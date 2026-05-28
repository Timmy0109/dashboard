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

class TaskCommentCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TaskComment $comment) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("task.{$this->comment->task_id}");
    }

    public function broadcastAs(): string
    {
        return 'CommentCreated';
    }

    public function broadcastWith(): array
    {
        $this->comment->loadMissing('user:id,name,avatar');
        return [
            'comment' => [
                'id'        => $this->comment->id,
                'task_id'   => $this->comment->task_id,
                'parent_id' => $this->comment->parent_id,
                'user_id'   => $this->comment->user_id,
                'body'      => $this->comment->body,
                'user'      => $this->comment->user ? [
                    'id'         => $this->comment->user->id,
                    'name'       => $this->comment->user->name,
                    'avatar_url' => $this->comment->user->avatar_url,
                ] : null,
                'created_at' => $this->comment->created_at?->toIso8601String(),
            ],
        ];
    }
}
