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
        $canSeeAll = $user->isAdmin()
            || ($user->isManager() && (new \App\Policies\ProjectPolicy())->update($user, $project));

        // Member: 只看得到自己提交的費用，看不到專案總額 / 預算 / 行政費用
        if (! $canSeeAll) {
            $ownApproved = $project->taskFees()
                ->where('submitted_by', $user->id)
                ->where('status', \App\Models\TaskFee::STATUS_APPROVED)->sum('amount');
            $ownPending = $project->taskFees()
                ->where('submitted_by', $user->id)
                ->where('status', \App\Models\TaskFee::STATUS_PENDING)->sum('amount');
            return response()->json([
                'scope'           => 'self',
                'own_approved'    => (float) $ownApproved,
                'own_pending'     => (float) $ownPending,
            ]);
        }

        $taskApproved = $project->taskFees()
            ->where('status', \App\Models\TaskFee::STATUS_APPROVED)->sum('amount');
        $taskPending = $project->taskFees()
            ->where('status', \App\Models\TaskFee::STATUS_PENDING)->sum('amount');
        $adminTotal = $project->adminFees()->sum('amount');
        $spent      = $taskApproved + $adminTotal;
        $budget     = (float) $project->total_budget;

        return response()->json([
            'scope'              => 'all',
            'total'              => (float) $spent,
            'task_fees_approved' => (float) $taskApproved,
            'task_fees_pending'  => (float) $taskPending,
            'admin_fees'         => (float) $adminTotal,
            'total_budget'       => $budget,
            'remaining'          => (float) ($budget - $spent),
            'over_budget'        => $spent > $budget,
        ]);
    }
}
