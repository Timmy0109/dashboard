<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskFee extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'task_id', 'project_id', 'submitted_by',
        'amount', 'note',
        'status',
        'reviewed_by', 'reviewed_at', 'reject_reason',
        'unapproved_by', 'unapproved_at', 'unapprove_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'reviewed_at'    => 'datetime',
            'unapproved_at'  => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function unapprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unapproved_by');
    }

    public function attachments(): HasMany
    {
        // 透過 task_attachments.task_fee_id 取得綁定到本費用的附件
        return $this->hasMany(TaskAttachment::class, 'task_fee_id');
    }

    public function stateLogs(): HasMany
    {
        return $this->hasMany(TaskFeeStateLog::class)->orderBy('created_at');
    }

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool  { return $this->status === self::STATUS_REJECTED; }
}
