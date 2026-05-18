<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureChangeLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['company_id', 'feature_key', 'old_value', 'new_value', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return [
            'old_value'  => 'boolean',
            'new_value'  => 'boolean',
            'changed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
