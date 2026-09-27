<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerNotificationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.customer.domain' => 'cp.localhost',
            'portals.admin.domain' => 'admin.localhost',
        ]);
    }

    public function test_customer_can_fetch_and_read_own_notifications_without_refreshing(): void
    {
        $customer = $this->fundedCustomer();
        $notificationId = $this->createNotification($customer, 'پاسخ پشتیبانی');

        $this->actingAs($customer, 'customer')
            ->getJson('https://cp.localhost/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('items.0.id', $notificationId)
            ->assertJsonPath('items.0.title', 'پاسخ پشتیبانی');

        $this->actingAs($customer, 'customer')
            ->postJson("https://cp.localhost/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJson([
                'notification_id' => $notificationId,
                'unread_count' => 0,
            ]);

        $this->assertNotNull(DB::table('notifications')->where('id', $notificationId)->value('read_at'));
    }

    public function test_customer_cannot_read_another_customers_notification(): void
    {
        $customer = $this->fundedCustomer();
        $otherCustomer = $this->fundedCustomer();
        $notificationId = $this->createNotification($otherCustomer, 'اعلان خصوصی');

        $this->actingAs($customer, 'customer')
            ->postJson("https://cp.localhost/notifications/{$notificationId}/read")
            ->assertNotFound();

        $this->assertNull(DB::table('notifications')->where('id', $notificationId)->value('read_at'));
    }

    public function test_customer_can_mark_all_own_notifications_as_read(): void
    {
        $customer = $this->fundedCustomer();
        $this->createNotification($customer, 'اعلان اول');
        $this->createNotification($customer, 'اعلان دوم');

        $this->actingAs($customer, 'customer')
            ->postJson('https://cp.localhost/notifications/mark-all-read')
            ->assertOk()
            ->assertJson(['unread_count' => 0, 'marked_count' => 2]);

        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_ticket_badge_and_action_labels_ignore_wallet_notifications(): void
    {
        $customer = $this->fundedCustomer();
        $ticketId = $this->createNotification($customer, 'پاسخ پشتیبانی');
        $walletId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $walletId,
            'type' => 'App\\Notifications\\WorkspaceWalletBalanceNotification',
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'data' => json_encode([
                'event' => 'workspace_wallet_balance',
                'title' => 'هشدار کیف پول',
                'body' => 'موجودی کم است',
                'url' => '/projects/example/enter',
            ]),
            'read_at' => null,
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);

        $this->actingAs($customer, 'customer')
            ->getJson('https://cp.localhost/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('ticket_unread_count', 1)
            ->assertJsonPath('items.0.action_label', 'مشاهده فضای کاری')
            ->assertJsonPath('items.1.action_label', 'مشاهده تیکت');

        $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('ticketUnreadCount: 1');

        $this->actingAs($customer, 'customer')
            ->postJson("https://cp.localhost/notifications/{$ticketId}/read")
            ->assertJsonPath('ticket_unread_count', 0);
        $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('ticketUnreadCount: 0');
        $this->actingAs($customer, 'customer')
            ->getJson('https://cp.localhost/notifications')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('ticket_unread_count', 0);
    }

    private function fundedCustomer(): Customer
    {
        $customer = Customer::factory()->create();
        $customer->wallet()->update(['balance' => 10_000_000]);

        return $customer;
    }

    private function createNotification(Customer $customer, string $title): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\TicketDatabaseNotification',
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'data' => json_encode([
                'title' => $title,
                'body' => 'متن اعلان',
                'url' => '/tickets/T123',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
