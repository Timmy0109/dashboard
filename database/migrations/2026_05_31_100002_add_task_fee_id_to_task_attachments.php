<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            // nullable：保留純 task-level attachment 的能力；非 null 表示綁定到特定 task_fee
            $table->foreignId('task_fee_id')
                ->nullable()
                ->after('uploader_id')
                ->constrained('task_fees')
                ->nullOnDelete();

            $table->index('task_fee_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropForeign(['task_fee_id']);
            $table->dropIndex(['task_fee_id']);
            $table->dropColumn('task_fee_id');
        });
    }
};
