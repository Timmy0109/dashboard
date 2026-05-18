<?php

namespace App\Observers;

use App\Events\ProjectProgressUpdated;
use App\Events\TaskDeleted;
use App\Events\TaskSaved;
use App\Models\Task;
use App\Models\TaskActivity;

class TaskObserver
{
    public function creating(Task $task): void
    {
        // created_by is set by controller; we log after creation in created()
    }

    public function created(Task $task): void
    {
        TaskActivity::create([
            'task_id'    => $task->id,
            'actor_id'   => $task->created_by,
            'event'      => 'created',
            'payload'    => null,
            'created_at' => now(),
        ]);
    }

    public function updating(Task $task): void
    {
        $dirty = $task->getDirty();
        $original = $task->getOriginal();

        $actorId = request()->user()?->id ?? $task->created_by;
        $now = now();

        // Assignee changed
        if (array_key_exists('assignee_id', $dirty) && $dirty['assignee_id'] !== $original['assignee_id']) {
            $fromName = $original['assignee_id']
                ? \App\Models\User::find($original['assignee_id'])?->name ?? '未指派'
                : '未指派';
            $toName = $dirty['assignee_id']
                ? \App\Models\User::find($dirty['assignee_id'])?->name ?? '未指派'
                : '未指派';

            TaskActivity::create([
                'task_id'    => $task->id,
                'actor_id'   => $actorId,
                'event'      => 'assignee_changed',
                'payload'    => ['from' => $fromName, 'to' => $toName],
                'created_at' => $now,
            ]);
        }

        // Status changed
        if (array_key_exists('status_id', $dirty) && $dirty['status_id'] !== $original['status_id']) {
            $fromName = $original['status_id']
                ? \App\Models\StatusRule::find($original['status_id'])?->name ?? '未知'
                : '未知';
            $toName = $dirty['status_id']
                ? \App\Models\StatusRule::find($dirty['status_id'])?->name ?? '未知'
                : '未知';

            TaskActivity::create([
                'task_id'    => $task->id,
                'actor_id'   => $actorId,
                'event'      => 'status_changed',
                'payload'    => ['from' => $fromName, 'to' => $toName],
                'created_at' => $now,
            ]);
        }

        // Progress updated (only log if not due to is_completed sync)
        if (array_key_exists('progress', $dirty)
            && $dirty['progress'] !== $original['progress']
            && ! array_key_exists('is_completed', $dirty)
        ) {
            TaskActivity::create([
                'task_id'    => $task->id,
                'actor_id'   => $actorId,
                'event'      => 'progress_updated',
                'payload'    => ['from' => $original['progress'], 'to' => $dirty['progress']],
                'created_at' => $now,
            ]);
        }

        // Completed / reopened
        if (array_key_exists('is_completed', $dirty) && $dirty['is_completed'] !== $original['is_completed']) {
            TaskActivity::create([
                'task_id'    => $task->id,
                'actor_id'   => $actorId,
                'event'      => $dirty['is_completed'] ? 'completed' : 'reopened',
                'payload'    => null,
                'created_at' => $now,
            ]);
        }
    }

    public function saved(Task $task): void
    {
        $task->loadMissing(['assignee', 'status', 'priority']);
        $project = $task->project;
        $project->recalculateProgress();

        broadcast(new TaskSaved($task));
        broadcast(new ProjectProgressUpdated($project->fresh()));
    }

    public function deleted(Task $task): void
    {
        $projectId = $task->project_id;
        $taskId = $task->id;

        $project = $task->project;
        $project->recalculateProgress();

        broadcast(new TaskDeleted($taskId, $projectId));
        broadcast(new ProjectProgressUpdated($project->fresh()));
    }
}
