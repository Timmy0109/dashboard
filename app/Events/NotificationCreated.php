<?php

namespace App\Events;

use App\Models\Notification;
use App\Support\Metrics;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification)
    {
        Metrics::increment('notifications_dispatched_total');
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("notifications.{$this->notification->user_id}");
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id'         => $this->notification->id,
                'user_id'    => $this->notification->user_id,
                'type'       => $this->notification->type,
                'payload'    => $this->notification->payload,
                'read_at'    => $this->notification->read_at?->toIso8601String(),
                'created_at' => $this->notification->created_at?->toIso8601String(),
            ],
        ];
    }
}
