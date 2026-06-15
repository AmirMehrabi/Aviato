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
