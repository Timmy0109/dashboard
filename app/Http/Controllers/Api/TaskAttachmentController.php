<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    private const MAX_SIZE_BYTES = 52428800; // 50 MB

    // GET /projects/{project}/tasks/{task}/attachments
    public function index(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        $attachments = $task->attachments()
            ->with('uploader:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => $this->formatAttachment($a));

        return response()->json($attachments);
    }

    // POST /projects/{project}/tasks/{task}/attachments
    public function store(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50 MB in KB for Laravel validator
        ]);

        $file = $request->file('file');

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return response()->json(['message' => '檔案大小不可超過 50 MB'], 422);
        }

        $path = $file->store("attachments/task_{$task->id}", 'local');

        $attachment = $task->attachments()->create([
            'uploader_id'   => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'disk_path'     => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);

        $attachment->load('uploader:id,name');

        TaskActivity::create([
            'task_id'    => $task->id,
            'actor_id'   => $request->user()->id,
            'event'      => 'attached',
            'payload'    => ['filename' => $file->getClientOriginalName()],
            'created_at' => now(),
        ]);

        return response()->json($this->formatAttachment($attachment), 201);
    }

    // DELETE /projects/{project}/tasks/{task}/attachments/{attachment}
    public function destroy(Request $request, Project $project, Task $task, TaskAttachment $attachment): JsonResponse
    {
        $this->authorize('view', $project);

        $canDelete = $attachment->uploader_id === $request->user()->id
            || $request->user()->isAdmin()
            || $project->owner_id === $request->user()->id;

        if (! $canDelete) {
            return response()->json(['message' => '無權限刪除此附件'], 403);
        }

        $filename = $attachment->original_name;
        Storage::disk('local')->delete($attachment->disk_path);
        $attachment->delete();

        TaskActivity::create([
            'task_id'    => $task->id,
            'actor_id'   => $request->user()->id,
            'event'      => 'detached',
            'payload'    => ['filename' => $filename],
            'created_at' => now(),
        ]);

        return response()->json(null, 204);
    }

    // GET /attachments/{attachment}?signature=... (signed URL, no auth middleware)
    public function download(Request $request, TaskAttachment $attachment)
    {
        if (! $request->hasValidSignature()) {
            abort(403, '連結已失效，請重新取得');
        }

        $path = storage_path('app/private/' . $attachment->disk_path);

        if (! file_exists($path)) {
            abort(404, '檔案不存在');
        }

        $inline = in_array($attachment->mime_type, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
        ], true);

        return response()->file($path, [
            'Content-Type'        => $attachment->mime_type,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $attachment->original_name . '"',
        ]);
    }

    private function formatAttachment(TaskAttachment $a): array
    {
        return [
            'id'            => $a->id,
            'original_name' => $a->original_name,
            'mime_type'     => $a->mime_type,
            'size_human'    => $a->size_human,
            'is_previewable'=> $a->is_previewable,
            'download_url'  => $a->download_url,
            'uploader_id'   => $a->uploader_id,
            'uploader'      => $a->uploader ? ['id' => $a->uploader->id, 'name' => $a->uploader->name] : null,
            'created_at'    => $a->created_at->format('Y/m/d H:i'),
        ];
    }
}
