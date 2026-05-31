<?php

namespace App\Observers;

use App\Events\NotificationCreated;
use App\Events\ProjectProgressUpdated;
use App\Events\TaskDeleted;
use App\Events\TaskSaved;
use App\Models\Notification;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Support\SafeBroadcast;

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

        // If task was created with an assignee that isn't the creator,
        // notify the assignee. (Updating-path already covers re-assigns.)
        if ($task->assignee_id && $task->assignee_id !== $task->created_by) {
            $task->loadMissing('project');
            $this->notify(
                $task->assignee_id,
                'task_assigned',
                [
                    'task_id'      => $task->id,
                    'task_name'    => $task->name,
                    'project_id'   => $task->project_id,
                    'project_name' => $task->project?->name,
                    'actor_name'   => User::find($task->created_by)?->name ?? '系統',
                ]
            );
        }
    }

    public function updating(Task $task): void
    {
        $dirty = $task->getDirty();
        $original = $task->getOriginal();

        $actorId = request()->user()?->id ?? $task->created_by;
        $now = now();

        // Assignee changed — log activity + notify new assignee (if not self-assigning)
        if (array_key_exists('assignee_id', $dirty) && $dirty['assignee_id'] !== $original['assignee_id']) {
            $fromName = $original['assignee_id']
                ? User::find($original['assignee_id'])?->name ?? '未指派'
                : '未指派';
            $toName = $dirty['assignee_id']
                ? User::find($dirty['assignee_id'])?->name ?? '未指派'
                : '未指派';

            TaskActivity::create([
                'task_id'    => $task->id,
                'actor_id'   => $actorId,
                'event'      => 'assignee_changed',
                'payload'    => ['from' => $fromName, 'to' => $toName],
                'created_at' => $now,
            ]);

            if ($dirty['assignee_id'] && $dirty['assignee_id'] !== $actorId) {
                $this->notify(
                    $dirty['assignee_id'],
                    'task_assigned',
                    [
                        'task_id'      => $task->id,
                        'task_name'    => $task->name,
                        'project_id'   => $task->project_id,
                        'project_name' => $task->project?->name,
                        'actor_name'   => User::find($actorId)?->name ?? '系統',
                    ]
                );
            }
        }

        // Status changed — log activity + notify assignee (if not self-changing)
        if (array_key_exists('status_id', $dirty) && $dirty['status_id'] !== $original['status_id']) {
            $fromName = $original['status_id']
                ? StatusRule::find($original['status_id'])?->name ?? '未知'
                : '未知';
            $toName = $dirty['status_id']
                ? StatusRule::find($dirty['status_id'])?->name ?? '未知'
                : '未知';

            TaskActivity::create([
                'task_id'    => $task->id,
                'actor_id'   => $actorId,
                'event'      => 'status_changed',
                'payload'    => ['from' => $fromName, 'to' => $toName],
                'created_at' => $now,
            ]);

            $assigneeId = $dirty['assignee_id'] ?? $original['assignee_id'] ?? null;
            if ($assigneeId && $assigneeId !== $actorId) {
                $this->notify(
                    $assigneeId,
                    'task_status_changed',
                    [
                        'task_id'    => $task->id,
                        'task_name'  => $task->name,
                        'project_id' => $task->project_id,
                        'from'       => $fromName,
                        'to'         => $toName,
                        'actor_name' => User::find($actorId)?->name ?? '系統',
                    ]
                );
            }
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

        SafeBroadcast::dispatch(new TaskSaved($task));
        SafeBroadcast::dispatch(new ProjectProgressUpdated($project->fresh()));
    }

    public function deleted(Task $task): void
    {
        $projectId = $task->project_id;
        $taskId = $task->id;

        $project = $task->project;
        $project->recalculateProgress();

        SafeBroadcast::dispatch(new TaskDeleted($taskId, $projectId));
        SafeBroadcast::dispatch(new ProjectProgressUpdated($project->fresh()));
    }

    /** Create + broadcast a notification. */
    private function notify(int $userId, string $type, array $payload): void
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'payload' => $payload,
        ]);

        SafeBroadcast::dispatch(new NotificationCreated($notification));
    }
}
