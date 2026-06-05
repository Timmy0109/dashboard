<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class TaskFee extends Model
{
    use SoftDeletes;

    /**
     * 3 階段 fee 工作流：
     *  PENDING   待會計審核
     *  REVIEWED  會計已審核，待老闆核發
     *  DISBURSED 老闆已核發（最終）
     *  REJECTED  任一階段退件
     *
     * Transitions：
     *  pending  → reviewed   (canReviewFee:    boss)
     *  pending  → rejected   (canReviewFee)
     *  reviewed → disbursed  (canDisburseFee:  accountant；無會計的公司由 boss 兼任)
     *  reviewed → pending    (canAccessFees, 改回待審)
     *  reviewed → rejected   (canReviewFee 或 canDisburseFee)
     *  disbursed→ reviewed   (canDisburseFee, 撤回核發)
     *  rejected → pending    (submitter / canAccessFees, resubmit)
     */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_REVIEWED  = 'reviewed';
    public const STATUS_DISBURSED = 'disbursed';
    public const STATUS_REJECTED  = 'rejected';

    protected $fillable = [
        'task_id', 'project_id', 'submitted_by',
        'amount', 'note',
        'status',
        'reviewed_by', 'reviewed_at', 'reject_reason',
        'disbursed_by', 'disbursed_at',
        'unapproved_by', 'unapproved_at', 'unapprove_reason',
        'receipt_requested_at', 'receipt_requested_by', 'receipt_request_message',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'reviewed_at'          => 'datetime',
            'disbursed_at'         => 'datetime',
            'unapproved_at'        => 'datetime',
            'receipt_requested_at' => 'datetime',
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

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function unapprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unapproved_by');
    }

    public function receiptRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receipt_requested_by');
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
    public function isReviewed(): bool  { return $this->status === self::STATUS_REVIEWED; }
    public function isDisbursed(): bool { return $this->status === self::STATUS_DISBURSED; }
    public function isRejected(): bool  { return $this->status === self::STATUS_REJECTED; }

    /**
     * 集中狀態轉換：lockForUpdate → re-read → 確認合法 transition → 更新 +
     * 寫 state log，全部包在 transaction 內。
     *
     * 競態防護：兩個 manager 同時按 approve 時，後手會 re-read 後看到 status
     * 已 != PENDING，丟出 RuntimeException，避免雙重 state log + 互蓋。
     * Controller 接到後可回 409 conflict。
     */
    public function transitionTo(string $newStatus, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($newStatus, $actor, $reason) {
            /** @var self|null $fresh */
            $fresh = self::lockForUpdate()->find($this->id);
            if (! $fresh) {
                throw new \RuntimeException('Task fee not found');
            }

            $from = $fresh->status;

            // 合法 transition 守護
            $valid = match (true) {
                $from === self::STATUS_PENDING   && in_array($newStatus, [self::STATUS_REVIEWED, self::STATUS_REJECTED], true) => true,
                $from === self::STATUS_REVIEWED  && in_array($newStatus, [self::STATUS_DISBURSED, self::STATUS_REJECTED, self::STATUS_PENDING], true) => true,
                $from === self::STATUS_DISBURSED && $newStatus === self::STATUS_REVIEWED => true,
                $from === self::STATUS_REJECTED  && $newStatus === self::STATUS_PENDING  => true,
                default => false,
            };
            if (! $valid) {
                throw new \RuntimeException("Invalid transition: {$from} → {$newStatus}");
            }

            $now = now();
            // 任何 status 變更都視為「補件需求已完成 / 失效」，清掉旗標避免殘留
            $updates = [
                'status' => $newStatus,
                'receipt_requested_at'    => null,
                'receipt_requested_by'    => null,
                'receipt_request_message' => null,
            ];

            if ($newStatus === self::STATUS_REVIEWED && $from === self::STATUS_PENDING) {
                // 一階審核通過：記 reviewer + 清退件殘留
                $updates['reviewed_by']   = $actor->id;
                $updates['reviewed_at']   = $now;
                $updates['reject_reason'] = null;
            } elseif ($newStatus === self::STATUS_DISBURSED && $from === self::STATUS_REVIEWED) {
                // 二階核發：記 disburser
                $updates['disbursed_by'] = $actor->id;
                $updates['disbursed_at'] = $now;
            } elseif ($newStatus === self::STATUS_REJECTED) {
                $updates['reviewed_by']   = $actor->id;
                $updates['reviewed_at']   = $now;
                $updates['reject_reason'] = $reason;
            } elseif ($from === self::STATUS_REVIEWED && $newStatus === self::STATUS_PENDING) {
                // 會計/老闆把已審回退到 pending（改回待審）
                $updates['unapproved_by']    = $actor->id;
                $updates['unapproved_at']    = $now;
                $updates['unapprove_reason'] = $reason;
                $updates['reviewed_by']      = null;
                $updates['reviewed_at']      = null;
            } elseif ($from === self::STATUS_DISBURSED && $newStatus === self::STATUS_REVIEWED) {
                // 老闆撤回核發
                $updates['unapproved_by']    = $actor->id;
                $updates['unapproved_at']    = $now;
                $updates['unapprove_reason'] = $reason;
                $updates['disbursed_by']     = null;
                $updates['disbursed_at']     = null;
            } elseif ($from === self::STATUS_REJECTED && $newStatus === self::STATUS_PENDING) {
                // resubmit — clear stale reject metadata
                $updates['reject_reason'] = null;
                $updates['reviewed_by']   = null;
                $updates['reviewed_at']   = null;
            }

            $fresh->update($updates);

            TaskFeeStateLog::create([
                'task_fee_id' => $fresh->id,
                'from_status' => $from,
                'to_status'   => $newStatus,
                'actor_id'    => $actor->id,
                'reason'      => $reason,
                'created_at'  => $now,
            ]);

            // sync local model so controller sees fresh state
            $this->refresh();
        });
    }
}
