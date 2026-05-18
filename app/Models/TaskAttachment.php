<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class TaskAttachment extends Model
{
    protected $fillable = ['task_id', 'uploader_id', 'original_name', 'disk_path', 'mime_type', 'size'];

    protected $appends = ['download_url', 'size_human', 'is_previewable'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getDownloadUrlAttribute(): string
    {
        return URL::signedRoute('attachments.download', ['attachment' => $this->id], now()->addMinutes(60));
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function getIsPreviewableAttribute(): bool
    {
        return in_array($this->mime_type, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
        ], true);
    }
}
