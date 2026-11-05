<?php

namespace App\Console\Commands;

use App\Services\Sms\DeploymentSmsTemplates;

class ConfigureDeploymentSms extends ConfigureWalletSms
{
    protected $signature = 'deployment:configure-sms {--apply : Create missing templates only; never update existing templates} {--sender=200000171}';

    protected $description = 'Inspect or submit deployment SMS templates without enabling messages';

    protected function templates(): array
    {
        return DeploymentSmsTemplates::definitions();
    }
}
