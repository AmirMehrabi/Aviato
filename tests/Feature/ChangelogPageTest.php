<?php

namespace Tests\Feature;

use App\Models\Customer;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    public function test_guest_can_view_the_changelog_page(): void
    {
        $this->get('/changelog')
            ->assertOk()
            ->assertSee('نسخه 0.8.5')
            ->assertSee('نسخه 0.8.6')
            ->assertSee('فقط مهمان‌ها');
    }

    public function test_authenticated_customer_is_redirected_away_from_the_changelog_page(): void
    {
        $customer = new Customer();

        $this->actingAs($customer, 'customer')
            ->get('/changelog')
            ->assertRedirectToRoute('dashboard');
    }
}
