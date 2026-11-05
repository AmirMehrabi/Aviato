<?php

namespace App\Services;

use App\Models\AppSetting;
use PDO;
use RuntimeException;

class DeploymentSmsSettings
{
    public const EVENTS = ['started', 'success', 'failure', 'rollback', 'recovery'];

    public function path(): string
    {
        return config('deployment-sms.path');
    }

    public function read(): array
    {
        $file = $this->path().'/config.json';
        $settings = is_file($file) ? json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : [];
        unset($settings['api_key']);

        return $settings + ['enabled' => false, 'recipients' => ['09135512366'], 'max_attempts' => 5, 'events' => []];
    }

    public function save(array $settings): void
    {
        if (! is_dir($this->path()) || ! is_writable($this->path())) {
            throw new RuntimeException('Deployment SMS storage is not configured.');
        }
        $settings['api_key'] = (string) AppSetting::getValue(AppSetting::KAVENEGAR_API_KEY, '');
        $temp = tempnam($this->path(), 'config-');
        file_put_contents($temp, json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        chmod($temp, 0660);
        rename($temp, $this->path().'/config.json');
    }

    public function database(): PDO
    {
        $file = $this->path().'/deliveries.sqlite';
        if (! is_file($file)) {
            throw new RuntimeException('Deployment SMS outbox is not initialized.');
        }
        $db = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA busy_timeout=20000');

        return $db;
    }
}
