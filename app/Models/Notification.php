<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app notification. Distinct from Laravel's built-in Notification framework —
 * this is a domain object with typed payload, surfaced in NotificationBell drop-down.
 */
class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'payload', 'read_at'];

    public const TYPES = [
        'task_assigned',
        'task_mentioned',
        'task_status_changed',
        'task_replied',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
