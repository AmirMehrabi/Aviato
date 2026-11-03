<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_workspace_sections_render_with_existing_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Admin]), 'admin');
        $customer = Customer::factory()->create(['status' => 'suspended', 'suspension_reason' => 'بررسی حساب']);
        $url = 'https://admin.localhost/customers/'.$customer->id;

        $this->get($url)->assertOk()->assertSee('نمای کلی')->assertSee('بررسی حساب')->assertDontSee('name="amount_toman"', false);
        $this->get($url.'?tab=services')->assertOk()->assertSee('ماشین‌های مجازی مشتری');
        $this->get($url.'?tab=billing')->assertOk()->assertSee('name="amount_toman"', false)->assertSee('name="tab" value="billing"', false);
        $this->get($url.'?tab=settings')->assertOk()->assertSee('کد ملی')->assertSee('محدودسازی خودکار سرویس‌ها');
        $this->get($url.'?tab=unknown')->assertOk()->assertDontSee('name="amount_toman"', false);
        $this->get($url.'?transaction_search=missing')->assertOk()->assertSee('تراکنشی با این فیلترها پیدا نشد.');
    }

    public function test_customer_reader_cannot_reveal_finances_by_selecting_billing_section(): void
    {
        $admin = User::factory()->create([
            'role' => AdminRole::Custom,
            'permissions' => AdminAccess::normalizePermissions(['customers.read']),
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('https://admin.localhost/customers/'.$customer->id.'?tab=billing')
            ->assertOk()
            ->assertDontSee('موجودی کیف پول')
            ->assertDontSee('name="amount_toman"', false)
            ->assertSee('نمای کلی');
    }
}
