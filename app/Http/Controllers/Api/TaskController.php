<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Event label map used by activities()
// Maps DB event keys to Chinese display strings (kept close to usage)

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
            'completed_at' => 'sometimes|nullable|date',
        ]);

        if (isset($validated['is_completed'])) {
            if ($validated['is_completed'] === true && ! $task->is_completed) {
                $validated['progress']     = 100;
                $validated['completed_at'] = now();
            } elseif ($validated['is_completed'] === false && $task->is_completed) {
                $validated['progress']     = 0;
                $validated['completed_at'] = null;
            }
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

    // GET /projects/{project}/tasks/{task}/activities
    public function activities(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        $labelMap = [
            'created'          => '建立任務',
            'assignee_changed' => '指派負責人',
            'status_changed'   => '狀態更新',
            'progress_updated' => '進度更新',
            'completed'        => '標記完成',
            'reopened'         => '重新開啟',
            'commented'        => '新增留言',
            'attached'         => '上傳附件',
            'detached'         => '刪除附件',
        ];

        $activities = $task->activities()
            ->with('actor:id,name,avatar')
            ->get()
            ->map(function ($a) use ($labelMap) {
                $payload = $a->payload ?? [];
                $description = match ($a->event) {
                    'assignee_changed' => "從「{$payload['from']}」變更為「{$payload['to']}」",
                    'status_changed'   => "從「{$payload['from']}」變更為「{$payload['to']}」",
                    'progress_updated' => "從「{$payload['from']}%」調整為「{$payload['to']}%」",
                    default            => null,
                };

                return [
                    'id'          => $a->id,
                    'event'       => $a->event,
                    'label'       => $labelMap[$a->event] ?? $a->event,
                    'description' => $description,
                    'payload'     => $a->payload,
                    'actor'       => $a->actor ? [
                        'id'         => $a->actor->id,
                        'name'       => $a->actor->name,
                        'avatar_url' => $a->actor->avatar_url,
                    ] : null,
                    'created_at'  => $a->created_at->format('Y/m/d H:i'),
                ];
            });

        return response()->json($activities);
    }
}
