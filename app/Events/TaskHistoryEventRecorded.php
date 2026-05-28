<?php

namespace App\Events;

use App\Models\TaskActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskHistoryEventRecorded implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskActivity $activity) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("task.{$this->activity->task_id}");
    }

    public function broadcastAs(): string
    {
        return 'HistoryEventRecorded';
    }

    public function broadcastWith(): array
    {
        $this->activity->loadMissing('actor:id,name,avatar');
        return [
            'event' => [
                'id'         => $this->activity->id,
                'task_id'    => $this->activity->task_id,
                'event'      => $this->activity->event,
                'payload'    => $this->activity->payload,
                'actor'      => $this->activity->actor ? [
                    'id'         => $this->activity->actor->id,
                    'name'       => $this->activity->actor->name,
                    'avatar_url' => $this->activity->actor->avatar_url,
                ] : null,
                'created_at' => $this->activity->created_at?->toIso8601String(),
            ],
        ];
    }
}
