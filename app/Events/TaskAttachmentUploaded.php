<?php

namespace App\Events;

use App\Models\TaskAttachment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAttachmentUploaded implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskAttachment $attachment) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("task.{$this->attachment->task_id}");
    }

    public function broadcastAs(): string
    {
        return 'AttachmentUploaded';
    }

    public function broadcastWith(): array
    {
        $this->attachment->loadMissing('uploader:id,name');
        return [
            'attachment' => [
                'id'            => $this->attachment->id,
                'task_id'       => $this->attachment->task_id,
                'original_name' => $this->attachment->original_name,
                'mime_type'     => $this->attachment->mime_type,
                'size_human'    => $this->attachment->size_human,
                'uploader_id'   => $this->attachment->uploader_id,
                'uploader'      => $this->attachment->uploader ? [
                    'id'   => $this->attachment->uploader->id,
                    'name' => $this->attachment->uploader->name,
                ] : null,
                'created_at' => $this->attachment->created_at?->toIso8601String(),
            ],
        ];
    }
}
