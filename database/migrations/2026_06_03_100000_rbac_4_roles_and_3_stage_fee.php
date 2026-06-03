<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RBAC 拆分 + Fee 三階段：
     *  - users.role:  admin / manager / member  →  admin / boss / accountant / member
     *    現有 manager 全部 mig 成 boss（語意：老闆 ⊇ 會計）。
     *  - users.job_title: 純顯示用，業助 / 美編 / PM 等職稱塞這。
     *  - task_fees.status: pending / approved / rejected
     *      → pending / reviewed / disbursed / rejected
     *    現有 approved 視為已完成 → mig 成 disbursed。
     *  - task_fee_state_logs.from_status / to_status: 同步 mig 舊資料。
     */
    public function up(): void
    {
        // 1. users.job_title
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 64)->nullable()->after('role');
        });

        // 2. backfill manager → boss
        DB::table('users')->where('role', 'manager')->update(['role' => 'boss']);

        // 3. task_fees 加 disbursed_by / disbursed_at
        Schema::table('task_fees', function (Blueprint $table) {
            $table->foreignId('disbursed_by')->nullable()->after('reject_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable()->after('disbursed_by');
        });

        // 4. backfill task_fees approved → disbursed
        //    既有 approved 在舊流程下已經是「最終核准」，視為已核發。
        //    既有 reviewed_by 變成 disbursed_by（誰按下「核准」誰是核發者）
        DB::table('task_fees')->where('status', 'approved')->update([
            'status'        => 'disbursed',
        ]);
        DB::statement("UPDATE task_fees
            SET disbursed_by = reviewed_by,
                disbursed_at = reviewed_at
            WHERE status = 'disbursed' AND disbursed_by IS NULL");

        // 4. state log 也同步（保留歷史追溯）
        DB::table('task_fee_state_logs')->where('from_status', 'approved')->update(['from_status' => 'disbursed']);
        DB::table('task_fee_state_logs')->where('to_status', 'approved')->update(['to_status' => 'disbursed']);
    }

    public function down(): void
    {
        DB::table('task_fee_state_logs')->where('to_status', 'disbursed')->update(['to_status' => 'approved']);
        DB::table('task_fee_state_logs')->where('from_status', 'disbursed')->update(['from_status' => 'approved']);
        DB::table('task_fees')->whereIn('status', ['disbursed', 'reviewed'])->update(['status' => 'approved']);
        DB::table('users')->whereIn('role', ['boss', 'accountant'])->update(['role' => 'manager']);

        Schema::table('task_fees', function (Blueprint $table) {
            $table->dropForeign(['disbursed_by']);
            $table->dropColumn(['disbursed_by', 'disbursed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
