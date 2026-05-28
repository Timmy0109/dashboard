<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nested-reply support (1 level deep) to task_comments.
 *
 * Reply policy: when a parent comment is deleted, all its replies cascade-delete
 * (semantically the conversation thread is also gone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('task_comments')
                ->cascadeOnDelete();

            // Lookups by parent are frequent (loading thread replies).
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
