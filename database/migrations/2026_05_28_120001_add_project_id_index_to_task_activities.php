<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity feed scope=project:{id} runs:
 *   SELECT * FROM task_activities
 *   WHERE task_id IN (SELECT id FROM tasks WHERE project_id = ?)
 *   ORDER BY created_at DESC LIMIT 50
 *
 * The query already covers (task_id, created_at). For scope=company we filter
 * by joining tasks → projects, so we add a covering index on (created_at desc)
 * to support cursor pagination across many task ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_activities', function (Blueprint $table) {
            $table->index(['task_id', 'created_at'], 'task_activities_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('task_activities', function (Blueprint $table) {
            $table->dropIndex('task_activities_task_created_idx');
        });
    }
};
