<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(
            $project->tasks()->with(['assignee', 'status', 'priority'])->orderBy('start_date')->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'note' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'progress' => 'integer|min:0|max:100',
            'assignee_id' => 'nullable|exists:users,id',
            'status_id' => 'required|exists:status_rules,id',
            'priority_id' => 'required|exists:priorities,id',
        ]);

        $task = $project->tasks()->create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json($task->load(['assignee', 'status', 'priority']), 201);
    }

    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($task->load(['assignee', 'status', 'priority']));
    }

    public function update(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'note' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'progress' => 'sometimes|integer|min:0|max:100',
            'assignee_id' => 'nullable|exists:users,id',
            'status_id' => 'sometimes|exists:status_rules,id',
            'priority_id' => 'sometimes|exists:priorities,id',
            'is_completed' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_completed']) && $validated['is_completed'] === true && ! $task->is_completed) {
            $validated['end_date'] = now()->toDateString();
        }

        $task->update($validated);

        return response()->json($task->load(['assignee', 'status', 'priority']));
    }

    public function destroy(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }
}
