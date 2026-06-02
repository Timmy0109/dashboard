<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_admin_fee_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_admin_fee_id')
                ->constrained('project_admin_fees')
                ->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();

            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');

            $table->timestamps();

            $table->index('project_admin_fee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_admin_fee_attachments');
    }
};
