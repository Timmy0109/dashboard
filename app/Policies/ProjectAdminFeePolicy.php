<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectAdminFee;
use App\Models\User;

class ProjectAdminFeePolicy
{
    /** admin 只管系統層面，不參與行政費用 */
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? false : null;
    }

    /**
     * 唯讀：boss(owner) 可看；
     * 另放寬同公司且可審核費用者（會計）唯讀，因會計需檢視專案支出。
     * 寫入（create/update/delete）仍維持 boss(owner) only，見 canWrite()。
     */
    public function viewAny(User $user, Project $project): bool
    {
        if ($this->canWrite($user, $project)) return true;

        // 會計（或其他 canReviewFee 角色）同公司唯讀
        return $user->company_id !== null
            && $user->company_id === $project->company_id
            && $user->canReviewFee();
    }

    public function view(User $user, ProjectAdminFee $fee): bool
    {
        return $this->viewAny($user, $fee->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canWrite($user, $project);
    }

    public function update(User $user, ProjectAdminFee $fee): bool
    {
        return $this->canWrite($user, $fee->project);
    }

    public function delete(User $user, ProjectAdminFee $fee): bool
    {
        return $this->canWrite($user, $fee->project);
    }

    /** 寫入權：boss(owner) only。會計不可寫；admin 已被 before() 全面排除。 */
    private function canWrite(User $user, Project $project): bool
    {
        return $user->canManage() && (new ProjectPolicy())->update($user, $project);
    }
}
