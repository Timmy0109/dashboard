<?php

namespace App\Observers;

use App\Models\Task;

class TaskObserver
{
    public function saved(Task $task): void
    {
        $task->project->recalculateProgress();
    }

    public function deleted(Task $task): void
    {
        $task->project->recalculateProgress();
    }
}
