<?php

use App\Models\Project;
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
