<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectAdminFee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'created_by',
        'amount', 'note', 'incurred_on',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'incurred_on' => 'date',
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

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAdminFeeAttachment::class);
    }
}
