<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskFee;
use App\Models\User;

class TaskFeePolicy
{
    /** 三階段流程：
     *  pending → reviewed (一階審核：accountant/boss/admin)
     *  reviewed → disbursed (二階核發：boss/admin)
     *  rejected ⇄ pending
     */

    public function viewAny(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->view($user, $task->project);
    }

    public function view(User $user, TaskFee $fee): bool
    {
        if (! (new ProjectPolicy())->view($user, $fee->project)) return false;
        if ($user->isAdmin() || $this->canReviewInProject($user, $fee)) return true;
        return $fee->submitted_by === $user->id;
    }

    public function create(User $user, Task $task): bool
    {
        if ($user->isAdmin() || $this->canManageProjectForTask($user, $task)) return true;
        return ($user->isMember() || $user->isAccountant()) && $task->assignee_id === $user->id;
    }

    public function update(User $user, TaskFee $fee): bool
    {
        if ($fee->submitted_by !== $user->id) return false;
        return $fee->isPending() || $fee->isRejected();
    }

    public function delete(User $user, TaskFee $fee): bool
    {
        if ($fee->submitted_by === $user->id) {
            return $fee->isPending() || $fee->isRejected();
        }
        return $this->canManageProject($user, $fee) && $fee->isRejected();
    }

    /** 一階審核：pending → reviewed（會計、老闆、admin） */
    public function review(User $user, TaskFee $fee): bool
    {
        return $user->canReviewFee()
            && $this->canReviewInProject($user, $fee)
            && $fee->isPending();
    }

    /** 二階核發：reviewed → disbursed（老闆、admin） */
    public function disburse(User $user, TaskFee $fee): bool
    {
        return $user->canDisburseFee()
            && $this->canReviewInProject($user, $fee)
            && $fee->isReviewed();
    }

    /** 退件：pending 或 reviewed 皆可被退 */
    public function reject(User $user, TaskFee $fee): bool
    {
        return $user->canReviewFee()
            && $this->canReviewInProject($user, $fee)
            && ($fee->isPending() || $fee->isReviewed());
    }

    /** 改回待審：reviewed → pending（會計、老闆、admin） */
    public function unreview(User $user, TaskFee $fee): bool
    {
        return $user->canReviewFee()
            && $this->canReviewInProject($user, $fee)
            && $fee->isReviewed();
    }

    /** 撤回核發：disbursed → reviewed（老闆、admin） */
    public function undisburse(User $user, TaskFee $fee): bool
    {
        return $user->canDisburseFee()
            && $this->canReviewInProject($user, $fee)
            && $fee->isDisbursed();
    }

    /** 取消退件：rejected → pending（submitter / 可審核者皆可） */
    public function resubmit(User $user, TaskFee $fee): bool
    {
        if (! $fee->isRejected()) return false;
        // submitter 自己重送，或可審核者取消退件
        return $fee->submitted_by === $user->id
            || ($user->canReviewFee() && $this->canReviewInProject($user, $fee));
    }

    /** 補件通知：可審核者 */
    public function requestReceipt(User $user, TaskFee $fee): bool
    {
        return $user->canReviewFee() && $this->canReviewInProject($user, $fee);
    }

    /** 可在此專案做審核（會計/老闆 + 同公司專案）
     *  審核者是公司層級角色，不需是專案成員；以同公司為界，
     *  與 FeeReviewController 的清單可見性（visibleProjectIds = 同公司）一致。 */
    private function canReviewInProject(User $user, TaskFee $fee): bool
    {
        if ($user->isAdmin()) return true;
        if (! $user->canReviewFee()) return false;
        return $user->company_id !== null
            && $fee->project->company_id === $user->company_id;
    }

    /** 可管理此專案（老闆/admin） */
    private function canManageProject(User $user, TaskFee $fee): bool
    {
        if ($user->isAdmin()) return true;
        return $user->canManage() && (new ProjectPolicy())->update($user, $fee->project);
    }

    private function canManageProjectForTask(User $user, Task $task): bool
    {
        if ($user->isAdmin()) return true;
        return $user->canManage() && (new ProjectPolicy())->update($user, $task->project);
    }
}
