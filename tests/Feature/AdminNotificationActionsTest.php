<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['portals.admin.domain' => 'admin.localhost']);
    }

    public function test_admin_can_fetch_latest_notifications(): void
    {
        $admin = User::factory()->create();
        $notificationId = $this->createNotification($admin->id, 'Latest notification');

        $this->actingAs($admin, 'admin')
            ->getJson('https://admin.localhost/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.id', $notificationId)
            ->assertJsonPath('items.0.title', 'Latest notification');
    }

    public function test_admin_can_mark_all_notifications_as_read_without_refreshing(): void
    {
        $admin = User::factory()->create();
        $first = $this->createNotification($admin->id, 'First notification');
        $second = $this->createNotification($admin->id, 'Second notification');

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.notifications.mark-all-read'))
            ->assertOk()
            ->assertJson([
                'unread_count' => 0,
                'marked_count' => 2,
            ]);

        $this->assertNotNull($this->notificationReadAt($first));
        $this->assertNotNull($this->notificationReadAt($second));
    }

    public function test_admin_can_mark_single_notification_as_read_without_refreshing(): void
    {
        $admin = User::factory()->create();
        $notificationId = $this->createNotification($admin->id, 'Single notification');
        $otherNotificationId = $this->createNotification($admin->id, 'Other notification');

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.notifications.read', $notificationId))
            ->assertOk()
            ->assertJson([
                'notification_id' => $notificationId,
                'unread_count' => 1,
            ]);

        $this->assertNotNull($this->notificationReadAt($notificationId));
        $this->assertNull($this->notificationReadAt($otherNotificationId));
    }

    public function test_admin_can_delete_a_notification_without_affecting_other_admins(): void
    {
        $admin = User::factory()->create();
        $own = $this->createNotification($admin->id, 'Dismiss me');
        $other = $this->createNotification(User::factory()->create()->id, 'Private notification');
        $this->actingAs($admin, 'admin')->deleteJson(route('admin.notifications.dismiss', $other))->assertNotFound();
        $this->deleteJson(route('admin.notifications.dismiss', $own))->assertOk()->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'items');
        $this->assertDatabaseMissing('notifications', ['id' => $own]);
        $this->assertDatabaseHas('notifications', ['id' => $other]);
    }

    public function test_clear_read_removes_only_current_admin_read_notifications(): void
    {
        $admin = User::factory()->create();
        $read = $this->createNotification($admin->id, 'Read', true);
        $unread = $this->createNotification($admin->id, 'Unread');
        $otherRead = $this->createNotification(User::factory()->create()->id, 'Other read', true);
        $this->actingAs($admin, 'admin')->deleteJson(route('admin.notifications.clear-read'))->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('items.0.id', $unread);
        $this->assertDatabaseMissing('notifications', ['id' => $read]);
        $this->assertDatabaseHas('notifications', ['id' => $otherRead]);
    }

    private function createNotification(int $adminId, string $title, bool $read = false): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\TicketDatabaseNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $adminId,
            'data' => json_encode([
                'title' => $title,
                'body' => 'Body',
                'url' => '/admin/tickets',
            ]),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function notificationReadAt(string $notificationId): mixed
    {
        return DB::table('notifications')->where('id', $notificationId)->value('read_at');
    }
}
