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
