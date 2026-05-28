import http from 'node:http';
import fs from 'node:fs';
import process from 'node:process';
import { WebSocket, WebSocketServer } from 'ws';

loadEnvFile();

const listenHost = process.env.CONSOLE_PROXY_HOST || '127.0.0.1';
const listenPort = Number(process.env.CONSOLE_PROXY_PORT || 8787);
const appUrl = (process.env.CONSOLE_PROXY_INTERNAL_URL || process.env.APP_URL || 'http://127.0.0.1').replace(/\/$/, '');
const appHostHeader = process.env.CONSOLE_PROXY_INTERNAL_HOST || '';
const proxySecret = process.env.CONSOLE_PROXY_SECRET || '';

if (!proxySecret) {
    console.error('CONSOLE_PROXY_SECRET is required.');
    process.exit(1);
}

const server = http.createServer((request, response) => {
    if (request.url === '/health' || request.url === '/console-ws/health') {
        response.writeHead(200, { 'content-type': 'application/json' });
        response.end(JSON.stringify({
            ok: true,
            app_url: appUrl,
            app_host_header: appHostHeader || null,
            listen_host: listenHost,
            listen_port: listenPort,
        }));
        return;
    }

    if (request.url === '/console-ws' || request.url === '/') {
        response.writeHead(426, { 'content-type': 'application/json' });
        response.end(JSON.stringify({ ok: false, message: 'WebSocket upgrade required.' }));
        return;
    }

    response.writeHead(404, { 'content-type': 'application/json' });
    response.end(JSON.stringify({ ok: false }));
});

const wss = new WebSocketServer({ server });

wss.on('connection', async (client, request) => {
    let upstream = null;

    try {
        const requestUrl = new URL(request.url || '/', 'http://localhost');
        const sessionId = requestUrl.searchParams.get('session');

        console.log('Console websocket accepted', { path: requestUrl.pathname, has_session: Boolean(sessionId) });

        if (!sessionId || !/^[0-9a-f-]{36}$/i.test(sessionId)) {
            closeWithReason(client, 1008, 'Invalid console session.');
            return;
        }

        const session = await resolveSession(sessionId);
        const target = proxmoxWebsocketUrl(session);

        console.log('Console session resolved', {
            session_id: session.session_id,
            vm_id: session.vm_id,
            node: session.node,
            vmid: session.vmid,
            proxmox_host: session.proxmox_host,
            proxmox_port: session.proxmox_port,
        });

        upstream = new WebSocket(target, {
            headers: session.headers || {},
            rejectUnauthorized: Boolean(session.verify_tls),
        });

        upstream.on('open', () => {
            console.log('Proxmox console websocket opened', {
                session_id: session.session_id,
                vm_id: session.vm_id,
            });

            client.on('message', (message, isBinary) => {
                if (upstream?.readyState === WebSocket.OPEN) {
                    upstream.send(message, { binary: isBinary });
                }
            });
            upstream.on('message', (message, isBinary) => {
                if (client.readyState === WebSocket.OPEN) {
                    client.send(message, { binary: isBinary });
                }
            });
        });

        upstream.on('close', (code, reason) => {
            console.log('Proxmox console websocket closed', {
                session_id: session.session_id,
                vm_id: session.vm_id,
                code,
                reason: reason?.toString(),
            });

            if (client.readyState === WebSocket.OPEN || client.readyState === WebSocket.CONNECTING) {
                client.close(code || 1000, reason?.toString() || 'Proxmox console closed.');
            }
        });

        upstream.on('error', (error) => {
            console.error('Proxmox console websocket error', {
                session_id: session.session_id,
                vm_id: session.vm_id,
                message: error.message,
            });
            closeWithReason(client, 1011, 'Proxmox console connection failed.');
        });

        client.on('close', () => {
            if (upstream && (upstream.readyState === WebSocket.OPEN || upstream.readyState === WebSocket.CONNECTING)) {
                upstream.close(1000, 'Browser console closed.');
            }
        });
    } catch (error) {
        console.error('Console proxy session failed', { message: error.message });
        closeWithReason(client, 1011, 'Console proxy session failed.');
        if (upstream) {
            upstream.close();
        }
    }
});

server.listen(listenPort, listenHost, () => {
    console.log(`Console proxy listening on ${listenHost}:${listenPort}`);
});

async function resolveSession(sessionId) {
    const sessionUrl = `${appUrl}/api/console-proxy/sessions/${sessionId}`;
    const headers = {
        Accept: 'application/json',
        'X-Console-Proxy-Secret': proxySecret,
    };

    if (appHostHeader) {
        headers.Host = appHostHeader;
    }

    let response;

    try {
        response = await fetch(sessionUrl, { headers });
    } catch (error) {
        throw new Error(`Could not reach Laravel console session endpoint at ${sessionUrl}: ${error.cause?.message || error.message}`);
    }

    if (!response.ok) {
        const body = await response.text().catch(() => '');
        const server = response.headers.get('server') || 'unknown';
        const contentType = response.headers.get('content-type') || 'unknown';

        throw new Error(`Laravel session lookup failed at ${sessionUrl} with HTTP ${response.status} from ${server} (${contentType}): ${body.slice(0, 250)}`);
    }

    return response.json();
}

function loadEnvFile() {
    const envPath = process.env.CONSOLE_PROXY_ENV || '.env';

    if (!fs.existsSync(envPath)) {
        return;
    }

    const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);

    for (const line of lines) {
        const trimmed = line.trim();

        if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) {
            continue;
        }

        const index = trimmed.indexOf('=');
        const key = trimmed.slice(0, index).trim();
        let value = trimmed.slice(index + 1).trim();

        if (process.env[key] !== undefined) {
            continue;
        }

        if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
            value = value.slice(1, -1);
        }

        process.env[key] = value.replace(/\$\{([^}]+)\}/g, (_, envKey) => process.env[envKey] || '');
    }
}

function proxmoxWebsocketUrl(session) {
    const url = new URL(`/api2/json/nodes/${encodeURIComponent(session.node)}/qemu/${encodeURIComponent(String(session.vmid))}/vncwebsocket`, `wss://${session.proxmox_host}:${session.proxmox_port}`);

    url.searchParams.set('port', String(session.port));
    url.searchParams.set('vncticket', session.vncticket);

    return url.toString();
}

function closeWithReason(socket, code, reason) {
    if (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING) {
        socket.close(code, reason);
    }
}
