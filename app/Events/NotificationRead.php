<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cross-tab read-state sync. Fired when a user marks a notification as read in
 * one tab; other tabs of the same user receive this and update their UI count.
 */
class NotificationRead implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $userId       owner of the channel
     * @param  int[]|null  $notificationIds  null = mark-all-read
     */
    public function __construct(
        public int $userId,
        public ?array $notificationIds,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("notifications.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'NotificationRead';
    }

    public function broadcastWith(): array
    {
        return [
            'notification_ids' => $this->notificationIds,
            'all'              => $this->notificationIds === null,
        ];
    }
}
