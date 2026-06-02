<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class ProjectAdminFeeAttachment extends Model
{
    protected $fillable = [
        'project_admin_fee_id', 'uploader_id',
        'original_name', 'disk_path', 'mime_type', 'size',
    ];

    protected $appends = ['download_url', 'size_human', 'is_previewable'];

    public function fee(): BelongsTo
    {
        return $this->belongsTo(ProjectAdminFee::class, 'project_admin_fee_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function getDownloadUrlAttribute(): string
    {
        return URL::signedRoute(
            'admin-fee-attachments.download',
            ['attachment' => $this->id],
            now()->addMinutes(60),
        );
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
