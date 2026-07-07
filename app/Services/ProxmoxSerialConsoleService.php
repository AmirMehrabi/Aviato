<?php

namespace App\Services;

use App\Models\ProxmoxServer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProxmoxSerialConsoleService
{
    private const CONNECT_TIMEOUT = 10;

    private const READ_TIMEOUT = 30;

    /**
     * Execute a command on the VM's serial console via the Proxmox WebSocket terminal.
     *
     * @return array{output: string, exit_code: int|null}
     */
    public function execute(ProxmoxServer $server, string $node, int $vmid, string $command, int $timeout = 60): array
    {
        $wsUrl = $this->resolveTerminalUrl($server, $node, $vmid);
        $socket = $this->connect($wsUrl, $server);

        try {
            $this->waitForPrompt($socket, ['#', '>'], self::READ_TIMEOUT);

            $this->send($socket, $command."\n");

            $output = $this->readUntilPrompt($socket, ['#', '>'], $timeout);

            return ['output' => $output, 'exit_code' => null];
        } finally {
            fclose($socket);
        }
    }

    /**
     * Execute multiple commands sequentially on the serial console.
     *
     * @param  array<int, string>  $commands
     * @return array{output: string, commands_executed: int}
     */
    public function executeBatch(ProxmoxServer $server, string $node, int $vmid, array $commands, int $timeout = 60): array
    {
        $wsUrl = $this->resolveTerminalUrl($server, $node, $vmid);
        $socket = $this->connect($wsUrl, $server);
        $fullOutput = '';

        try {
            $this->waitForPrompt($socket, ['#', '>'], self::READ_TIMEOUT);

            $executed = 0;

            foreach ($commands as $command) {
                $this->send($socket, $command."\n");

                $chunk = $this->readUntilPrompt($socket, ['#', '>'], $timeout);
                $fullOutput .= $chunk;
                $executed++;
            }

            return ['output' => $fullOutput, 'commands_executed' => $executed];
        } finally {
            fclose($socket);
        }
    }

    private function resolveTerminalUrl(ProxmoxServer $server, string $node, int $vmid): string
    {
        $ticket = $this->authenticate($server);

        $response = $this->httpRequest($server)
            ->post("/nodes/{$node}/qemu/{$vmid}/terminal", ['term' => 'serial0']);

        $data = $response->throw()->json('data');

        if (! is_string($data) || $data === '') {
            throw new RuntimeException('Proxmox did not return a terminal WebSocket URL.');
        }

        $parsedUrl = parse_url($data);
        if (! $parsedUrl || ! isset($parsedUrl['host'], $parsedUrl['port'])) {
            throw new RuntimeException('Invalid terminal WebSocket URL received from Proxmox.');
        }

        $scheme = ($parsedUrl['scheme'] ?? 'wss') === 'wss' ? 'ssl' : 'tcp';
        $host = $parsedUrl['host'];
        $port = (int) ($parsedUrl['port'] ?? ($scheme === 'ssl' ? 443 : 80));

        $path = $parsedUrl['path'] ?? '/';
        if (isset($parsedUrl['query'])) {
            $path .= '?'.$parsedUrl['query'];
        }

        $socketUrl = "tcp://{$host}:{$port}";

        return $this->buildWebSocketUrl($socketUrl, $scheme, $host, $port, $path, $ticket);
    }

    private function buildWebSocketUrl(string $socketUrl, string $scheme, string $host, int $port, string $path, string $ticket): string
    {
        return $socketUrl.'|'.$scheme.'|'.$host.'|'.$port.'|'.$path.'|'.$ticket;
    }

    /** @var resource */
    private function connect(string $encodedUrl, ProxmoxServer $server): mixed
    {
        [$socketUrl, $scheme, $host, $port, $path, $ticket] = explode('|', $encodedUrl, 6);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $server->verify_tls,
                'verify_peer_name' => $server->verify_tls,
            ],
        ]);

        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $socket = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $errstr, self::CONNECT_TIMEOUT, $flags, $context);

        if (! is_resource($socket)) {
            throw new RuntimeException("Failed to connect to Proxmox terminal: {$errstr} ({$errno})");
        }

        if ($scheme === 'ssl') {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        }

        $key = base64_encode(random_bytes(16));

        $request = "GET {$path} HTTP/1.1\r\n"
            ."Host: {$host}:{$port}\r\n"
            .'Upgrade: websocket'."\r\n"
            .'Connection: Upgrade'."\r\n"
            ."Sec-WebSocket-Key: {$key}\r\n"
            .'Sec-WebSocket-Version: 13'."\r\n"
            .'Cookie: PVEAuthCookie='.$ticket."\r\n"
            ."\r\n";

        $this->writeRaw($socket, $request);

        $response = '';
        while (true) {
            $chunk = fread($socket, 4096);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
            if (str_contains($response, "\r\n\r\n")) {
                break;
            }
        }

        if (! str_contains($response, '101')) {
            throw new RuntimeException("WebSocket upgrade failed: {$response}");
        }

        return $socket;
    }

    private function send($socket, string $data): void
    {
        $frame = chr(0x81);
        $length = strlen($data);
        $mask = random_bytes(4);

        if ($length <= 125) {
            $frame .= chr(0x80 | $length);
        } elseif ($length <= 65535) {
            $frame .= chr(0x80 | 127).pack('N', $length);
        } else {
            $frame .= chr(0x80 | 127).pack('J', $length);
        }

        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= $data[$i] ^ $mask[$i % 4];
        }

        $this->writeRaw($socket, $frame);
    }

    private function receive($socket, int $timeoutSeconds = self::READ_TIMEOUT): ?string
    {
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $read = [$socket];
            $write = $except = null;
            $changed = @stream_select($read, $write, $except, 0, 200_000);

            if ($changed === false) {
                break;
            }

            if ($changed === 0) {
                continue;
            }

            $header = fread($socket, 2);
            if ($header === false || strlen($header) < 2) {
                continue;
            }

            $byte1 = ord($header[0]);
            $byte2 = ord($header[1]);
            $opcode = $byte1 & 0x0F;
            $masked = ($byte2 & 0x80) !== 0;
            $payloadLength = $byte2 & 0x7F;

            if ($payloadLength === 126) {
                $ext = fread($socket, 2);
                if ($ext === false) {
                    continue;
                }
                $payloadLength = unpack('n', $ext)[1];
            } elseif ($payloadLength === 127) {
                $ext = fread($socket, 8);
                if ($ext === false) {
                    continue;
                }
                $payloadLength = unpack('J', $ext)[1];
            }

            $maskKey = null;
            if ($masked) {
                $maskKey = fread($socket, 4);
                if ($maskKey === false) {
                    continue;
                }
            }

            $payload = '';
            $remaining = $payloadLength;
            while ($remaining > 0) {
                $chunk = fread($socket, min($remaining, 8192));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $payload .= $chunk;
                $remaining -= strlen($chunk);
            }

            if ($maskKey !== null && $payload !== '') {
                for ($i = 0; $i < strlen($payload); $i++) {
                    $payload[$i] = $payload[$i] ^ $maskKey[$i % 4];
                }
            }

            if ($opcode === 0x08) {
                return null;
            }

            if ($opcode === 0x01 && $payload !== '') {
                return $payload;
            }
        }

        return '';
    }

    private function writeRaw($socket, string $data): void
    {
        $written = 0;
        $length = strlen($data);

        while ($written < $length) {
            $chunk = substr($data, $written);
            $bytes = fwrite($socket, $chunk);
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Failed to write to WebSocket.');
            }
            $written += $bytes;
        }
    }

    private function waitForPrompt($socket, array $prompts, int $timeoutSeconds): string
    {
        $buffer = '';
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $chunk = $this->receive($socket, min(2, $deadline - time()));
            if ($chunk === null) {
                break;
            }
            $buffer .= $chunk;

            foreach ($prompts as $prompt) {
                if (str_ends_with($buffer, $prompt)) {
                    return $buffer;
                }
            }
        }

        return $buffer;
    }

    private function readUntilPrompt($socket, array $prompts, int $timeoutSeconds): string
    {
        $buffer = '';
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            $remaining = $deadline - time();
            if ($remaining <= 0) {
                break;
            }
            $chunk = $this->receive($socket, min(2, $remaining));
            if ($chunk === null) {
                break;
            }
            $buffer .= $chunk;

            foreach ($prompts as $prompt) {
                if (str_ends_with($buffer, $prompt)) {
                    return $buffer;
                }
            }
        }

        return $buffer;
    }

    private function authenticate(ProxmoxServer $server): string
    {
        $response = $this->httpRequest($server)
            ->asForm()
            ->post('/access/ticket', [
                'username' => $server->proxmoxUser(),
                'password' => $server->password,
            ]);

        $ticket = $response->throw()->json('data.ticket');

        if (blank($ticket)) {
            throw new RuntimeException('Proxmox did not return an authentication ticket.');
        }

        return $ticket;
    }

    private function httpRequest(ProxmoxServer $server): PendingRequest
    {
        return Http::baseUrl($server->baseUrl().'/api2/json')
            ->acceptJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->withOptions(['verify' => $server->verify_tls]);
    }
}
