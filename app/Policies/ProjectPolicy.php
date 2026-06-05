<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($project->owner_id === $user->id) {
            return true;
        }

        // 同公司（company_id 相同且非 null）且參與費用流程/可管理者，
        // 取得「唯讀」檢視權：會計與同公司非 owner 老闆可看公司專案。
        // 一般 member 仍須是專案成員。寫入權（update/delete）不放寬。
        if (
            $user->company_id !== null
            && $user->company_id === $project->company_id
            && ($user->canAccessFees() || $user->canManage())
        ) {
            return true;
        }

        return $this->member($user, $project);
    }

    /**
     * 「參與」資格（admin / owner / 專案成員）。
     * 這是 view 放寬前的原始語意，用於需實際參與才能執行的協作寫入
     * （留言、上傳附件等）。會計／同公司非 owner 老闆的公司範圍唯讀
     * **不**授予此資格，避免寫入權外溢。
     */
    public function member(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($project->owner_id === $user->id) {
            return true;
        }

        return $project->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $project->owner_id === $user->id;
    }
}
