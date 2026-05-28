<?php

namespace App\Observers;

use App\Events\ActivityRecorded;
use App\Events\TaskHistoryEventRecorded;
use App\Models\TaskActivity;
use App\Support\SafeBroadcast;

/**
 * Fires whenever a TaskActivity row lands in the DB — regardless of which
 * controller / observer / job created it.
 *
 * Two broadcasts:
 *   TaskHistoryEventRecorded  → task.{taskId} channel       (in-modal history tab)
 *   ActivityRecorded          → company.{companyId}.activity (dashboard feed)
 */
class TaskActivityObserver
{
    public function created(TaskActivity $activity): void
    {
        // History event on the task channel.
        SafeBroadcast::dispatch(new TaskHistoryEventRecorded($activity));

        // Activity feed on the company channel.
        $project = $activity->task?->project;
        if ($project && $project->company_id) {
            SafeBroadcast::dispatch(new ActivityRecorded($activity, $project->company_id));
        }
    }
}
