<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_fees', function (Blueprint $table) {
            $table->timestamp('receipt_requested_at')->nullable()->after('reject_reason');
            $table->foreignId('receipt_requested_by')->nullable()->after('receipt_requested_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_fees', function (Blueprint $table) {
            $table->dropForeign(['receipt_requested_by']);
            $table->dropColumn(['receipt_requested_at', 'receipt_requested_by']);
        });
    }
};
