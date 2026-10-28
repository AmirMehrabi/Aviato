<?php

namespace App\Console\Commands;

use App\Jobs\DeliverCorporateNotification;
use App\Models\AppSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigureCorporateSms extends Command
{
    protected $signature = 'corporate:configure-sms {--apply : Create or update provider templates} {--sender=200000171 : Required primary sending line} {--include-existing : Also update the sending line of configured login, ticket and wallet templates}';

    protected $description = 'Preview or configure corporate Kavenegar Lookup templates and their primary line';

    public function handle(): int
    {
        $sender = (string) $this->option('sender');
        if (! preg_match('/^\d{3,20}$/', $sender)) {
            $this->error('Invalid sender.');

            return self::FAILURE;
        }
        $templates = DeliverCorporateNotification::smsTemplates();
        if (! $this->option('apply')) {
            foreach ($templates as $event => $template) {
                $this->line(AppSetting::getValue('corporate.template.'.$event, $template['name']).' [primaryLineNumber='.$sender."]\n".$template['text']);
            }
            $this->info('Preview only. Use --apply to configure the templates at Kavenegar.');

            return self::SUCCESS;
        }
        $apiKey = AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, '');
        if (blank($apiKey)) {
            $this->error('Configure the existing Kavenegar API key first.');

            return self::FAILURE;
        }
        $base = 'https://api.kavenegar.com/v1/'.rawurlencode($apiKey).'/verify/';
        try {
            $existing = collect();
            $page = 1;
            do {
                $response = Http::asForm()->timeout(20)->post($base.'templatelist.json', ['page' => $page]);
                if (! $response->successful() || (int) $response->json('return.status') !== 200) {
                    throw new \RuntimeException('Template list failed.');
                }
                $existing = $existing->merge($response->json('entries', []));
                $pages = (int) $response->json('metadata.TotalPages', 1);
                $page++;
            } while ($page <= min($pages, 100));
            foreach ($templates as $event => $template) {
                $name = AppSetting::getValue('corporate.template.'.$event, $template['name']);
                $found = $existing->first(fn ($item) => strcasecmp($item['name'] ?? '', $name) === 0);
                $payload = ['name' => $name, 'sourceType' => 0, 'sendMethod' => 1, 'fallBackMethod' => 3,
                    'primaryLineNumber' => $sender, 'sourceName' => 'Aviato', 'sourceUrl' => 'https://'.config('portals.customer.domain'), 'textMessage' => $template['text']];
                if ($found) {
                    $payload['templateId'] = $found['id'] ?? $found['templateid'] ?? null;
                }
                if ($found && ! $payload['templateId']) {
                    throw new \RuntimeException('Provider template ID missing.');
                }
                $response = Http::asForm()->timeout(20)->post($base.($found ? 'updatetemplate' : 'addtemplate').'.json', $payload);
                if (! $response->successful() || (int) $response->json('return.status') !== 200) {
                    throw new \RuntimeException('Provider rejected template '.$name.'. Check line ownership and template permissions.');
                }
                $this->info($name.': configured; provider approval must be checked before enabling SMS.');
            }
            if ($this->option('include-existing')) {
                $keys = [AppSetting::KAVENEGAR_TEMPLATE, AppSetting::CUSTOMER_WALLET_NEGATIVE_SMS_TEMPLATE,
                    AppSetting::TICKET_KAVENEGAR_CUSTOMER_CREATED_TEMPLATE, AppSetting::TICKET_KAVENEGAR_ADMIN_NEW_TEMPLATE,
                    AppSetting::TICKET_KAVENEGAR_CUSTOMER_REPLY_TEMPLATE, AppSetting::TICKET_KAVENEGAR_ADMIN_REPLY_TEMPLATE,
                    AppSetting::TICKET_KAVENEGAR_ASSIGNMENT_TEMPLATE];
                $names = collect($keys)->map(fn ($key) => AppSetting::getValue($key, ''))->filter()->unique();
                foreach ($names as $name) {
                    $found = $existing->first(fn ($item) => strcasecmp($item['name'] ?? '', $name) === 0);
                    $id = $found['id'] ?? $found['templateid'] ?? null;
                    if (! $id) {
                        throw new \RuntimeException('Configured template missing at provider.');
                    }
                    $detail = Http::asForm()->timeout(20)->post($base.'gettemplate.json', ['id' => $id]);
                    $entry = $detail->json('entries');
                    if (! $detail->successful() || (int) $detail->json('return.status') !== 200 || ! is_array($entry)) {
                        throw new \RuntimeException('Template details missing.');
                    }
                    $payload = ['templateId' => $id, 'sourceType' => 0, 'sendMethod' => 1, 'fallBackMethod' => 3,
                        'name' => $name, 'primaryLineNumber' => $sender, 'textMessage' => $entry['smsMessage'] ?? '',
                        'sourceUrl' => 'https://'.config('portals.customer.domain'), 'sourceName' => 'Aviato'];
                    if ($payload['textMessage'] === '') {
                        throw new \RuntimeException('SMS template text missing.');
                    }
                    $response = Http::asForm()->timeout(20)->post($base.'updatetemplate.json', $payload);
                    if (! $response->successful() || (int) $response->json('return.status') !== 200) {
                        throw new \RuntimeException('Sender update failed.');
                    }
                    $this->info($name.': primary line updated; message text preserved. Check approval before sending.');
                }
            }
        } catch (\Throwable) {
            // Provider URLs contain credentials; never print exception URLs.
            $this->error('Kavenegar configuration failed. Check API access, line ownership and provider template status; no SMS was sent.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
