<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FeeReviewController extends Controller
{
    // GET /api/manager/fee-reviews
    //   query: status=pending|approved|rejected|all|receipt_requested  (default=pending)
    //   q=search (submitter name / task name)
    //   返回 { kpi: {...}, items: TaskFee[] }
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            abort(403, '僅 manager / admin 可使用');
        }

        // 可見的 project 範圍
        $projectIds = $this->visibleProjectIds($user);

        $base = TaskFee::query()
            ->whereIn('project_id', $projectIds);

        // KPI counts (full scope, 不受 status filter 影響)
        $monthStart = Carbon::now()->startOfMonth();
        $kpi = [
            'pending_count'           => (int) (clone $base)->where('status', TaskFee::STATUS_PENDING)->count(),
            'pending_amount'          => (float) (clone $base)->where('status', TaskFee::STATUS_PENDING)->sum('amount'),
            'receipt_requested_count' => (int) (clone $base)->whereNotNull('receipt_requested_at')->count(),
            'approved_this_month'     => (int) (clone $base)->where('status', TaskFee::STATUS_APPROVED)
                                            ->where('reviewed_at', '>=', $monthStart)->count(),
            'approved_this_month_amount' => (float) (clone $base)->where('status', TaskFee::STATUS_APPROVED)
                                            ->where('reviewed_at', '>=', $monthStart)->sum('amount'),
            'rejected_count'          => (int) (clone $base)->where('status', TaskFee::STATUS_REJECTED)->count(),
            'total_count'             => (int) (clone $base)->count(),
        ];

        $status = $request->query('status', 'pending');
        $q      = $request->query('q');

        $list = (clone $base)
            ->with([
                'task:id,name,project_id',
                'task.project:id,name',
                'submitter:id,name',
                'reviewer:id,name',
                'unapprover:id,name',
                'receiptRequester:id,name',
                'attachments',
            ]);

        switch ($status) {
            case 'pending':
                $list->where('status', TaskFee::STATUS_PENDING);
                break;
            case 'approved':
                $list->where('status', TaskFee::STATUS_APPROVED);
                break;
            case 'rejected':
                $list->where('status', TaskFee::STATUS_REJECTED);
                break;
            case 'receipt_requested':
                $list->whereNotNull('receipt_requested_at');
                break;
            case 'all':
            default:
                break;
        }

        if ($q) {
            $like = "%{$q}%";
            $list->where(function ($qq) use ($like) {
                $qq->whereHas('submitter', fn ($u) => $u->where('name', 'like', $like))
                   ->orWhereHas('task', fn ($t) => $t->where('name', 'like', $like))
                   ->orWhereHas('task.project', fn ($p) => $p->where('name', 'like', $like));
            });
        }

        $items = $list->orderByDesc('created_at')->limit(200)->get();

        return response()->json([
            'kpi'   => $kpi,
            'items' => $items,
        ]);
    }

    private function visibleProjectIds($user): array
    {
        if ($user->isAdmin()) {
            return Project::pluck('id')->all();
        }

        // Manager: 同公司
        return Project::where('company_id', $user->company_id)->pluck('id')->all();
    }
}
