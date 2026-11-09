import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

class FakeRFB extends EventTarget {
    static clients = [];
    constructor(screen, url, options) { super(); this.url = url; this.calls = []; FakeRFB.clients.push(this); }
    disconnect() { this.dispatchEvent(new Event('disconnect')); }
    sendKey(...args) { this.calls.push(args); }
    focus() {}
    blur() {}
    clipboardPasteFrom(value) { this.pasted = value; }
}
globalThis.FakeRFB = FakeRFB;
const source = (await readFile(new URL('../../resources/js/vm-console.js', import.meta.url), 'utf8')).replace("import RFB from '@novnc/novnc';", 'const RFB = globalThis.FakeRFB;');
const { vmConsole } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

beforeEach(() => {
    FakeRFB.clients = [];
    globalThis.window = { location: { href: 'https://cp.localhost/servers/test/console', host: 'cp.localhost', protocol: 'https:' } };
    globalThis.document = { removeEventListener() {}, body: { style: {} } };
});
function component(t) {
    const state = vmConsole({ sessionUrl: '/session', csrf: 'token', name: 'test-vm' });
    state.$refs = { screen: {}, keyboardButton: { focus() {} } };
    t.after(() => state.destroy());
    return state;
}
const session = () => ({ ok: true, status: 200, json: async () => ({ websocket_url: '/console-ws/test?ticket=secret', password: 'secret', reference: 'reference' }) });

test('a late session response cannot replace a newer connection or restart a disconnected console', async (t) => {
    let resolveFirst;
    globalThis.fetch = () => new Promise((resolve) => { resolveFirst = resolve; });
    const state = component(t);
    const first = state.connect();
    globalThis.fetch = async () => session();
    await state.connect();
    assert.equal(FakeRFB.clients.length, 1);
    resolveFirst(session());
    await first;
    assert.equal(FakeRFB.clients.length, 1);
    state.disconnect();
    assert.equal(state.retryIn, 0);
    assert.equal(state.loading, false);
});

test('portal expiry does not auto retry and malformed responses do not leak server errors', async (t) => {
    const state = component(t);
    globalThis.fetch = async () => ({ ok: false, status: 419, json: async () => { throw new Error('html'); } });
    await state.connect();
    assert.equal(state.errorCode, 'portal_session');
    assert.equal(state.retryIn, 0);
    assert.match(state.error, /منقضی/);
});

test('timeout aborts a hung request and retries stop after three attempts', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout', 'setInterval'] });
    const state = component(t);
    let signal;
    globalThis.fetch = (url, options) => { signal = options.signal; return new Promise(() => {}); };
    state.connect();
    t.mock.timers.tick(20000);
    assert.equal(signal.aborted, true);
    assert.equal(state.errorCode, 'timeout');
    assert.equal(state.retryCount, 1);
    assert.equal(state.retryIn, 3);
    state.cancelRetry();
    t.mock.timers.tick(30000);
    assert.equal(state.loading, false);
    for (let i = 0; i < 3; i++) state.fail('network failure', 'network', true);
    assert.equal(state.retryCount, 3);
    assert.equal(state.retryIn, 0);
});

test('old transport events cannot disconnect a new transport; manual disconnect does not retry', async (t) => {
    const state = component(t);
    globalThis.fetch = async () => session();
    await state.connect();
    const first = FakeRFB.clients[0];
    await state.connect();
    FakeRFB.clients[1].dispatchEvent(new Event('connect'));
    first.dispatchEvent(new Event('disconnect'));
    assert.equal(state.connected, true);
    state.disconnect();
    assert.equal(state.connected, false);
    assert.equal(state.retryIn, 0);
});

test('view-only mode releases sticky modifiers and blocks keyboard and clipboard sends', async (t) => {
    const state = component(t);
    globalThis.fetch = async () => session();
    await state.connect();
    const client = FakeRFB.clients[0];
    client.dispatchEvent(new Event('connect'));
    state.toggleModifier('Control');
    state.viewOnly = true;
    state.setViewOnly();
    assert.equal(state.held.Control, false);
    assert.equal(client.viewOnly, true);
    assert.deepEqual(client.calls.map((call) => call[2]), [true, false]);
    state.sendKey('Tab');
    state.clipboard = 'text'; state.sendClipboard();
    assert.equal(client.calls.length, 2);
    assert.equal(client.pasted, undefined);
    assert.ok(!state.diagnostics().includes('secret'));
    state.display = 'actual'; state.applyDisplay();
    assert.equal(client.scaleViewport, false);
    assert.equal(client.clipViewport, false);
    assert.equal(client.resizeSession, false);
});
