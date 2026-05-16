<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index('owner_id');
            $table->index('status_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('assignee_id');
            $table->index(['project_id', 'is_completed', 'end_date']);
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropIndex(['status_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['assignee_id']);
            $table->dropIndex(['project_id', 'is_completed', 'end_date']);
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
