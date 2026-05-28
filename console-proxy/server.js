import http from 'node:http';
import process from 'node:process';
import { WebSocket, WebSocketServer } from 'ws';

const listenHost = process.env.CONSOLE_PROXY_HOST || '127.0.0.1';
const listenPort = Number(process.env.CONSOLE_PROXY_PORT || 8787);
const appUrl = (process.env.CONSOLE_PROXY_INTERNAL_URL || process.env.APP_URL || 'http://127.0.0.1').replace(/\/$/, '');
const proxySecret = process.env.CONSOLE_PROXY_SECRET || '';

if (!proxySecret) {
    console.error('CONSOLE_PROXY_SECRET is required.');
    process.exit(1);
}

const server = http.createServer((request, response) => {
    if (request.url === '/health') {
        response.writeHead(200, { 'content-type': 'application/json' });
        response.end(JSON.stringify({ ok: true }));
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

        if (!sessionId || !/^[0-9a-f-]{36}$/i.test(sessionId)) {
            closeWithReason(client, 1008, 'Invalid console session.');
            return;
        }

        const session = await resolveSession(sessionId);
        const target = proxmoxWebsocketUrl(session);

        upstream = new WebSocket(target, {
            headers: session.headers || {},
            rejectUnauthorized: Boolean(session.verify_tls),
        });

        upstream.on('open', () => {
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
    const response = await fetch(`${appUrl}/api/console-proxy/sessions/${sessionId}`, {
        headers: {
            Accept: 'application/json',
            'X-Console-Proxy-Secret': proxySecret,
        },
    });

    if (!response.ok) {
        throw new Error(`Laravel rejected console session with HTTP ${response.status}.`);
    }

    return response.json();
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
