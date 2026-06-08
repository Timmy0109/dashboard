<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 行政費審核流程：
 *  - status: pending（PM 建立待審）/ approved（已核准，計入支出）/ rejected（退件）
 *  - 既有資料預設 approved，避免影響既有預算統計
 *  - reviewed_by / reviewed_at / review_note 記錄審核軌跡
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_admin_fees', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('amount');
            $table->foreignId('reviewed_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('project_admin_fees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'reviewed_at', 'review_note']);
        });
    }
};
