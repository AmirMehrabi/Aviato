<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_groups_daily_work_and_highlights_the_current_page(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        $response = $this->get('https://admin.localhost/billing/bundles')->assertOk();
        $html = $response->getContent();
        $start = strpos($html, '<nav ');
        $sidebar = substr($html, $start, strpos($html, '</nav>', $start) - $start);
        $this->assertStringContainsString('مشتریان و همکاران', $sidebar);
        $this->assertStringContainsString('طرح‌ها و فروش', $sidebar);
        $this->assertStringContainsString('تنظیمات پشتیبانی', $sidebar);
        $this->assertStringContainsString('aria-controls="admin-nav-sales"', $sidebar);
        $this->assertMatchesRegularExpression('/href="[^"]*billing\/bundles"\s+@?[^>]*aria-current="page"/u', $sidebar);
        $this->assertLessThan(strpos($sidebar, 'سرورهای Proxmox'), strpos($sidebar, 'ماشین‌های مجازی'));
    }

    public function test_page_search_supports_english_and_persian_navigation_terms(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        foreach (['pricing', 'قیمت', 'تعرفه'] as $query) {
            $this->getJson('https://admin.localhost/search?q='.urlencode($query))
                ->assertOk()
                ->assertJsonPath('groups.0.label', 'صفحات مدیریت')
                ->assertJsonPath('groups.0.items.0.title', 'قیمت منابع')
                ->assertJsonPath('groups.0.items.0.subtitle', 'طرح‌ها و فروش')
                ->assertJsonPath('groups.0.items.0.url', route('admin.billing.rates.index'));
        }
    }

    public function test_restricted_admin_cannot_discover_unauthorized_pages_or_empty_groups(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => AdminRole::Custom,
            'permissions' => ['tickets.read'],
        ]), 'admin');

        $this->get('https://admin.localhost/profile')->assertOk()
            ->assertSee('پشتیبانی و رخدادها')
            ->assertDontSee('aria-controls="admin-nav-finance"', false)
            ->assertDontSee('aria-controls="admin-nav-sales"', false)
            ->assertDontSee('تنظیمات پشتیبانی');
        $this->getJson('https://admin.localhost/search?q=pricing')->assertExactJson(['groups' => []]);
        $this->getJson('https://admin.localhost/search?q=tickets')
            ->assertJsonPath('groups.0.items.0.title', 'تیکت‌ها');
    }

    public function test_ticket_badge_counts_only_tickets_awaiting_admin_response(): void
    {
        $customer = Customer::factory()->create();
        foreach ([Ticket::STATUS_OPEN, Ticket::STATUS_PENDING, Ticket::STATUS_ANSWERED, Ticket::STATUS_CLOSED] as $status) {
            Ticket::create([
                'number' => 'NAV-'.$status, 'customer_id' => $customer->id,
                'subject' => 'Navigation badge', 'status' => $status, 'priority' => Ticket::PRIORITY_NORMAL,
            ]);
        }
        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]), 'admin');
        $this->get('https://admin.localhost/profile')->assertOk()
            ->assertSee('aria-label="1 تیکت در انتظار پاسخ مدیر"', false);
        $this->actingAs(User::factory()->create(['role' => AdminRole::Accountant]), 'admin');
        $this->get('https://admin.localhost/profile')->assertOk()
            ->assertDontSee('تیکت در انتظار پاسخ مدیر');
    }
}
