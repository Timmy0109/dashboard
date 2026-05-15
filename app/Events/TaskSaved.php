<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskSaved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Task $task) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->task->project_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'task' => [
                'id' => $this->task->id,
                'project_id' => $this->task->project_id,
                'name' => $this->task->name,
                'start_date' => $this->task->start_date->toDateString(),
                'end_date' => $this->task->end_date->toDateString(),
                'progress' => $this->task->progress,
                'is_completed' => $this->task->is_completed,
                'assignee' => $this->task->assignee ? ['id' => $this->task->assignee->id, 'name' => $this->task->assignee->name] : null,
                'status' => $this->task->status ? ['id' => $this->task->status->id, 'name' => $this->task->status->name, 'icon' => $this->task->status->icon, 'color' => $this->task->status->color] : null,
                'priority' => $this->task->priority ? ['id' => $this->task->priority->id, 'name' => $this->task->priority->name, 'color' => $this->task->priority->color] : null,
            ],
        ];
    }
}
