<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->view($user, $task->project);
    }

    public function create(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->update($user, $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        if ((new ProjectPolicy())->update($user, $task->project)) {
            return true;
        }
        // Members can update their own assigned tasks
        return $user->isMember()
            && (new ProjectPolicy())->view($user, $task->project)
            && $task->assignee_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->update($user, $task->project);
    }
}
