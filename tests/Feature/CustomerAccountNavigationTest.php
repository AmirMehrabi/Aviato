<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Services\ProjectAccessService;
use App\Services\ResellerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_layout_exposes_account_actions_on_desktop_and_mobile(): void
    {
        $customer = Customer::factory()->create(['name' => 'مشتری آزمایشی']);

        $response = $this->actingAs($customer, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('حساب کاربری '.$customer->name, false)
            ->assertSee('aria-controls="customer-account-menu"', false)
            ->assertSee('lg:hidden', false)
            ->assertSee(route('customer.profile.show', [], false));

        $this->assertSame(2, substr_count($response->getContent(), 'خروج از حساب'));
        $this->assertSame(2, substr_count($response->getContent(), 'action="'.route('customer.logout', [], false).'"'));
    }

    public function test_customer_can_sign_out_from_account_actions(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post('https://cp.localhost/logout')
            ->assertRedirect();

        $this->assertGuest('customer');
    }

    public function test_reseller_navigation_expands_on_reseller_pages_and_marks_the_current_destination(): void
    {
        $customer = Customer::factory()->create();
        app(ResellerService::class)->enableReseller($customer, 10.00, 'auto_credit');
        $this->actingAs($customer, 'customer');

        foreach (['dashboard' => false, 'reseller/customers' => true] as $page => $expanded) {
            $response = $this->get('https://cp.localhost/'.$page)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $resellerSection = $xpath->query('//nav//details[summary/span="فروشندگی"]')->item(0);

            $this->assertNotNull($resellerSection);
            $this->assertSame($expanded, $resellerSection->hasAttribute('open'));
            $this->assertSame('/'.$page, $xpath->query('//nav//a[@aria-current="page"]')->item(0)->getAttribute('href'));
        }
    }

    public function test_member_sidebar_omits_wallet_actions_and_keeps_resource_navigation(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $member->id, 'role' => ProjectMember::ROLE_MEMBER]);

        $response = $this->actingAs($member, 'customer')
            ->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/dashboard')
            ->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//aside//a[contains(@href, "/wallet")]')->length);
        $this->assertSame(0, $xpath->query('//aside//details[summary/span="فروشندگی"]')->length);
        $this->assertSame(1, $xpath->query('//aside//a[@href="/servers"]')->length);
    }
}
