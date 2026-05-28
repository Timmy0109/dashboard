<?php

namespace App\Observers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\SafeBroadcast;

/**
 * Triggers notifications for two scenarios:
 *   1. @mention in comment body → notify mentioned users
 *   2. Reply to existing comment → notify parent comment's author
 */
class TaskCommentObserver
{
    public function created(TaskComment $comment): void
    {
        $task = $comment->task;
        if (! $task) {
            return;
        }
        $actorName = User::find($comment->user_id)?->name ?? '系統';

        // Reply: notify parent comment author (if not self).
        if ($comment->parent_id) {
            $parent = TaskComment::find($comment->parent_id);
            if ($parent && $parent->user_id !== $comment->user_id) {
                $this->notify(
                    $parent->user_id,
                    'task_replied',
                    [
                        'comment_id'        => $comment->id,
                        'parent_comment_id' => $parent->id,
                        'task_id'           => $task->id,
                        'project_id'        => $task->project_id,
                        'actor_name'        => $actorName,
                        'snippet'           => mb_substr($comment->body, 0, 80),
                    ]
                );
            }
        }

        // @mention: notify each mentioned user.
        $mentionedNames = $this->extractMentions($comment->body);
        if (! empty($mentionedNames)) {
            $project = $task->project;
            $candidateIds = collect();
            if ($project) {
                $candidateIds = $project->members()->pluck('user_id')
                    ->merge([$project->owner_id])
                    ->unique();
            }

            $mentioned = User::whereIn('name', $mentionedNames)
                ->whereIn('id', $candidateIds)
                ->where('id', '!=', $comment->user_id) // don't notify self
                ->get();

            foreach ($mentioned as $u) {
                $this->notify(
                    $u->id,
                    'task_mentioned',
                    [
                        'comment_id' => $comment->id,
                        'task_id'    => $task->id,
                        'task_name'  => $task->name,
                        'project_id' => $task->project_id,
                        'actor_name' => $actorName,
                        'snippet'    => mb_substr($comment->body, 0, 80),
                    ]
                );
            }
        }
    }

    /**
     * Extract @mentioned names from comment body.
     * Pattern matches @ followed by 1-30 chars of CJK / latin / digits / underscore.
     */
    private function extractMentions(string $body): array
    {
        preg_match_all('/@([\p{Han}\w]{1,30})/u', $body, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function notify(int $userId, string $type, array $payload): void
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'payload' => $payload,
        ]);

        SafeBroadcast::dispatch(new NotificationCreated($notification));
    }
}
