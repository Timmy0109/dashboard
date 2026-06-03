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

    public function isAccountant(): bool
    {
        return $this->role === self::ROLE_ACCOUNTANT;
    }

    public function isMember(): bool
    {
        return $this->role === self::ROLE_MEMBER;
    }

    /**
     * 階層：admin ⊇ boss ⊇ accountant ⊇ (fee 一階審核權限)
     *      admin ⊇ boss              ⊇ (fee 二階核發權限)
     *      admin ⊇ boss              ⊇ (manage 公司、行政費用…)
     */

    /** 可一階審核費用：pending → reviewed */
    public function canReviewFee(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_BOSS, self::ROLE_ACCOUNTANT], true);
    }

    /** 可二階核發費用：reviewed → disbursed */
    public function canDisburseFee(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_BOSS], true);
    }

    /** 可管理公司 / 專案 / 行政費用 / 成員 */
    public function canManage(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_BOSS], true);
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
