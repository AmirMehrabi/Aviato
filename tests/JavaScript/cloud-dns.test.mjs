import test from 'node:test';
import assert from 'node:assert/strict';
import { parseValue, serializeValues, cloudDns } from '../../resources/js/cloud-dns.js';

for (const [type, content] of Object.entries({ A: '192.0.2.1', AAAA: '2001:db8::1', CNAME: 'example.com.', MX: '10 mail.example.com.', TXT: '"a\\"b" "part two"', CAA: '0 issue "letsencrypt.org"', SRV: '10 5 5060 sip.example.com.', NS: 'ns.example.com.' })) {
    test(`${type} editing preserves DNS content`, () => assert.equal(serializeValues(type, [parseValue(type, content)]), content));
}
test('multiple values remain separate records', () => assert.equal(serializeValues('MX', [{ priority: '10', value: 'mx1.example.com' }, { priority: '20', value: 'mx2.example.com' }]), '10 mx1.example.com\n20 mx2.example.com'));
test('new record cannot silently replace an existing set', () => {
    const state = cloudDns({ zone: 'example.com', records: [{ name: 'example.com.', type: 'A', records: ['192.0.2.1'] }] });
    state.values = [{ value: '192.0.2.2' }];
    state.save({ target: { submit() { assert.fail('Must not overwrite existing set'); } } });
    assert.match(state.error, /از قبل وجود دارد/);
});
test('embedded newlines cannot introduce extra records', () => {
    const state = cloudDns({ zone: 'example.com', records: [] });
    state.values = [{ value: '192.0.2.1\n192.0.2.2' }];
    state.save({ target: { submit() { assert.fail('Must not submit'); } } });
    assert.ok(state.error);
});
test('search includes record contents and type filter', () => {
    const state = cloudDns({ records: [{ type: 'MX', name: 'example.com.', records: ['10 mail.example.com.'] }, { type: 'A', name: 'example.com.', records: ['192.0.2.1'] }] });
    state.search = 'MAIL'; assert.equal(state.filtered.length, 1);
    state.filter = 'A'; assert.equal(state.filtered.length, 0);
});

test('record type changes choose useful defaults and retain a custom owner', () => {
    const state = cloudDns({ zone: 'example.com', records: [] });
    state.type = 'SRV'; state.changeType();
    assert.equal(state.name, '_sip._tcp');
    assert.equal(state.values[0].priority, '10');
    state.type = 'TXT'; state.changeType();
    assert.equal(state.name, '@');
    state.name = '_dmarc'; state.type = 'CNAME'; state.changeType();
    assert.equal(state.name, '_dmarc');
});
test('record sorting changes direction without changing source records', () => {
    const state = cloudDns({ records: [{ type: 'TXT', name: 'z.example.com.', records: ['hello'] }, { type: 'A', name: 'a.example.com.', records: ['192.0.2.1'] }] });
    assert.equal(state.filtered[0].type, 'A');
    state.sortBy('type'); assert.equal(state.filtered[0].type, 'TXT');
    state.sortBy('name'); assert.equal(state.filtered[0].name, 'a.example.com.');
    assert.equal(state.records[0].type, 'TXT');
});


test('short and full names normalize to the same preview', () => {
    const state = cloudDns({ zone: 'example.com', records: [] });
    for (const name of ['blog', 'blog.example.com', ' BLOG.EXAMPLE.COM ']) {
        state.name = name; assert.equal(state.fqdn, 'blog.example.com');
    }
    state.name = 'blog.example.com.'; assert.equal(state.fqdn, 'blog.example.com.');
    state.name = '@'; assert.equal(state.fqdn, 'example.com');
});
test('root NS is rejected before submitting', () => {
    const state = cloudDns({ zone: 'example.com', records: [] });
    state.type = 'NS'; state.name = 'example.com'; state.values = [{ value: 'ns1.example.net' }];
    state.save({ target: { submit() { assert.fail('Must reject root NS'); } } });
    assert.match(state.error, /NS/);
});
test('adding another NS opens existing set and preserves nameservers', (t) => {
    const originalDocument = globalThis.document;
    t.after(() => { if (originalDocument === undefined) delete globalThis.document; else globalThis.document = originalDocument; });
    const state = cloudDns({ zone: 'example.com', records: [{ name: 'blog.example.com.', type: 'NS', ttl: 600, records: ['ns1.example.net.'] }] });
    state.$nextTick = () => {};
    globalThis.document = { activeElement: null };
    state.type = 'NS'; state.name = 'blog.example.com'; state.values = [{ value: 'ns2.example.net' }];
    state.save({ target: { submit() { assert.fail('Must review merged set'); } } });
    assert.equal(state.editing, true); assert.equal(state.ttl, 600);
    assert.deepEqual(state.values.map(v => v.value), ['ns1.example.net.', 'ns2.example.net']);
});
test('DNS publication alone cannot complete migration checklist', () => {
    const state = cloudDns({ records: [], connection: { published: true } });
    assert.equal(state.migrationReviewed, false);
    state.emptyDomain = true; assert.equal(state.migrationReviewed, false);
    state.dnssecReviewed = true; assert.equal(state.migrationReviewed, true);
});

test('status polling flags changed records and preserves last state on failure', async () => {
    const originalFetch = globalThis.fetch;
    const state = cloudDns({ zone: 'example.com', records: [], statusUrl: '/status', connection: { revision: 1, healthy: false } });
    state.recordsReviewed = true;
    try {
        globalThis.fetch = async () => ({ ok: true, json: async () => ({ revision: 2, healthy: true }) });
        await state.refreshStatus();
        assert.equal(state.recordsStale, true);
        assert.equal(state.recordsReviewed, false);
        assert.equal(state.connection.healthy, true);
        globalThis.fetch = async () => { throw new Error('offline'); };
        await state.refreshStatus();
        assert.equal(state.connection.healthy, true);
        assert.equal(state.refreshing, false);
        assert.ok(state.statusError);
    } finally { globalThis.fetch = originalFetch; }
});

test('editing preserves multiple values and a custom TTL, then adding starts fresh', (t) => {
    const originalDocument = globalThis.document;
    globalThis.document = { activeElement: null };
    t.after(() => { if (originalDocument === undefined) delete globalThis.document; else globalThis.document = originalDocument; });
    const record = { id: 42, type: 'MX', name: 'example.com.', ttl: 720, records: ['10 mx1.example.com.', '20 mx2.example.com.'] };
    const state = cloudDns({ zone: 'example.com', records: [record] });
    state.$nextTick = () => {};
    state.openEditor(record);
    assert.equal(state.editingRecord.id, 42);
    assert.equal(state.ttl, 720);
    assert.equal(state.ttlChoice, 'custom');
    assert.equal(serializeValues(state.type, state.values), record.records.join('\n'));
    state.openEditor();
    assert.equal(state.editingRecord, null);
    assert.equal(state.editing, false);
    assert.equal(state.type, 'A');
    assert.equal(state.name, '@');
    assert.equal(state.ttl, 300);
    assert.equal(state.ttlChoice, '300');
    assert.equal(state.values[0].value, '');
});
