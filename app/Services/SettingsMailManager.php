<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SettingsMailManager extends MailManager
{
    private ?string $settingsFingerprint = null;

    public function mailer($name = null)
    {
        // Read fresh values so long-running queue workers pick up admin changes.
        $settings = Schema::hasTable('app_settings')
            ? AppSetting::query()->where('group', 'smtp')->pluck('value', 'key')->all()
            : [];

        if (filled($settings[AppSetting::SMTP_HOST] ?? null)) {
            $fingerprint = hash('sha256', serialize($settings));
            if ($fingerprint !== $this->settingsFingerprint) {
                $password = $settings[AppSetting::SMTP_PASSWORD] ?? '';
                if (str_starts_with($password, 'encrypted:')) {
                    $password = Crypt::decryptString(substr($password, 10));
                }
                $encryption = $settings[AppSetting::SMTP_ENCRYPTION] ?? 'tls';
                $this->app['config']->set('mail.mailers.settings_smtp', [
                    'transport' => 'smtp',
                    'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $settings[AppSetting::SMTP_HOST],
                    'port' => (int) ($settings[AppSetting::SMTP_PORT] ?? 587),
                    'username' => $settings[AppSetting::SMTP_USERNAME] ?? null,
                    'password' => $password,
                    'auto_tls' => $encryption === 'tls',
                    'require_tls' => $encryption === 'tls',
                    'timeout' => 8,
                    'from' => [
                        'address' => $settings[AppSetting::SMTP_FROM_ADDRESS] ?? $this->app['config']->get('mail.from.address'),
                        'name' => $settings[AppSetting::SMTP_FROM_NAME] ?? $this->app['config']->get('mail.from.name'),
                    ],
                ]);
                $this->purge('settings_smtp');
                $this->settingsFingerprint = $fingerprint;
            }

            $name ??= 'settings_smtp';
        }

        if ($name === 'settings_smtp' && blank($settings[AppSetting::SMTP_HOST] ?? null)) {
            throw new \RuntimeException('SMTP is not configured in admin settings.');
        }

        return parent::mailer($name);
    }
}
