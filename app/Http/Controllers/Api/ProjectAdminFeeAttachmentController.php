<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectAdminFee;
use App\Models\ProjectAdminFeeAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectAdminFeeAttachmentController extends Controller
{
    private const MAX_SIZE_BYTES = 52428800; // 50 MB

    // POST /api/project-admin-fees/{fee}/attachments
    public function store(Request $request, ProjectAdminFee $fee): JsonResponse
    {
        $this->authorize('update', $fee);

        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('file');
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return response()->json(['message' => '檔案大小不可超過 50 MB'], 422);
        }

        $path = $file->store("attachments/admin_fee_{$fee->id}", 'local');

        $attachment = $fee->attachments()->create([
            'uploader_id'   => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'disk_path'     => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ]);

        $attachment->load('uploader:id,name');
        return response()->json($attachment, 201);
    }

    // DELETE /api/project-admin-fee-attachments/{attachment}
    public function destroy(Request $request, ProjectAdminFeeAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $attachment->fee);
        Storage::disk('local')->delete($attachment->disk_path);
        $attachment->delete();
        return response()->json(null, 204);
    }

    // GET /admin-fee-attachments/{attachment}?signature=... (signed URL)
    public function download(Request $request, ProjectAdminFeeAttachment $attachment)
    {
        if (! $request->hasValidSignature()) {
            abort(403, '連結已失效');
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
}
