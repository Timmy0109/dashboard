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
}
