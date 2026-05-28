<?php

namespace App\Services;

use App\Models\ProxmoxServer;
use Carbon\CarbonInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

class WebsockifyConsoleTokenService
{
    public function publish(ProxmoxServer $server, string $token, int $port, CarbonInterface $expiresAt): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]{16,128}$/', $token)) {
            throw new RuntimeException('Console token format is invalid.');
        }

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Console target port is invalid.');
        }

        $tokenFile = (string) config('console.websockify.token_file');
        $targetHost = (string) config('console.websockify.target_host', '127.0.0.1');
        $sshUser = (string) config('console.websockify.ssh_user', 'root');
        $sshPort = (int) config('console.websockify.ssh_port', 22);
        $sshKey = (string) config('console.websockify.ssh_key', '');
        $timeout = max(2, (int) config('console.websockify.ssh_connect_timeout', 8));

        if ($tokenFile === '' || ! str_starts_with($tokenFile, '/')) {
            throw new RuntimeException('Console websockify token file must be an absolute path.');
        }

        if ($sshUser === '') {
            throw new RuntimeException('Console websockify SSH user is not configured.');
        }

        $line = $token.': '.$targetHost.':'.$port;
        $remote = $sshUser.'@'.$server->host;
        $remoteCommand = $this->remoteCommand($tokenFile, $token, $line, $expiresAt->timestamp);

        $command = [
            'ssh',
            '-p',
            (string) $sshPort,
            '-o',
            'BatchMode=yes',
            '-o',
            'StrictHostKeyChecking=accept-new',
            '-o',
            'ConnectTimeout='.$timeout,
        ];

        if ($sshKey !== '') {
            $command[] = '-i';
            $command[] = $sshKey;
        }

        $command[] = $remote;
        $command[] = $remoteCommand;

        $process = new Process($command);
        $process->setTimeout($timeout + 5);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Unable to publish websockify console token.');
        }
    }

    private function remoteCommand(string $tokenFile, string $token, string $line, int $expiresAt): string
    {
        $dir = dirname($tokenFile);
        $metadataFile = $tokenFile.'.expires';

        return implode(' && ', [
            'set -e',
            'install -d -m 700 '.escapeshellarg($dir),
            'touch '.escapeshellarg($tokenFile),
            'touch '.escapeshellarg($metadataFile),
            'chmod 600 '.escapeshellarg($tokenFile).' '.escapeshellarg($metadataFile),
            'tmp_meta=$(mktemp '.escapeshellarg($dir.'/tokens-meta.XXXXXX').')',
            'awk -v now="$(date +%s)" -v token='.escapeshellarg($token).' '.escapeshellarg('NF >= 2 && $1 != token && $2 > now { print }').' '.escapeshellarg($metadataFile).' > "$tmp_meta"',
            'printf %s\\n '.escapeshellarg($token.' '.$expiresAt).' >> "$tmp_meta"',
            'tmp=$(mktemp '.escapeshellarg($dir.'/tokens.XXXXXX').')',
            'awk -v token='.escapeshellarg($token).' '.escapeshellarg('NR == FNR { valid[$1] = 1; next } { split($1, key, ":"); if (key[1] != token && valid[key[1]]) print }').' "$tmp_meta" '.escapeshellarg($tokenFile).' > "$tmp"',
            'printf %s\\n '.escapeshellarg($line).' >> "$tmp"',
            'mv "$tmp" '.escapeshellarg($tokenFile),
            'mv "$tmp_meta" '.escapeshellarg($metadataFile),
            'chmod 600 '.escapeshellarg($tokenFile).' '.escapeshellarg($metadataFile),
        ]);
    }
}
