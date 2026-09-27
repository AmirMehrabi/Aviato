<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_separates_sales_requests_from_customer_support(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('درخواست مشاوره خرید')
            ->assertSee('پشتیبانی مشتریان')
            ->assertSee(route('customer.tickets.create'), false)
            ->assertSee('نوع درخواست را انتخاب کنید')
            ->assertSee('mailto:admin@aviato.ir', false)
            ->assertSee('tel:+983491097953', false);
    }

    public function test_guest_can_submit_contact_form(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Amir Rezaei',
            'email' => 'amir@example.com',
            'phone' => '09123456789',
            'need_type' => 'cloud-vps',
            'team_size' => '1-5',
            'message' => 'We need a VPS for a production Laravel application.',
        ]);

        $response->assertRedirect('/contact');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Amir Rezaei',
            'email' => 'amir@example.com',
            'phone' => '09123456789',
            'need_type' => 'cloud-vps',
            'team_size' => '1-5',
            'status' => 'new',
        ]);
    }

    public function test_contact_form_requires_valid_data(): void
    {
        $this->post('/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'need_type' => 'invalid',
            'team_size' => 'invalid',
            'message' => 'short',
        ])->assertSessionHasErrors(['name', 'email', 'need_type', 'team_size', 'message']);
    }

    public function test_guest_can_submit_without_team_size(): void
    {
        $this->post('/contact', [
            'name' => 'Amir Rezaei',
            'email' => 'amir@example.com',
            'need_type' => 'migration',
            'message' => 'We need help planning a migration to Aviato.',
        ])->assertRedirect('/contact');

        $this->assertDatabaseHas('contact_submissions', [
            'email' => 'amir@example.com',
            'team_size' => null,
            'status' => 'new',
        ]);
    }
}
