<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectAdminFee;
use App\Models\User;

class ProjectAdminFeePolicy
{
    /** 只有 admin / boss 可建/改/刪行政費用；accountant 不在此 scope */
    public function viewAny(User $user, Project $project): bool
    {
        if ($user->isAdmin()) return true;
        return $user->canManage() && (new ProjectPolicy())->update($user, $project);
    }

    public function view(User $user, ProjectAdminFee $fee): bool
    {
        return $this->viewAny($user, $fee->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->viewAny($user, $project);
    }

    public function update(User $user, ProjectAdminFee $fee): bool
    {
        return $this->viewAny($user, $fee->project);
    }

    public function delete(User $user, ProjectAdminFee $fee): bool
    {
        return $this->viewAny($user, $fee->project);
    }
}
