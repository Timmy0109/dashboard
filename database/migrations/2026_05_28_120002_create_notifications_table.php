<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (separate from Laravel's queueable Notification system).
 *
 * Notification type enum (4 variants):
 *   task_assigned       — user got assigned a task
 *   task_mentioned      — user got @mentioned in a comment
 *   task_status_changed — user's assigned task changed status
 *   task_replied        — someone replied to user's comment
 *
 * Payload is type-specific JSON; see frontend/src/types/notification.ts for shape.
 *
 * Unread count is the most frequent query (every page load).
 * Partial index on (user_id) WHERE read_at IS NULL keeps it cheap.
 * SQLite supports partial indexes; MySQL 8 does not — uses regular index there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Primary read pattern: list user's recent notifications.
            $table->index(['user_id', 'created_at']);
        });

        // Partial index for unread count. SQLite + Postgres support it natively;
        // fall back to a regular index on MySQL.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            \DB::statement('CREATE INDEX notifications_user_unread_idx ON notifications (user_id) WHERE read_at IS NULL');
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'read_at'], 'notifications_user_unread_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop the partial index by name first (some drivers need explicit handling).
        try {
            \DB::statement('DROP INDEX IF EXISTS notifications_user_unread_idx');
        } catch (\Throwable $e) {
            // ignored — drop will happen via Schema::dropIfExists below
        }
        Schema::dropIfExists('notifications');
    }
};
