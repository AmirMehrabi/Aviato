<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HesabroCallbackCaptureTest extends TestCase
{
    public function test_generic_callback_displays_the_current_get_and_post_payload(): void
    {
        $url = '/api/v1/hesabro/wallet/callback';
        $this->get($url.'?status=success&reference_id=123')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('query.reference_id', '123');
        $this->postJson($url, ['status' => 'success', 'transaction_id' => '456'])
            ->assertOk()
            ->assertJsonPath('body.transaction_id', '456')
            ->assertJsonPath('raw_body', '{"status":"success","transaction_id":"456"}');
    }

    public function test_capture_records_get_and_post_without_exposing_saved_data(): void
    {
        Storage::fake('local');
        $token = (string) Str::uuid();
        Storage::disk('local')->put('hesabro-tests/'.$token.'/session.json', json_encode([
            'expires_at' => now()->addDay()->timestamp,
        ]));
        $url = '/api/v1/hesabro/wallet/callback/'.$token;
        $this->getJson($url.'?status=success&reference_id=123')->assertOk()->assertJson(['received' => true])->assertJsonMissing(['reference_id' => '123']);
        $this->postJson($url, ['success' => false])->assertOk();
        $rows = array_map(fn ($row) => json_decode($row, true), explode("\n", trim(Storage::disk('local')->get('hesabro-tests/'.$token.'/callbacks.jsonl'))));
        $this->assertCount(2, $rows);
        $this->assertSame('123', $rows[0]['query']['reference_id']);
        $this->assertSame('POST', $rows[1]['method']);
        $this->assertFalse($rows[1]['body']['success']);
        $this->assertSame('{"success":false}', $rows[1]['raw_body']);
        $this->artisan('hesabro:callback-test', ['token' => $token])->assertSuccessful();
    }

    public function test_capture_rejects_unknown_expired_and_oversized_requests(): void
    {
        Storage::fake('local');
        $token = (string) Str::uuid();
        $url = '/api/v1/hesabro/wallet/callback/'.$token;
        $this->getJson($url)->assertNotFound();
        Storage::disk('local')->put('hesabro-tests/'.$token.'/session.json', json_encode(['expires_at' => now()->subSecond()->timestamp]));
        $this->getJson($url)->assertStatus(410);
        Storage::disk('local')->put('hesabro-tests/'.$token.'/session.json', json_encode(['expires_at' => now()->addDay()->timestamp]));
        $this->postJson($url, ['data' => str_repeat('x', 65537)])->assertStatus(413);
        Storage::disk('local')->assertMissing('hesabro-tests/'.$token.'/callbacks.jsonl');
    }

    public function test_command_creates_session(): void
    {
        Storage::fake('local');
        $this->artisan('hesabro:callback-test')->assertSuccessful();
        $this->assertCount(1, Storage::disk('local')->allFiles('hesabro-tests'));
    }
}
