<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    // GET /api/finance/overview
    // 公司範圍財務總覽（唯讀）：每專案 預算 / 已核發支出 / 行政費用 / 進行中 / 餘額
    // 僅費用流程參與者（老闆一階 / 會計二階）可用；admin 不參與費用
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->canAccessFees() || $user->company_id === null) {
            abort(403, '僅費用流程相關人員（老闆 / 會計）可使用');
        }

        $projects = Project::query()
            ->where('company_id', $user->company_id)
            ->withSum(['taskFees as task_disbursed' => fn ($q) => $q->where('status', TaskFee::STATUS_DISBURSED)], 'amount')
            ->withSum(['taskFees as task_in_flight' => fn ($q) => $q->whereIn('status', [TaskFee::STATUS_PENDING, TaskFee::STATUS_REVIEWED])], 'amount')
            ->withSum('adminFees as admin_total', 'amount')
            ->orderBy('name')
            ->get()
            ->map(function (Project $p) {
                $taskDisbursed = (float) ($p->task_disbursed ?? 0);
                $taskInFlight  = (float) ($p->task_in_flight ?? 0);
                $adminTotal    = (float) ($p->admin_total ?? 0);
                $budget        = (float) ($p->total_budget ?? 0);
                $spent         = $taskDisbursed + $adminTotal;

                return [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'total_budget'   => $budget,
                    'task_disbursed' => $taskDisbursed,
                    'task_in_flight' => $taskInFlight,
                    'admin_total'    => $adminTotal,
                    'spent'          => $spent,
                    'remaining'      => $budget - $spent,
                ];
            });

        return response()->json([
            'totals' => [
                'budget'         => $projects->sum('total_budget'),
                'spent'          => $projects->sum('spent'),
                'task_in_flight' => $projects->sum('task_in_flight'),
                'remaining'      => $projects->sum('remaining'),
            ],
            'projects' => $projects->values(),
        ]);
    }
}
