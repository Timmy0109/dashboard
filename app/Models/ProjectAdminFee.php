<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectAdminFee extends Model
{
    use SoftDeletes;

    /** PM 建立待會計審核 */
    public const STATUS_PENDING  = 'pending';
    /** 已核准（boss 建立免審 → 直接核准；或審核者核准）→ 計入專案支出 */
    public const STATUS_APPROVED = 'approved';
    /** 審核退件 */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'project_id', 'created_by',
        'amount', 'note', 'incurred_on',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'incurred_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAdminFeeAttachment::class);
    }
}
