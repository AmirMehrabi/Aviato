<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

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
}
