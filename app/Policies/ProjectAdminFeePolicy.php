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

        // 會計（或其他費用流程參與者）同公司唯讀
        return $user->company_id !== null
            && $user->company_id === $project->company_id
            && $user->canAccessFees();
    }

    public function view(User $user, ProjectAdminFee $fee): bool
    {
        return $this->viewAny($user, $fee->project);
    }

    public function create(User $user, Project $project): bool
    {
        // boss（免審）或專案經理在自己的專案建立（建立後 pending，待會計審核）
        return $this->canWrite($user, $project) || $this->managerOwns($user, $project);
    }

    public function update(User $user, ProjectAdminFee $fee): bool
    {
        if ($this->canWrite($user, $fee->project)) return true;

        // 專案經理僅可改自己建立、且尚未審核（pending）的行政費
        return $this->managerOwns($user, $fee->project)
            && $fee->created_by === $user->id
            && $fee->status === ProjectAdminFee::STATUS_PENDING;
    }

    public function delete(User $user, ProjectAdminFee $fee): bool
    {
        if ($this->canWrite($user, $fee->project)) return true;

        return $this->managerOwns($user, $fee->project)
            && $fee->created_by === $user->id
            && $fee->status === ProjectAdminFee::STATUS_PENDING;
    }

    /**
     * 審核（pending → approved/rejected）：
     * 會計專有（無會計的公司由老闆兼審），且須同公司、費用仍為 pending。
     */
    public function review(User $user, ProjectAdminFee $fee): bool
    {
        return $user->canReviewAdminFee()
            && $user->company_id !== null
            && $user->company_id === $fee->project->company_id
            && $fee->status === ProjectAdminFee::STATUS_PENDING;
    }

    /** 寫入權：boss(owner) only。會計不可寫；admin 已被 before() 全面排除。 */
    private function canWrite(User $user, Project $project): bool
    {
        return $user->canManage() && (new ProjectPolicy())->update($user, $project);
    }

    /** 專案經理且為該專案 owner（ProjectPolicy::update 對 manager 僅放行自己的專案） */
    private function managerOwns(User $user, Project $project): bool
    {
        return $user->isManager() && (new ProjectPolicy())->update($user, $project);
    }
}
