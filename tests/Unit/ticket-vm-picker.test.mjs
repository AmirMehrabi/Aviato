import assert from 'node:assert/strict';
import { test } from 'node:test';
import { initTicketVmPickers } from '../../resources/js/ticket-vm-picker.js';

function setup() {
    const customer = { value: '1', addEventListener: (_, handler) => { customer.change = handler; } };
    const select = {
        value: '10', options: [], attributes: {}, disabled: false,
        replaceChildren(...options) { this.options = options; this.value = ''; },
        add(option) { this.options.push(option); },
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
    };
    const status = { textContent: '' };
    const retry = { hidden: true, addEventListener: (_, handler) => { retry.click = handler; } };
    const requests = [];
    globalThis.document = { querySelectorAll: () => [{
        dataset: { url: '/tickets/virtual-machines' },
        closest: () => ({ querySelector: () => customer }),
        querySelector: (selector) => ({ select, '[data-vm-status]': status, '[data-vm-retry]': retry })[selector],
    }] };
    globalThis.window = { location: { origin: 'https://admin.localhost' } };
    globalThis.Option = class { constructor(text, value) { this.text = text; this.value = value; } };
    globalThis.fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    initTicketVmPickers();
    return { customer, select, status, retry, requests };
}

const respond = (request, machines) => request.resolve({ ok: true, json: async () => ({ virtual_machines: machines }) });

test('changing customers clears the selection and ignores an older response', async () => {
    const { customer, select, requests } = setup();
    assert.equal(select.value, '10', 'Initialization must preserve the server-rendered selection');
    const first = customer.change();
    assert.equal(select.value, '');
    assert.equal(select.disabled, true);
    customer.value = '2';
    const second = customer.change();
    assert.equal(requests[0].options.signal.aborted, true);
    assert.equal(requests[1].url.searchParams.get('customer_id'), '2');
    respond(requests[1], [{ id: 20, label: 'New customer server' }]);
    await second;
    respond(requests[0], [{ id: 10, label: 'Old customer server' }]);
    await first;
    assert.deepEqual(select.options.map((option) => option.value), ['', '20']);
    assert.equal(select.disabled, false);
    assert.equal(select.attributes['aria-busy'], 'false');
});

test('a failed request offers retry and an empty result allows an unlinked ticket', async () => {
    const { customer, select, retry, status, requests } = setup();
    const first = customer.change();
    requests[0].reject(new Error('Offline'));
    await first;
    assert.equal(retry.hidden, false);
    assert.equal(select.disabled, true);
    const retried = retry.click();
    respond(requests[1], []);
    await retried;
    assert.equal(retry.hidden, true);
    assert.equal(select.disabled, true);
    assert.match(status.textContent, /بدون ماشین مجازی/);
});

test('clearing the customer cancels pending results without showing an error', async () => {
    const { customer, select, retry, requests } = setup();
    const pending = customer.change();
    customer.value = '';
    await customer.change();
    requests[0].reject(new Error('Aborted'));
    await pending;
    assert.equal(requests.length, 1);
    assert.equal(retry.hidden, true);
    assert.equal(select.disabled, true);
    assert.deepEqual(select.options.map((option) => option.value), ['']);
});
