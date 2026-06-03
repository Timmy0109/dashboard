<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBudgetLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id', 'actor_id', 'from_amount', 'to_amount', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_amount' => 'decimal:2',
            'to_amount'   => 'decimal:2',
            'created_at'  => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
