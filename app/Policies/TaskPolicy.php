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
        return (new ProjectPolicy())->update($user, $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->update($user, $task->project);
    }
}
