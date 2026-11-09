import { test } from 'node:test';
import assert from 'node:assert/strict';
import { adminVmDetail } from '../../resources/js/admin-vm-detail.js';

function component() {
    const state = adminVmDetail();
    state.root = { dataset: { status: 'running', provisioning: 'ready', generation: '2', locked: '0' } };
    state.schedule = () => {};
    globalThis.document = { hidden: false };
    globalThis.location = { href: 'https://admin.localhost/virtual-machines/test?operations_page=2#activities' };
    return state;
}

test('targeted polling preserves navigation and detects stale power generations', async () => {
    const state = component();
    state.tab = 'configuration';
    globalThis.fetch = async (url, options) => {
        assert.equal(url.search, '?status_only=1');
        assert.equal(url.hash, '');
        assert.equal(options.cache, 'no-store');
        return { ok: true, json: async () => ({status:'running', provisioning:'ready', generation:3, locked:false, operation:null}) };
    };
    await state.poll();
    assert.equal(state.tab, 'configuration');
    assert.equal(state.stale, true);
    assert.equal(state.loading, false);
    let prevented = false;
    state.guardSubmission({target:{method:'POST', dataset:{}}, preventDefault(){ prevented = true; }});
    assert.equal(prevented, true);
});

test('poll failure preserves the last known state and allows retry', async () => {
    const state = component();
    state.status = 'running';
    globalThis.fetch = async () => { throw new Error('network'); };
    await state.poll();
    assert.equal(state.status, 'running');
    assert.equal(state.loading, false);
    assert.match(state.pollMessage, /دوباره/);
});

test('background tabs do not poll and duplicate submissions are blocked', async () => {
    const state = component();
    document.hidden = true;
    globalThis.fetch = async () => { assert.fail('hidden tab polled'); };
    await state.poll();
    let prevented = false;
    state.guardSubmission({target:{method:'POST',dataset:{submitting:'1'}},preventDefault(){prevented=true;}});
    assert.equal(prevented, true);
});


test('manual polling compares the component root even when invoked from a button', async () => {
    const state = component();
    state.$el = { dataset: {} };
    globalThis.fetch = async () => ({ok:true, json:async () => ({status:'running', provisioning:'ready', generation:2, locked:false, operation:null})});
    await state.poll();
    assert.equal(state.stale, false);
});
