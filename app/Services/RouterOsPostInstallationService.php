<?php

namespace App\Services;

use App\Models\VirtualMachine;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RouterOsPostInstallationService
{
    private const CONNECT_ATTEMPTS = 18;

    private const RETRY_DELAY_SECONDS = 5;

    /**
     * @return array{commands_executed:int}
     */
    public function execute(VirtualMachine $vm): array
    {
        $vm->loadMissing(['cloudImage', 'reservedIpAddress.pool']);

        $image = $vm->cloudImage;
        $address = $vm->reservedIpAddress;
        $pool = $address?->pool;
        $script = trim((string) $image?->post_installation_script);

        if ($script === '') {
            return ['commands_executed' => 0];
        }

        if (! $address || ! $pool) {
            throw new RuntimeException('Post-installation requires a reserved IP address and IP pool.');
        }

        $username = trim((string) $image->default_username);
        if ($username === '') {
            throw new RuntimeException('Post-installation requires a default username on the cloud image.');
        }

        $commands = collect(preg_split('/\R/u', $script) ?: [])
            ->map(fn (string $command): string => trim($command))
            ->reject(fn (string $command): bool => $command === '' || str_starts_with($command, '#'))
            ->map(fn (string $command): string => $this->interpolate($command, [
                'ip_address' => $address->address,
                'ip_address_with_prefix' => $address->address.'/'.$pool->prefix_length,
                'prefix_length' => (string) $pool->prefix_length,
                'gateway' => $pool->gateway,
            ]))
            ->values();

        if ($commands->isEmpty()) {
            return ['commands_executed' => 0];
        }

        $vm->loadMissing('proxmoxServer');

        $server = $vm->proxmoxServer;
        if (! $server || ! $vm->node || ! $vm->vmid) {
            throw new RuntimeException('Post-installation requires a connected Proxmox server, node, and VMID.');
        }

        $console = app(ProxmoxSerialConsoleService::class);

        try {
            $result = $console->executeBatch($server, $vm->node, (int) $vm->vmid, $commands->all());
        } catch (RuntimeException $exception) {
            Log::error('Post-installation serial console failed', [
                'virtual_machine_id' => $vm->id,
                'vmid' => $vm->vmid,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return ['commands_executed' => $result['commands_executed']];
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function interpolate(string $command, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $command = str_replace(
                ['{{'.$name.'}}', '{{ '.$name.' }}', '${'.$name.'}'],
                $value,
                $command
            );
        }

        if (preg_match('/\{\{\s*[^}]+\s*\}\}|\$\{[^}]+\}/', $command)) {
            throw new RuntimeException('Post-installation script contains an unknown variable.');
        }

        return $command;
    }
}
