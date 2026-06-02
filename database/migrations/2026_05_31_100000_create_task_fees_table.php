<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            // denormalize 加速 project-level 彙總（避免 task→task_fees 兩段 join）
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();

            // pending / approved / rejected
            $table->string('status', 20)->default('pending');

            // 審核軌跡（最後一次行為）
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();

            // 反悔軌跡（最後一次反悔）
            $table->foreignId('unapproved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unapproved_at')->nullable();
            $table->text('unapprove_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // 熱查詢：project-level approved 加總
            $table->index(['project_id', 'status']);
            $table->index('task_id');
            $table->index('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_fees');
    }
};
