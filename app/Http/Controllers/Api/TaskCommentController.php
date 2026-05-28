<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskCommentCreated;
use App\Events\TaskCommentReplied;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Support\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    // GET /projects/{project}/tasks/{task}/comments
    //
    // Returns a flat list with nested `replies` on top-level comments only
    // (1-level deep — no reply-to-reply).
    public function index(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        $all = $task->comments()
            ->with('user:id,name,avatar')
            ->orderBy('created_at')
            ->get();

        $byParent = $all->groupBy('parent_id');
        $topLevel = $byParent->get(null, collect());

        $comments = $topLevel->map(function ($c) use ($byParent) {
            $formatted = $this->formatComment($c);
            $replies = $byParent->get($c->id, collect())
                ->map(fn ($r) => $this->formatComment($r));
            $formatted['replies'] = $replies;
            return $formatted;
        });

        return response()->json($comments);
    }

    // POST /projects/{project}/tasks/{task}/comments
    public function store(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body'    => $data['body'],
        ]);

        $comment->load('user:id,name,avatar');

        TaskActivity::create([
            'task_id'    => $task->id,
            'actor_id'   => $request->user()->id,
            'event'      => 'commented',
            'payload'    => ['comment_id' => $comment->id],
            'created_at' => now(),
        ]);

        SafeBroadcast::dispatch(new TaskCommentCreated($comment));

        return response()->json($this->formatComment($comment), 201);
    }

    // POST /projects/{project}/tasks/{task}/comments/{comment}/reply
    //
    // Replies are 1 level deep. Reply-to-reply is rejected with 422.
    public function reply(Request $request, Project $project, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('view', $project);

        if ($comment->task_id !== $task->id) {
            abort(404);
        }
        if ($comment->parent_id !== null) {
            return response()->json(['message' => '不能回覆回覆，請回覆原始留言'], 422);
        }

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $reply = $task->comments()->create([
            'user_id'   => $request->user()->id,
            'parent_id' => $comment->id,
            'body'      => $data['body'],
        ]);

        $reply->load('user:id,name,avatar');

        TaskActivity::create([
            'task_id'    => $task->id,
            'actor_id'   => $request->user()->id,
            'event'      => 'commented',
            'payload'    => ['comment_id' => $reply->id, 'parent_id' => $comment->id],
            'created_at' => now(),
        ]);

        SafeBroadcast::dispatch(new TaskCommentReplied($reply));

        return response()->json($this->formatComment($reply), 201);
    }

    // DELETE /projects/{project}/tasks/{task}/comments/{comment}
    public function destroy(Request $request, Project $project, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('view', $project);

        if ($comment->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => '只能刪除自己的留言'], 403);
        }

        $comment->delete();

        return response()->json(null, 204);
    }

    private function formatComment(TaskComment $c): array
    {
        return [
            'id'         => $c->id,
            'parent_id'  => $c->parent_id,
            'body'       => $c->body,
            'created_at' => $c->created_at->format('Y/m/d H:i'),
            'user'       => [
                'id'         => $c->user->id,
                'name'       => $c->user->name,
                'avatar_url' => $c->user->avatar_url,
            ],
        ];
    }
}
