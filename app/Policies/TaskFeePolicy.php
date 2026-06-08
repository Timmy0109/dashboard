<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskFee;
use App\Models\User;

class TaskFeePolicy
{
    /** 三階段流程：
     *  pending → reviewed (一階審核：boss)
     *  reviewed → disbursed (二階核發：accountant；無會計的公司由 boss 兼任)
     *  rejected ⇄ pending
     */

    /** admin 只管系統層面，完全不參與費用流程（看 / 審核 / 核發 / 管理皆拒絕） */
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? false : null;
    }

    public function viewAny(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->view($user, $task->project);
    }

    public function view(User $user, TaskFee $fee): bool
    {
        if (! (new ProjectPolicy())->view($user, $fee->project)) return false;
        if ($this->inFeeCompanyScope($user, $fee)) return true;
        return $fee->submitted_by === $user->id;
    }

    public function create(User $user, Task $task): bool
    {
        if ($this->canManageProjectForTask($user, $task)) return true;
        // 被指派者本人可提交（member / 專案經理 / 會計）
        return ($user->isMember() || $user->isManager() || $user->isAccountant())
            && $task->assignee_id === $user->id;
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

    /** 一階審核：pending → reviewed（老闆） */
    public function review(User $user, TaskFee $fee): bool
    {
        return $user->canReviewFee()
            && $this->inFeeCompanyScope($user, $fee)
            && $fee->isPending();
    }

    /** 二階核發：reviewed → disbursed（會計；無會計的公司由老闆兼任） */
    public function disburse(User $user, TaskFee $fee): bool
    {
        return $user->canDisburseFee()
            && $this->inFeeCompanyScope($user, $fee)
            && $fee->isReviewed();
    }

    /** 退件：pending 由一階（老闆）退；reviewed 一階或二階（會計出帳前發現問題）皆可退 */
    public function reject(User $user, TaskFee $fee): bool
    {
        if (! $this->inFeeCompanyScope($user, $fee)) return false;
        if ($fee->isPending())  return $user->canReviewFee();
        if ($fee->isReviewed()) return $user->canReviewFee() || $user->canDisburseFee();
        return false;
    }

    /** 改回待審：reviewed → pending（老闆自我修正，或會計退回一階重審） */
    public function unreview(User $user, TaskFee $fee): bool
    {
        return $this->inFeeCompanyScope($user, $fee)
            && $fee->isReviewed();
    }

    /** 撤回核發：disbursed → reviewed（會計） */
    public function undisburse(User $user, TaskFee $fee): bool
    {
        return $user->canDisburseFee()
            && $this->inFeeCompanyScope($user, $fee)
            && $fee->isDisbursed();
    }

    /** 取消退件：rejected → pending（submitter / 費用流程參與者皆可） */
    public function resubmit(User $user, TaskFee $fee): bool
    {
        if (! $fee->isRejected()) return false;
        // submitter 自己重送，或費用流程參與者取消退件
        return $fee->submitted_by === $user->id
            || $this->inFeeCompanyScope($user, $fee);
    }

    /** 補件通知：一階（審核）與二階（出帳）皆可能要求單據 */
    public function requestReceipt(User $user, TaskFee $fee): bool
    {
        return $this->inFeeCompanyScope($user, $fee);
    }

    /** 在此費用的公司範圍內且參與費用流程（一階老闆 / 二階會計）
     *  費用相關人員是公司層級角色，不需是專案成員；以同公司為界，
     *  與 FeeReviewController 的清單可見性（visibleProjectIds = 同公司）一致。 */
    private function inFeeCompanyScope(User $user, TaskFee $fee): bool
    {
        if (! $user->canAccessFees()) return false;
        return $user->company_id !== null
            && $fee->project->company_id === $user->company_id;
    }

    /** 可管理此專案（老闆，或專案經理且為 owner；admin 已被 before() 排除） */
    private function canManageProject(User $user, TaskFee $fee): bool
    {
        return $user->canCreateProjects() && (new ProjectPolicy())->update($user, $fee->project);
    }

    private function canManageProjectForTask(User $user, Task $task): bool
    {
        return $user->canCreateProjects() && (new ProjectPolicy())->update($user, $task->project);
    }
}
