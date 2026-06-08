<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAdminFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAdminFeeController extends Controller
{
    // GET /api/projects/{project}/admin-fees
    public function index(Project $project): JsonResponse
    {
        $this->authorize('viewAny', [ProjectAdminFee::class, $project]);

        $fees = $project->adminFees()
            ->with(['creator:id,name', 'reviewer:id,name', 'attachments'])
            ->orderByDesc('incurred_on')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($fees);
    }

    // POST /api/projects/{project}/admin-fees
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('create', [ProjectAdminFee::class, $project]);

        $data = $request->validate([
            'amount'      => 'required|numeric|min:0|max:99999999.99',
            'note'        => 'nullable|string|max:1000',
            'incurred_on' => 'nullable|date',
        ]);

        // boss（canManage）建立 → 免審直接核准；專案經理建立 → pending 待會計審核
        $creator = $request->user();
        $isAutoApproved = $creator->canManage();

        $fee = $project->adminFees()->create([
            'created_by'  => $creator->id,
            'amount'      => $data['amount'],
            'note'        => $data['note'] ?? null,
            'incurred_on' => $data['incurred_on'] ?? null,
            'status'      => $isAutoApproved
                ? ProjectAdminFee::STATUS_APPROVED
                : ProjectAdminFee::STATUS_PENDING,
        ]);

        $fee->load(['creator:id,name', 'reviewer:id,name', 'attachments']);
        return response()->json($fee, 201);
    }

    // POST /api/project-admin-fees/{fee}/review
    // 會計（無會計時老闆兼審）核准或退件 PM 建立的行政費
    public function review(Request $request, ProjectAdminFee $fee): JsonResponse
    {
        $this->authorize('review', $fee);

        $data = $request->validate([
            'decision'    => 'required|in:approve,reject',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $fee->update([
            'status' => $data['decision'] === 'approve'
                ? ProjectAdminFee::STATUS_APPROVED
                : ProjectAdminFee::STATUS_REJECTED,
            'reviewed_by'  => $request->user()->id,
            'reviewed_at'  => now(),
            'review_note'  => $data['review_note'] ?? null,
        ]);

        $fee->load(['creator:id,name', 'reviewer:id,name', 'attachments']);
        return response()->json($fee);
    }

    // PATCH /api/project-admin-fees/{fee}
    public function update(Request $request, ProjectAdminFee $fee): JsonResponse
    {
        $this->authorize('update', $fee);

        $data = $request->validate([
            'amount'      => 'sometimes|numeric|min:0|max:99999999.99',
            'note'        => 'sometimes|nullable|string|max:1000',
            'incurred_on' => 'sometimes|nullable|date',
        ]);

        $fee->update($data);
        $fee->load(['creator:id,name', 'reviewer:id,name', 'attachments']);
        return response()->json($fee);
    }

    // DELETE /api/project-admin-fees/{fee}
    public function destroy(ProjectAdminFee $fee): JsonResponse
    {
        $this->authorize('delete', $fee);
        $fee->delete();
        return response()->json(['message' => '已刪除']);
    }

    // GET /api/projects/{project}/fee-summary
    // Member 只能拿到 total + admin_fees 不可見；admin/manager 拿全貌
    public function summary(\Illuminate\Http\Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $user = $request->user();
        // admin 不參與費用：看不到專案總額 / 預算 / 行政費用（落入 member self scope）
        // 老闆（全公司可管的專案）與專案經理（自己的專案）皆可看全貌
        $canSeeAll = ! $user->isAdmin()
            && ($user->canManage() || $user->isManager())
            && (new \App\Policies\ProjectPolicy())->update($user, $project);

        // Member: 只看得到自己提交的費用，看不到專案總額 / 預算 / 行政費用
        if (! $canSeeAll) {
            $base = $project->taskFees()->where('submitted_by', $user->id);
            $ownApproved = (float) (clone $base)->where('status', \App\Models\TaskFee::STATUS_DISBURSED)->sum('amount');
            $ownPending  = (float) (clone $base)->where('status', \App\Models\TaskFee::STATUS_PENDING)->sum('amount');
            $ownRejected = (float) (clone $base)->where('status', \App\Models\TaskFee::STATUS_REJECTED)->sum('amount');
            return response()->json([
                'scope'        => 'self',
                'own_approved' => $ownApproved,
                'own_pending'  => $ownPending,
                'own_rejected' => $ownRejected,
                'own_total'    => $ownApproved + $ownPending, // 已撥付 + in-flight
            ]);
        }

        // 「已撥付」= disbursed（核發完成）；reviewed 視同 in-flight
        $taskApproved = $project->taskFees()
            ->where('status', \App\Models\TaskFee::STATUS_DISBURSED)->sum('amount');
        $taskPending = $project->taskFees()
            ->where('status', \App\Models\TaskFee::STATUS_PENDING)->sum('amount');
        // 僅「已核准」行政費計入支出；待審（pending）另計、不佔預算
        $adminTotal   = $project->adminFees()
            ->where('status', ProjectAdminFee::STATUS_APPROVED)->sum('amount');
        $adminPending = $project->adminFees()
            ->where('status', ProjectAdminFee::STATUS_PENDING)->sum('amount');
        $spent      = $taskApproved + $adminTotal;
        $budget     = (float) $project->total_budget;

        return response()->json([
            'scope'              => 'all',
            'total'              => (float) $spent,
            'task_fees_approved' => (float) $taskApproved,
            'task_fees_pending'  => (float) $taskPending,
            'admin_fees'         => (float) $adminTotal,
            'admin_fees_pending' => (float) $adminPending,
            'total_budget'       => $budget,
            'remaining'          => (float) ($budget - $spent),
            'over_budget'        => $spent > $budget,
        ]);
    }
}
