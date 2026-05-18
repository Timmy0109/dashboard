<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projectQuery = match ($user->role) {
            'admin' => Project::query(),
            'manager' => Project::where('owner_id', $user->id),
            default => Project::whereHas('members', fn($q) => $q->where('user_id', $user->id)),
        };

        $projects = (clone $projectQuery)->with(['category', 'priority', 'status'])->latest()->get();

        $stats = [
            'total_projects' => (clone $projectQuery)->count(),
            'active_projects' => (clone $projectQuery)->where('is_completed', false)->count(),
            'completed_projects' => (clone $projectQuery)->where('is_completed', true)->count(),
        ];

        $projectIds = (clone $projectQuery)->pluck('id');

        $taskStats = [
            'total_tasks' => Task::whereIn('project_id', $projectIds)->count(),
            'completed_tasks' => Task::whereIn('project_id', $projectIds)->where('is_completed', true)->count(),
            'overdue_tasks' => Task::whereIn('project_id', $projectIds)
                ->where('is_completed', false)
                ->where('end_date', '<', now()->toDateString())
                ->count(),
        ];

        return response()->json([
            'stats' => array_merge($stats, $taskStats),
            'projects' => $projects,
        ]);
    }

    public function myTasks(Request $request): JsonResponse
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $base = Task::with(['project:id,name', 'status:id,name,color,icon', 'priority:id,name,color'])
            ->where('assignee_id', $user->id)
            ->where('is_completed', false);

        $overdue = (clone $base)
            ->where('end_date', '<', $today)
            ->orderBy('end_date')
            ->get()
            ->map(fn($t) => $this->formatTask($t, $today));

        $dueSoon = (clone $base)
            ->whereBetween('end_date', [$today, now()->addDays(7)->toDateString()])
            ->orderBy('end_date')
            ->get()
            ->map(fn($t) => $this->formatTask($t, $today));

        $inProgress = (clone $base)
            ->where('progress', '>', 0)
            ->where('end_date', '>=', $today)
            ->orderBy('end_date')
            ->limit(10)
            ->get()
            ->map(fn($t) => $this->formatTask($t, $today));

        return response()->json([
            'overdue'     => $overdue,
            'due_soon'    => $dueSoon,
            'in_progress' => $inProgress,
        ]);
    }

    private function formatTask(Task $t, string $today): array
    {
        return [
            'id'           => $t->id,
            'name'         => $t->name,
            'project_id'   => $t->project_id,
            'project_name' => $t->project?->name,
            'end_date'     => $t->end_date?->toDateString(),
            'progress'     => $t->progress,
            'is_overdue'   => $t->end_date && $t->end_date->toDateString() < $today,
            'status'       => $t->status ? ['name' => $t->status->name, 'color' => $t->status->color, 'icon' => $t->status->icon] : null,
            'priority'     => $t->priority ? ['name' => $t->priority->name, 'color' => $t->priority->color] : null,
        ];
    }
}
