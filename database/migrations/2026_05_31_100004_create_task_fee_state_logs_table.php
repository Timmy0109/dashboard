<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only 稽核軌跡：每次 task_fee 狀態轉移寫一筆
        Schema::create('task_fee_state_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_fee_id')->constrained('task_fees')->cascadeOnDelete();

            // submitted(null) → pending、pending → approved 等
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);

            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();

            // 故意只用 created_at，無 updated_at — append-only 設計
            $table->timestamp('created_at')->useCurrent();

            $table->index('task_fee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_fee_state_logs');
    }
};
