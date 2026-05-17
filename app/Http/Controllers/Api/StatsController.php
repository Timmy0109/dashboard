<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projectQuery = match ($user->role) {
            'admin' => Project::query(),
            'manager' => Project::where('owner_id', $user->id),
            default => Project::whereHas('members', fn ($q) => $q->where('user_id', $user->id)),
        };

        $projectIds = (clone $projectQuery)->pluck('id');

        // Project status distribution
        $statusDist = (clone $projectQuery)
            ->select('status_id', DB::raw('count(*) as count'))
            ->groupBy('status_id')
            ->with('status:id,name,color,icon')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status ? ['name' => $row->status->name, 'color' => $row->status->color, 'icon' => $row->status->icon] : ['name' => '未知', 'color' => '#6b7280', 'icon' => '⚪'],
                'count' => $row->count,
            ]);

        // Projects with progress (for bar chart)
        $projectProgress = (clone $projectQuery)
            ->select('id', 'name', 'progress_percent', 'is_completed')
            ->orderByDesc('progress_percent')
            ->limit(10)
            ->get();

        // Task assignee workload
        $taskWorkload = Task::whereIn('project_id', $projectIds)
            ->where('is_completed', false)
            ->select('assignee_id', DB::raw('count(*) as task_count'))
            ->groupBy('assignee_id')
            ->with('assignee:id,name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->assignee?->name ?? '未指派',
                'task_count' => $row->task_count,
            ]);

        // Monthly task completion trend (last 6 months)
        $dateFormat = match (config('database.default')) {
            'mysql', 'mariadb' => "DATE_FORMAT(updated_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(updated_at, 'YYYY-MM')",
            default => "strftime('%Y-%m', updated_at)",
        };

        $trend = Task::whereIn('project_id', $projectIds)
            ->where('is_completed', true)
            ->where('updated_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("$dateFormat as month"),
                DB::raw('count(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'status_distribution' => $statusDist,
            'project_progress' => $projectProgress,
            'task_workload' => $taskWorkload,
            'completion_trend' => $trend,
            'totals' => [
                'projects' => (clone $projectQuery)->count(),
                'tasks' => Task::whereIn('project_id', $projectIds)->count(),
                'completed_tasks' => Task::whereIn('project_id', $projectIds)->where('is_completed', true)->count(),
                'overdue_tasks' => Task::whereIn('project_id', $projectIds)->where('is_completed', false)->where('end_date', '<', now()->toDateString())->count(),
                'members' => match ($user->role) {
                    'admin'   => User::where('status', 'active')->where('role', '!=', 'admin')->count(),
                    default   => User::where('status', 'active')->where('role', '!=', 'admin')
                                     ->where('company_id', $user->company_id)
                                     ->where('id', '!=', $user->id)
                                     ->count(),
                },
            ],
        ]);
    }
}
