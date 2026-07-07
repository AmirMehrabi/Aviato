<?php

namespace App\Services;

use App\Models\VirtualMachine;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
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
            throw new RuntimeException('RouterOS post-installation requires a reserved IP address and IP pool.');
        }

        $username = trim((string) $image->default_username);
        if ($username === '') {
            throw new RuntimeException('RouterOS post-installation requires a default username.');
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

        $this->waitUntilReachable($username, $address->address, $image?->os_family);

        foreach ($commands as $index => $command) {
            $result = $this->run($username, $address->address, $command);

            if ($result->failed()) {
                throw new RuntimeException(sprintf(
                    'RouterOS post-installation command %d failed: %s',
                    $index + 1,
                    trim($result->errorOutput()) ?: trim($result->output()) ?: 'SSH exited unsuccessfully.'
                ));
            }
        }

        return ['commands_executed' => $commands->count()];
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
            throw new RuntimeException('RouterOS post-installation script contains an unknown variable.');
        }

        return $command;
    }

    private function waitUntilReachable(string $username, string $host, ?string $osFamily = null): void
    {
        $probe = $osFamily === 'router_os' ? ':put "ready"' : 'echo ready';

        for ($attempt = 1; $attempt <= self::CONNECT_ATTEMPTS; $attempt++) {
            if ($this->run($username, $host, $probe)->successful()) {
                return;
            }

            if ($attempt < self::CONNECT_ATTEMPTS) {
                sleep(self::RETRY_DELAY_SECONDS);
            }
        }

        throw new RuntimeException("RouterOS SSH did not become reachable at {$host}.");
    }

    private function run(string $username, string $host, string $command): ProcessResult
    {
        return Process::timeout(20)->run([
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'PreferredAuthentications=none,password',
            '-o', 'PubkeyAuthentication=no',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            $username.'@'.$host,
            $command,
        ]);
    }
}
