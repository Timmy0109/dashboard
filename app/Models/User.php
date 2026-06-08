<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Role enum: admin / boss / accountant / member
     *  - admin     系統管理員：所有權限
     *  - boss      老闆：⊇ accountant 全部 + 核發 + 一階審核
     *  - accountant 會計：一階審核（pending → reviewed）
     *  - member    成員：提交費用
     * 「業助/美編/PM/出納」等職稱歸 job_title 純顯示。
     */
    public const ROLE_ADMIN      = 'admin';
    public const ROLE_BOSS       = 'boss';
    public const ROLE_MANAGER    = 'manager';   // 專案經理：管自己的專案 + 做任務 + 提費用；不參與費用審核
    public const ROLE_ACCOUNTANT = 'accountant';
    public const ROLE_MEMBER     = 'member';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'job_title', 'status', 'last_login_at',
        'company_id', 'invited_by', 'avatar', 'suspended_by_company_delete',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isBoss(): bool
    {
        return $this->role === self::ROLE_BOSS;
    }

    /** 專案經理（manager）：管自己的專案、做任務、提費用；不參與費用審核/核發 */
    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isAccountant(): bool
    {
        return $this->role === self::ROLE_ACCOUNTANT;
    }

    public function isMember(): bool
    {
        return $this->role === self::ROLE_MEMBER;
    }

    /**
     * 費用流程權責（admin 不參與費用）：
     *   一階審核（核准支出）  = boss 專有
     *   二階核發（執行出帳）  = accountant 專有；
     *     過渡規則：公司無在職會計時由 boss 兼任核發
     *     （多重角色系統上線後改為 boss 加掛 accountant 角色，移除此特例）
     */

    /** 可一階審核費用：pending → reviewed（僅老闆） */
    public function canReviewFee(): bool
    {
        return $this->role === self::ROLE_BOSS;
    }

    /** 可二階核發費用：reviewed → disbursed（僅會計；無會計的公司由老闆兼任） */
    public function canDisburseFee(): bool
    {
        if ($this->role === self::ROLE_ACCOUNTANT) {
            return true;
        }

        return $this->role === self::ROLE_BOSS && ! $this->companyHasAccountant();
    }

    /** 參與費用流程（一階或二階）：費用審核頁入口與公司範圍唯讀的依據 */
    public function canAccessFees(): bool
    {
        return $this->canReviewFee() || $this->canDisburseFee();
    }

    /** 公司是否有在職會計（過渡規則用） */
    private function companyHasAccountant(): bool
    {
        return $this->company_id !== null
            && static::where('company_id', $this->company_id)
                ->where('role', self::ROLE_ACCOUNTANT)
                ->where('status', 'active')
                ->exists();
    }

    /** 可管理公司 / 專案 / 行政費用 / 成員 */
    public function canManage(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_BOSS], true);
    }

    /** 可建立專案：admin / 老闆（全公司）、專案經理（自己的，owner 限本人） */
    public function canCreateProjects(): bool
    {
        return $this->canManage() || $this->isManager();
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members', 'user_id', 'project_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }
}
