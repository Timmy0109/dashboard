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
            ->with(['creator:id,name', 'attachments'])
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

        $fee = $project->adminFees()->create([
            'created_by'  => $request->user()->id,
            'amount'      => $data['amount'],
            'note'        => $data['note'] ?? null,
            'incurred_on' => $data['incurred_on'] ?? null,
        ]);

        $fee->load(['creator:id,name', 'attachments']);
        return response()->json($fee, 201);
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
        $fee->load(['creator:id,name', 'attachments']);
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
        $canSeeAdminFees = $user->isAdmin()
            || ($user->isManager() && (new \App\Policies\ProjectPolicy())->update($user, $project));

        $taskApproved = $project->taskFees()
            ->where('status', \App\Models\TaskFee::STATUS_APPROVED)->sum('amount');
        $taskPending = $project->taskFees()
            ->where('status', \App\Models\TaskFee::STATUS_PENDING)->sum('amount');
        $adminTotal = $project->adminFees()->sum('amount');

        $payload = [
            'total'                  => (float) ($taskApproved + $adminTotal),
            'task_fees_approved'     => (float) $taskApproved,
            'task_fees_pending'      => (float) $taskPending,
        ];

        if ($canSeeAdminFees) {
            $payload['admin_fees'] = (float) $adminTotal;
        }

        return response()->json($payload);
    }
}
