<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\Sms\WalletSmsTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ConfigureWalletSms extends Command
{
    protected $signature = 'wallet:configure-sms {--apply : Submit missing templates only} {--sender=200000171}';

    protected $description = 'Preview, submit or inspect wallet SMS templates without activating or updating any template';

    public function handle(): int
    {
        $key = AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, '');
        if (blank($key)) {
            $this->error('Kavenegar API key is missing.');

            return self::FAILURE;
        }
        $sender = (string) $this->option('sender');
        if (! preg_match('/^\d{3,20}$/', $sender)) {
            return self::FAILURE;
        }
        $base = 'https://api.kavenegar.com/v1/'.rawurlencode($key).'/verify/';
        try {
            $mappings = AppSetting::getValue('kavenegar.template_ids', []);
            $existing = collect();
            for ($page = 1; $page <= 100; $page++) {
                try {
                    $result = $this->request($base.'templatelist.json', ['page' => $page], true);
                } catch (Throwable $exception) {
                    if ($page !== 1 || $mappings === []) {
                        throw $exception;
                    }
                    $existing = collect($mappings)->map(fn ($id, $name) => ['id' => $id, 'name' => $name])->values();
                    $this->warn('Listing unavailable; using saved template IDs. Existing templates will never be updated.');
                    break;
                }
                $existing = $existing->merge($result['entries'] ?? []);
                if ($page >= (int) data_get($result, 'metadata.TotalPages', 1)) {
                    break;
                }
                if ($page === 100) {
                    throw new RuntimeException('Listing incomplete.');
                }
            }
            foreach ($this->templates() as $template) {
                $found = $existing->first(fn ($item) => strcasecmp($item['name'] ?? '', $template['name']) === 0);
                if ($found) {
                    $id = $found['id'] ?? $found['templateid'] ?? null;
                    if (! $id) {
                        throw new RuntimeException('Missing ID.');
                    }
                    $detail = $this->request($base.'gettemplate.json', ['id' => $id], true)['entries'];
                    if (($detail['name'] ?? '') !== $template['name']) {
                        throw new RuntimeException('Name mismatch.');
                    }
                    $matches = ($detail['smsmessage'] ?? $detail['smsMessage'] ?? '') === $template['text'];
                    $this->line($template['name'].': '.($detail['approvalstatus'] ?? $detail['approvalStatus'] ?? 'unknown').($matches ? '' : ' (TEXT DIFFERS; do not activate)'));
                } elseif ($this->option('apply')) {
                    $created = $this->request($base.'addtemplate.json', [
                        'name' => $template['name'], 'textMessage' => $template['text'],
                        'sourceType' => 0, 'sendMethod' => 1, 'fallBackMethod' => 3,
                        'primaryLineNumber' => $sender, 'sourceName' => 'Aviato',
                        'sourceUrl' => 'https://'.config('portals.customer.domain'),
                    ]);
                    $id = data_get($created, 'entries.templateid') ?? data_get($created, 'entries.id') ?? data_get($created, 'entries.0.templateid') ?? data_get($created, 'entries.0.id');
                    if ($id) {
                        $mappings[$template['name']] = $id;
                        AppSetting::setValue('kavenegar.template_ids', $mappings, 'array', 'kavenegar');
                    }
                    $this->info($template['name'].': submitted; not activated.'.($id ? ' ID='.$id : ' (provider returned no ID; record its ID before retrying)'));
                } else {
                    $this->line($template['name'].": not submitted\n".$template['text']);
                }
            }
        } catch (Throwable) {
            $this->error('Provider request failed. No active settings were changed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function templates(): array
    {
        return WalletSmsTemplates::definitions();
    }

    private function request(string $url, array $payload, bool $read = false): array
    {
        $client = Http::asForm()->timeout(20);
        $response = $read ? $client->get($url, $payload) : $client->post($url, $payload);
        if (! $response->successful() || (int) $response->json('return.status') !== 200) {
            throw new RuntimeException('Provider request failed.');
        }

        return $response->json();
    }
}
