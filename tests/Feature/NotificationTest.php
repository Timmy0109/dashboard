<?php

namespace Tests\Feature;

use App\Events\NotificationRead;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $this->bob   = User::factory()->create(['role' => 'member', 'status' => 'active']);
    }

    public function test_index_returns_users_notifications_only(): void
    {
        Notification::create([
            'user_id' => $this->alice->id,
            'type'    => 'task_assigned',
            'payload' => ['task_id' => 1, 'task_name' => 'A'],
        ]);
        Notification::create([
            'user_id' => $this->bob->id,
            'type'    => 'task_assigned',
            'payload' => ['task_id' => 2, 'task_name' => 'B'],
        ]);

        $this->actingAs($this->alice)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['type' => 'task_assigned'])
            ->assertJsonPath('unread_count', 1);
    }

    public function test_index_empty(): void
    {
        $this->actingAs($this->alice)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_index_unread_filter(): void
    {
        $read = Notification::create([
            'user_id' => $this->alice->id,
            'type'    => 'task_assigned',
            'payload' => [],
            'read_at' => now(),
        ]);
        $unread = Notification::create([
            'user_id' => $this->alice->id,
            'type'    => 'task_assigned',
            'payload' => [],
        ]);

        $this->actingAs($this->alice)
            ->getJson('/api/notifications?filter=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $unread->id]);
    }

    public function test_mark_read_updates_and_broadcasts(): void
    {
        Event::fake([NotificationRead::class]);

        $n = Notification::create([
            'user_id' => $this->alice->id,
            'type'    => 'task_assigned',
            'payload' => [],
        ]);

        $this->actingAs($this->alice)
            ->postJson("/api/notifications/{$n->id}/read")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($n->fresh()->read_at);
        Event::assertDispatched(NotificationRead::class);
    }

    public function test_mark_read_other_users_notification_returns_404(): void
    {
        $bobNotif = Notification::create([
            'user_id' => $this->bob->id,
            'type'    => 'task_assigned',
            'payload' => [],
        ]);

        $this->actingAs($this->alice)
            ->postJson("/api/notifications/{$bobNotif->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_read_updates_all_unread(): void
    {
        Event::fake([NotificationRead::class]);

        Notification::create(['user_id' => $this->alice->id, 'type' => 'task_assigned', 'payload' => []]);
        Notification::create(['user_id' => $this->alice->id, 'type' => 'task_mentioned', 'payload' => []]);
        Notification::create(['user_id' => $this->alice->id, 'type' => 'task_replied', 'payload' => [], 'read_at' => now()]);

        $this->actingAs($this->alice)
            ->postJson('/api/notifications/mark-all-read')
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 2]);

        $this->assertEquals(0, Notification::where('user_id', $this->alice->id)->whereNull('read_at')->count());
        Event::assertDispatched(NotificationRead::class);
    }
}
