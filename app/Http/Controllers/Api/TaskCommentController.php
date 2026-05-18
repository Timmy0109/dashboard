<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    // GET /projects/{project}/tasks/{task}/comments
    public function index(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        $comments = $task->comments()
            ->with('user:id,name,avatar')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($c) => $this->formatComment($c));

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

        return response()->json($this->formatComment($comment), 201);
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
