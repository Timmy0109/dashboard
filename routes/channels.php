<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Allow project members (and admins) to subscribe to project-specific channel
Broadcast::channel('project.{projectId}', function (User $user, int $projectId) {
    if ($user->isAdmin()) {
        return true;
    }
    $project = Project::find($projectId);
    if (!$project) {
        return false;
    }
    return $project->owner_id === $user->id
        || $project->members()->where('user_id', $user->id)->exists();
});

// Task-scoped WebSocket: subscribers receive CommentCreated / CommentReplied /
// AttachmentUploaded / HistoryEventRecorded for that specific task. Permission
// mirrors project access — any project member can listen.
Broadcast::channel('task.{taskId}', function (User $user, int $taskId) {
    if ($user->isAdmin()) {
        return true;
    }
    $task = Task::find($taskId);
    if (! $task) {
        return false;
    }
    $project = $task->project;
    if (! $project) {
        return false;
    }
    return $project->owner_id === $user->id
        || $project->members()->where('user_id', $user->id)->exists();
});

// Per-user notification channel. Only the user themselves may subscribe.
Broadcast::channel('notifications.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

// Company-wide activity feed channel. Users only see their own company's events.
// Frontend filters further by "projects I'm a member of".
Broadcast::channel('company.{companyId}.activity', function (User $user, int $companyId) {
    return $user->company_id === $companyId;
});
