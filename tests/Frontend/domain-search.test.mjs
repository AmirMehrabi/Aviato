import test from 'node:test';
import assert from 'node:assert/strict';
import { domainSearch } from '../../resources/js/domain-search.js';

function component(config = {}) {
    const value = domainSearch({ url: '/check', ...config });
    value.$refs = { domain: { focus() {} }, result: { focus() {} } };
    value.$nextTick = (fn) => fn();
    return value;
}

globalThis.document = { querySelector: () => ({ content: 'csrf-token' }) };

test('a successful public search uses the server handoff and normalized domain', async () => {
    let destination;
    globalThis.window = { location: { assign(url) { destination = url; } } };
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: { domain: 'example.com', available: true }, continue_url: 'https://cp.localhost/domains?domain=example.com' }) });
    const c = component({ publicSearch: true, domain: 'EXAMPLE.COM' });
    await c.search();
    assert.equal(c.domain, 'example.com');
    assert.equal(destination, 'https://cp.localhost/domains?domain=example.com');
    assert.equal(c.loading, false);
});

test('rate limiting starts a cooldown and never retries in the background', async () => {
    let calls = 0;
    globalThis.fetch = async () => {
        calls++;
        return { ok: false, status: 429, headers: new Headers({ 'Retry-After': '45' }), json: async () => ({}) };
    };
    const c = component({ domain: 'example.com' });
    await c.search();
    assert.equal(c.cooldown, 45);
    await c.search();
    assert.equal(calls, 1);
    c.destroy();
});

test('unavailable names stay on the page and network failure clears the previous result', async () => {
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ data: { domain: 'example.com', available: false }, continue_url: null }) });
    const c = component({ publicSearch: true, domain: 'example.com' });
    await c.search();
    assert.equal(c.result.available, false);
    globalThis.fetch = async () => { throw new Error('offline'); };
    await c.search();
    assert.equal(c.result, null);
    assert.ok(c.error);
    assert.equal(c.loading, false);
});

test('portal handoff checks once and ignores duplicate clicks while loading', async () => {
    let finish;
    let calls = 0;
    globalThis.fetch = () => {
        calls++;
        return new Promise(resolve => { finish = resolve; });
    };
    const c = component({ autoSearch: true, domain: 'example.com' });
    c.init();
    await c.search();
    assert.equal(calls, 1);
    finish({ ok: true, json: async () => ({ data: { domain: 'example.com', available: true } }) });
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(c.result.available, true);
});
