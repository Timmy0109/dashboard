<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projectIds = match ($user->role) {
            'admin' => Project::pluck('id'),
            'manager' => Project::where('owner_id', $user->id)->pluck('id'),
            default => Project::whereHas('members', fn($q) => $q->where('user_id', $user->id))->pluck('id'),
        };

        $tasks = Task::with(['project', 'assignee', 'status', 'priority'])
            ->whereIn('project_id', $projectIds)
            ->orderByRaw('is_completed ASC')
            ->orderBy('end_date')
            ->get()
            ->map(function (Task $task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'note' => $task->note,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project->name,
                    'start_date' => $task->start_date->toDateString(),
                    'end_date' => $task->end_date->toDateString(),
                    'is_overdue' => $task->isOverdue(),
                    'progress' => $task->progress,
                    'is_completed' => $task->is_completed,
                    'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
                    'status' => $task->status ? ['id' => $task->status->id, 'name' => $task->status->name, 'color' => $task->status->color, 'icon' => $task->status->icon] : null,
                    'priority' => $task->priority ? ['id' => $task->priority->id, 'name' => $task->priority->name, 'color' => $task->priority->color] : null,
                ];
            });

        return response()->json($tasks);
    }
}
