import assert from 'node:assert/strict';
import { test } from 'node:test';
import { customerServerStatus, customerServerDetailStatus } from '../../resources/js/server-status.js';

function setup() {
    const state = { reloads: 0, cleared: [], requests: [], timers: [] };
    globalThis.document = { hidden: false };
    globalThis.window = {
        location: { reload() { state.reloads++; } },
        setInterval(callback) { state.timers.push(callback); return state.timers.length; },
        clearInterval(id) { state.cleared.push(id); },
    };
    globalThis.fetch = (url, options) => new Promise((resolve, reject) => state.requests.push({ url, options, resolve, reject }));
    return state;
}
const server = (overrides = {}) => ({
    id: 'vm-124', status: 'running', action_pending: false, operation_id: null,
    password_reset_status: null, provisioning_pending: false, ...overrides,
});
const config = { url: '/servers/statuses', id: 'vm-124', status: 'running', operation: null, passwordResetStatus: null, provisioningPending: false, pending: false };
const respond = (request, servers) => request.resolve({ ok: true, json: async () => ({ servers }) });
const flush = () => new Promise((resolve) => setImmediate(resolve));

test('list continues polling after idle response and reflects a subsequent stop', async () => {
    const state = setup();
    const component = customerServerStatus({ url: config.url, servers: [server()] });
    component.init();
    respond(state.requests[0], [server()]);
    await flush();
    assert.deepEqual(state.cleared, []);
    const tick = state.timers[0]();
    respond(state.requests[1], [server({ status: 'stopped' })]);
    await tick;
    assert.equal(component.server('vm-124').status, 'stopped');
    assert.equal(state.requests[1].options.cache, 'no-store');
    component.destroy();
    assert.deepEqual(state.cleared, [1]);
});

test('detail notices power change even with no pending operation on page load', async () => {
    const state = setup();
    const component = customerServerDetailStatus(config);
    component.init();
    respond(state.requests[0], [server()]);
    await flush();
    assert.equal(state.reloads, 0);
    const tick = state.timers[0]();
    respond(state.requests[1], [server({ status: 'stopped' })]);
    await tick;
    assert.equal(state.reloads, 1);
    component.destroy();
    assert.deepEqual(state.cleared, [1]);
});

test('detail refreshes when operation finishes even if power state is unchanged', async () => {
    const state = setup();
    const component = customerServerDetailStatus({ ...config, operation: 4 });
    const request = component.refresh();
    respond(state.requests[0], [server()]);
    await request;
    assert.equal(state.reloads, 1);
});

test('polling retries after failure and skips hidden pages and overlapping requests', async () => {
    const state = setup();
    const component = customerServerDetailStatus(config);
    document.hidden = true;
    await component.refresh();
    assert.equal(state.requests.length, 0);
    document.hidden = false;
    const request = component.refresh();
    await component.refresh();
    assert.equal(state.requests.length, 1);
    state.requests[0].reject(new Error('Network unavailable'));
    await request;
    const retry = component.refresh();
    respond(state.requests[1], [server({ status: 'stopped' })]);
    await retry;
    assert.equal(state.reloads, 1);
});
