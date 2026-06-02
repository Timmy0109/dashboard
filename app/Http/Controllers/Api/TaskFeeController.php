<?php

namespace App\Http\Controllers\Api;

use App\Events\NotificationCreated;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskFee;
use App\Models\TaskFeeStateLog;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskFeeController extends Controller
{
    // GET /api/projects/{project}/tasks/{task}/fees
    public function index(Request $request, $project, Task $task): JsonResponse
    {
        $this->authorize('viewAny', [TaskFee::class, $task]);

        $user = $request->user();
        $query = $task->fees()
            ->with(['submitter:id,name', 'reviewer:id,name', 'unapprover:id,name', 'attachments']);

        // Member 只看自己提的明細
        if (! $user->isAdmin() && ! $user->isManager()) {
            $query->where('submitted_by', $user->id);
        }

        $fees = $query->orderByDesc('created_at')->get();

        return response()->json($fees);
    }

    // GET /api/projects/{project}/task-fees — 整個專案的任務費用
    //  - admin / manager: 全部
    //  - member: 只看自己提的
    public function projectIndex(Request $request, \App\Models\Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $user = $request->user();
        $query = $project->taskFees()
            ->with([
                'task:id,name,project_id',
                'submitter:id,name',
                'reviewer:id,name',
                'unapprover:id,name',
                'attachments',
            ]);

        if (! $user->isAdmin() && ! $user->isManager()) {
            $query->where('submitted_by', $user->id);
        }

        $fees = $query->orderByDesc('created_at')->limit(100)->get();

        return response()->json($fees);
    }

    // POST /api/projects/{project}/tasks/{task}/fees
    public function store(Request $request, $project, Task $task): JsonResponse
    {
        $this->authorize('create', [TaskFee::class, $task]);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:99999999.99',
            'note'   => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $fee = TaskFee::create([
            'task_id'       => $task->id,
            'project_id'    => $task->project_id,
            'submitted_by'  => $user->id,
            'amount'        => $data['amount'],
            'note'          => $data['note'] ?? null,
            'status'        => TaskFee::STATUS_PENDING,
        ]);

        TaskFeeStateLog::create([
            'task_fee_id' => $fee->id,
            'from_status' => null,
            'to_status'   => TaskFee::STATUS_PENDING,
            'actor_id'    => $user->id,
            'reason'      => null,
            'created_at'  => now(),
        ]);

        // Notify project owner (manager) — 只通知 owner 一個人即可避免 spam
        $ownerId = $task->project->owner_id;
        if ($ownerId && $ownerId !== $user->id) {
            $this->notify($ownerId, 'fee_submitted', [
                'task_fee_id' => $fee->id,
                'task_id'     => $task->id,
                'project_id'  => $task->project_id,
                'task_name'   => $task->name,
                'amount'      => (float) $fee->amount,
                'submitter'   => $user->name,
            ]);
        }

        $fee->load(['submitter:id,name', 'attachments']);
        return response()->json($fee, 201);
    }

    // PATCH /api/task-fees/{fee}
    public function update(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('update', $fee);

        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:0|max:99999999.99',
            'note'   => 'sometimes|nullable|string|max:1000',
        ]);

        $fee->update($data);
        $fee->load(['submitter:id,name', 'reviewer:id,name', 'attachments']);
        return response()->json($fee);
    }

    // DELETE /api/task-fees/{fee}
    public function destroy(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('delete', $fee);
        $fee->delete();
        return response()->json(['message' => '已撤回']);
    }

    // POST /api/task-fees/{fee}/resubmit
    public function resubmit(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('resubmit', $fee);

        try {
            $fee->transitionTo(TaskFee::STATUS_PENDING, $request->user(), '重新提交');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => '此費用狀態已被其他人變更，請重新整理'], 409);
        }

        $ownerId = $fee->project->owner_id;
        if ($ownerId && $ownerId !== $request->user()->id) {
            $this->notify($ownerId, 'fee_submitted', [
                'task_fee_id' => $fee->id,
                'task_id'     => $fee->task_id,
                'project_id'  => $fee->project_id,
                'task_name'   => $fee->task->name,
                'amount'      => (float) $fee->amount,
                'submitter'   => $request->user()->name,
                'is_resubmit' => true,
            ]);
        }

        $fee->load(['submitter:id,name', 'reviewer:id,name']);
        return response()->json($fee);
    }

    // POST /api/task-fees/{fee}/approve
    public function approve(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('approve', $fee);
        try {
            $fee->transitionTo(TaskFee::STATUS_APPROVED, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => '此費用狀態已被其他人變更，請重新整理'], 409);
        }

        $this->notify($fee->submitted_by, 'fee_approved', [
            'task_fee_id' => $fee->id,
            'task_id'     => $fee->task_id,
            'project_id'  => $fee->project_id,
            'amount'      => (float) $fee->amount,
            'reviewer'    => $request->user()->name,
        ]);

        $fee->load(['submitter:id,name', 'reviewer:id,name']);
        return response()->json($fee);
    }

    // POST /api/task-fees/{fee}/reject
    public function reject(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('reject', $fee);

        $data = $request->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        try {
            $fee->transitionTo(TaskFee::STATUS_REJECTED, $request->user(), $data['reject_reason']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => '此費用狀態已被其他人變更，請重新整理'], 409);
        }

        $this->notify($fee->submitted_by, 'fee_rejected', [
            'task_fee_id'    => $fee->id,
            'task_id'        => $fee->task_id,
            'project_id'     => $fee->project_id,
            'amount'         => (float) $fee->amount,
            'reviewer'       => $request->user()->name,
            'reject_reason'  => $data['reject_reason'],
        ]);

        $fee->load(['submitter:id,name', 'reviewer:id,name']);
        return response()->json($fee);
    }

    // POST /api/task-fees/{fee}/unapprove
    public function unapprove(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('unapprove', $fee);

        $data = $request->validate([
            'unapprove_reason' => 'required|string|max:500',
        ]);

        try {
            $fee->transitionTo(TaskFee::STATUS_PENDING, $request->user(), $data['unapprove_reason']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => '此費用狀態已被其他人變更，請重新整理'], 409);
        }

        $this->notify($fee->submitted_by, 'fee_unapproved', [
            'task_fee_id'      => $fee->id,
            'task_id'          => $fee->task_id,
            'project_id'       => $fee->project_id,
            'amount'           => (float) $fee->amount,
            'reviewer'         => $request->user()->name,
            'unapprove_reason' => $data['unapprove_reason'],
        ]);

        $fee->load(['submitter:id,name', 'reviewer:id,name', 'unapprover:id,name']);
        return response()->json($fee);
    }

    // POST /api/task-fees/{fee}/request-receipt
    public function requestReceipt(Request $request, TaskFee $fee): JsonResponse
    {
        $this->authorize('requestReceipt', $fee);

        $data = $request->validate([
            'message' => 'nullable|string|max:500',
        ]);

        $this->notify($fee->submitted_by, 'fee_receipt_requested', [
            'task_fee_id' => $fee->id,
            'task_id'     => $fee->task_id,
            'project_id'  => $fee->project_id,
            'amount'      => (float) $fee->amount,
            'reviewer'    => $request->user()->name,
            'message'     => $data['message'] ?? null,
        ]);

        return response()->json(['message' => '已通知提交者補件']);
    }

    private function notify(int $userId, string $type, array $payload): void
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'payload' => $payload,
        ]);
        SafeBroadcast::dispatch(new NotificationCreated($notification));
    }
}
