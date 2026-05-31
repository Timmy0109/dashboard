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

    /**
     * 集中狀態轉換：更新主表審核欄位 + 寫一筆 state log。
     * 不在這層做權限檢查（policy 在 controller 處理），也不做合法 transition 檢查
     * （controller 在呼叫前 assert）— 這層只負責「正確寫」。
     */
    public function transitionTo(string $newStatus, User $actor, ?string $reason = null): void
    {
        $from = $this->status;

        // 更新主表審核欄位（記錄「最後一次」行為）
        $updates = ['status' => $newStatus];
        $now = now();

        if ($newStatus === self::STATUS_APPROVED) {
            $updates['reviewed_by']  = $actor->id;
            $updates['reviewed_at']  = $now;
            $updates['reject_reason'] = null;
        } elseif ($newStatus === self::STATUS_REJECTED) {
            $updates['reviewed_by']   = $actor->id;
            $updates['reviewed_at']   = $now;
            $updates['reject_reason'] = $reason;
        } elseif ($from === self::STATUS_APPROVED && $newStatus === self::STATUS_PENDING) {
            // unapprove
            $updates['unapproved_by']    = $actor->id;
            $updates['unapproved_at']    = $now;
            $updates['unapprove_reason'] = $reason;
        }

        $this->update($updates);

        TaskFeeStateLog::create([
            'task_fee_id' => $this->id,
            'from_status' => $from,
            'to_status'   => $newStatus,
            'actor_id'    => $actor->id,
            'reason'      => $reason,
            'created_at'  => $now,
        ]);
    }
}
