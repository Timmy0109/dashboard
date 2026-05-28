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

/**
 * Cross-project activity event fired to a company's activity channel.
 * Subscribed by Dashboard ActivityFeed component.
 */
class ActivityRecorded implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TaskActivity $activity,
        public int $companyId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("company.{$this->companyId}.activity");
    }

    public function broadcastAs(): string
    {
        return 'ActivityRecorded';
    }

    public function broadcastWith(): array
    {
        $this->activity->loadMissing(['actor:id,name,avatar', 'task:id,name,project_id', 'task.project:id,name']);
        return [
            'item' => [
                'id'           => $this->activity->id,
                'event'        => $this->activity->event,
                'task_id'      => $this->activity->task_id,
                'task_name'    => $this->activity->task?->name,
                'project_id'   => $this->activity->task?->project_id,
                'project_name' => $this->activity->task?->project?->name,
                'payload'      => $this->activity->payload,
                'actor'        => $this->activity->actor ? [
                    'id'         => $this->activity->actor->id,
                    'name'       => $this->activity->actor->name,
                    'avatar_url' => $this->activity->actor->avatar_url,
                ] : null,
                'created_at'   => $this->activity->created_at?->toIso8601String(),
            ],
        ];
    }
}
