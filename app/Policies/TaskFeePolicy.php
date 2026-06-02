<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskFee;
use App\Models\User;

class TaskFeePolicy
{
    /** List task fees on a task. Admin/manager 看全部、member 只看自己。 */
    public function viewAny(User $user, Task $task): bool
    {
        return (new ProjectPolicy())->view($user, $task->project);
    }

    /** 個別費用：admin/manager 全看、member 只看自己提的 */
    public function view(User $user, TaskFee $fee): bool
    {
        if (! (new ProjectPolicy())->view($user, $fee->project)) return false;
        if ($user->isAdmin() || $this->isProjectManager($user, $fee)) return true;
        return $fee->submitted_by === $user->id;
    }

    /** Member assignee 可提交；manager 也可代提（管理員行為） */
    public function create(User $user, Task $task): bool
    {
        if ($user->isAdmin() || $this->isProjectManagerForTask($user, $task)) return true;
        return $user->isMember() && $task->assignee_id === $user->id;
    }

    /** 編輯：提交者本人，且狀態為 pending 或 rejected */
    public function update(User $user, TaskFee $fee): bool
    {
        if ($fee->submitted_by !== $user->id) {
            // manager 不可改別人提的金額（避免洗單）；要動就走 reject + 請對方重送
            return false;
        }
        return $fee->isPending() || $fee->isRejected();
    }

    public function delete(User $user, TaskFee $fee): bool
    {
        // 自己提的 pending/rejected 可撤回；manager 可刪任何 rejected
        if ($fee->submitted_by === $user->id) {
            return $fee->isPending() || $fee->isRejected();
        }
        return $this->isProjectManager($user, $fee) && $fee->isRejected();
    }

    /** Manager / admin 可審 */
    public function approve(User $user, TaskFee $fee): bool
    {
        return $this->isProjectManager($user, $fee) && $fee->isPending();
    }

    public function reject(User $user, TaskFee $fee): bool
    {
        return $this->isProjectManager($user, $fee) && $fee->isPending();
    }

    public function unapprove(User $user, TaskFee $fee): bool
    {
        return $this->isProjectManager($user, $fee) && $fee->isApproved();
    }

    public function resubmit(User $user, TaskFee $fee): bool
    {
        if (! $fee->isRejected()) return false;
        // submitter 自己重送，或 manager/admin 取消退件
        return $fee->submitted_by === $user->id || $this->isProjectManager($user, $fee);
    }

    public function requestReceipt(User $user, TaskFee $fee): bool
    {
        return $this->isProjectManager($user, $fee);
    }

    private function isProjectManager(User $user, TaskFee $fee): bool
    {
        if ($user->isAdmin()) return true;
        return $user->isManager() && (new ProjectPolicy())->update($user, $fee->project);
    }

    private function isProjectManagerForTask(User $user, Task $task): bool
    {
        if ($user->isAdmin()) return true;
        return $user->isManager() && (new ProjectPolicy())->update($user, $task->project);
    }
}
