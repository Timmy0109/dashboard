<?php

namespace App\Observers;

use App\Events\ProjectProgressUpdated;
use App\Events\TaskDeleted;
use App\Events\TaskSaved;
use App\Models\Task;

class TaskObserver
{
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
