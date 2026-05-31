<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskFeeStateLog extends Model
{
    // append-only: 不用 updated_at
    public $timestamps = false;

    protected $fillable = [
        'task_fee_id', 'from_status', 'to_status', 'actor_id', 'reason', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function taskFee(): BelongsTo
    {
        return $this->belongsTo(TaskFee::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
