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
use Illuminate\Support\Carbon;

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
            ->whereNotNull('assignee_id')
            ->select('assignee_id', DB::raw('count(*) as task_count'))
            ->groupBy('assignee_id')
            ->with('assignee:id,name')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->assignee_id,
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

    public function memberDetail(Request $request, int $userId): JsonResponse
    {
        $viewer = $request->user();
        $target = User::findOrFail($userId);

        // Permission: admin sees everyone; manager sees own company members
        if (!$viewer->isAdmin() && $viewer->company_id !== $target->company_id) {
            abort(403);
        }

        $today = now()->toDateString();
        $weekEnd = now()->addDays(7)->toDateString();

        $tasks = Task::with(['project:id,name', 'status:id,name,color,icon', 'priority:id,name,color'])
            ->where('assignee_id', $userId)
            ->get();

        $total      = $tasks->count();
        $completed  = $tasks->where('is_completed', true)->count();
        $overdue    = $tasks->where('is_completed', false)->filter(fn ($t) => $t->end_date && $t->end_date->toDateString() < $today)->count();
        $pending    = $tasks->where('is_completed', false)->where('progress', 0)->count();
        $inProgress = $tasks->where('is_completed', false)->filter(fn ($t) => $t->progress > 0)->count();
        $dueSoon    = $tasks->where('is_completed', false)->filter(fn ($t) => $t->end_date && $t->end_date->toDateString() >= $today && $t->end_date->toDateString() <= $weekEnd)->count();

        $avgProgress = $total > 0
            ? (int) round($tasks->where('is_completed', false)->avg('progress') ?? 0)
            : 0;

        // Per-project breakdown
        $byProject = $tasks->groupBy('project_id')->map(function ($group) use ($today) {
            $proj = $group->first()->project;
            return [
                'project_id'   => $proj->id,
                'project_name' => $proj->name,
                'total'        => $group->count(),
                'completed'    => $group->where('is_completed', true)->count(),
                'overdue'      => $group->where('is_completed', false)->filter(fn ($t) => $t->end_date && $t->end_date->toDateString() < $today)->count(),
            ];
        })->values();

        // Task list (most actionable first: overdue → pending → in_progress → completed)
        $taskList = $tasks->sortBy([
            fn ($a, $b) => $a->is_completed <=> $b->is_completed,
            fn ($a, $b) => ($b->end_date?->toDateString() ?? '') <=> ($a->end_date?->toDateString() ?? ''),
        ])->values()->map(fn ($t) => [
            'id'           => $t->id,
            'name'         => $t->name,
            'project_id'   => $t->project_id,
            'project_name' => $t->project?->name,
            'end_date'     => $t->end_date?->toDateString(),
            'progress'     => $t->progress,
            'is_completed' => $t->is_completed,
            'is_overdue'   => !$t->is_completed && $t->end_date && $t->end_date->toDateString() < $today,
            'status'       => $t->status ? ['id' => $t->status->id, 'name' => $t->status->name, 'color' => $t->status->color, 'icon' => $t->status->icon] : null,
            'priority'     => $t->priority ? ['id' => $t->priority->id, 'name' => $t->priority->name, 'color' => $t->priority->color] : null,
        ]);

        return response()->json([
            'member' => ['id' => $target->id, 'name' => $target->name, 'email' => $target->email],
            'summary' => [
                'total'        => $total,
                'completed'    => $completed,
                'pending'      => $pending,
                'in_progress'  => $inProgress,
                'overdue'      => $overdue,
                'due_soon'     => $dueSoon,
                'overdue_rate' => $total > 0 ? (int) round($overdue / max($total - $completed, 1) * 100) : 0,
                'avg_progress' => $avgProgress,
                'project_count' => $byProject->count(),
            ],
            'by_project' => $byProject,
            'tasks'      => $taskList,
        ]);
    }
}
