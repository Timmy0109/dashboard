<?php

namespace App\Events;

use App\Models\TaskComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentReplied implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskComment $reply) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("task.{$this->reply->task_id}");
    }

    public function broadcastAs(): string
    {
        return 'CommentReplied';
    }

    public function broadcastWith(): array
    {
        $this->reply->loadMissing('user:id,name,avatar');
        return [
            'reply' => [
                'id'        => $this->reply->id,
                'task_id'   => $this->reply->task_id,
                'parent_id' => $this->reply->parent_id,
                'user_id'   => $this->reply->user_id,
                'body'      => $this->reply->body,
                'user'      => $this->reply->user ? [
                    'id'         => $this->reply->user->id,
                    'name'       => $this->reply->user->name,
                    'avatar_url' => $this->reply->user->avatar_url,
                ] : null,
                'created_at' => $this->reply->created_at?->toIso8601String(),
            ],
        ];
    }
}
