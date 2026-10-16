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

    public function test_main_sidebar_keeps_daily_destinations_visible_and_moves_tools_to_secondary_panels(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        $response = $this->get('https://admin.localhost/profile')->assertOk()
            ->assertSee('data-initial-panel="main"', false);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(12, $xpath->query('//*[@id="admin-nav-main"]//a')->length);
        $this->assertSame(5, $xpath->query('//*[@id="admin-nav-main"]//button[@aria-controls]')->length);
        $main = $document->saveHTML($document->getElementById('admin-nav-main'));
        foreach (['admin.customers.index', 'admin.projects.index', 'admin.resellers.index',
            'admin.virtual-machines.index', 'admin.tickets.index', 'admin.incidents.index',
            'admin.billing.overview', 'admin.billing.invoices.index', 'admin.billing.payments.index',
            'admin.billing.wallets.index', 'admin.billing.transactions.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $main);
        }
        foreach (['admin.proxmox-servers.index', 'admin.billing.rates.index', 'admin.users.index'] as $route) {
            $this->assertStringNotContainsString('href="'.route($route).'"', $main);
        }
        foreach (['infrastructure' => 6, 'support-settings' => 2, 'billing-tools' => 2, 'sales' => 3, 'administration' => 4] as $panel => $count) {
            $this->assertSame($count, $xpath->query('//*[@id="admin-nav-'.$panel.'"]//a')->length);
            $this->assertStringContainsString('منوی اصلی', $document->saveHTML($document->getElementById('admin-nav-'.$panel)));
        }
    }

    public function test_secondary_destination_opens_its_panel_and_highlights_the_current_page(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        $response = $this->get('https://admin.localhost/billing/bundles')->assertOk()
            ->assertSee('data-initial-panel="sales"', false)
            ->assertSee('background-color: #073C8C', false);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $active = $xpath->query('//*[@id="admin-nav-sales"]//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame(route('admin.billing.bundles.index'), $active->item(0)->getAttribute('href'));
        $this->assertStringNotContainsString('adminNavGroup', $response->getContent());
    }

    public function test_page_search_supports_english_and_persian_navigation_terms(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');

        foreach (['pricing', 'قیمت', 'تعرفه'] as $query) {
            $this->getJson('https://admin.localhost/search?q='.urlencode($query))
                ->assertOk()
                ->assertJsonPath('groups.0.label', 'صفحات مدیریت')
                ->assertJsonPath('groups.0.items.0.title', 'قیمت منابع')
                ->assertJsonPath('groups.0.items.0.subtitle', 'طرح‌ها و پروموشن‌ها')
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
            ->assertDontSee('id="admin-nav-finance"', false)
            ->assertDontSee('id="admin-nav-sales"', false)
            ->assertDontSee('تنظیمات پشتیبانی');
        $this->getJson('https://admin.localhost/search?q=pricing')->assertExactJson(['groups' => []]);
        $this->getJson('https://admin.localhost/search?q=tickets')
            ->assertJsonPath('groups.0.items.0.title', 'تیکت‌ها');
    }

    public function test_custom_admin_with_only_pricing_access_gets_only_the_sales_panel(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => AdminRole::Custom,
            'permissions' => ['pricing.manage'],
        ]), 'admin');

        $this->get('https://admin.localhost/billing/rates')->assertOk()
            ->assertSee('data-initial-panel="sales"', false)
            ->assertSee('aria-controls="admin-nav-sales"', false)
            ->assertDontSee('id="admin-nav-infrastructure"', false)
            ->assertDontSee('id="admin-nav-administration"', false)
            ->assertDontSee('href="'.route('admin.billing.bundles.index').'"', false);
        $this->getJson('https://admin.localhost/search?q=pricing')
            ->assertJsonPath('groups.0.items.0.url', route('admin.billing.rates.index'));
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
