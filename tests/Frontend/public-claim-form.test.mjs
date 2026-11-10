import test from 'node:test';
import assert from 'node:assert/strict';
import { publicClaimForm } from '../../resources/js/public-claim-form.js';

const options = { identifier: '000001', phone: '081234567890', lookupUrl: '/lookup', csrf: 'test' };
const response = (participants) => ({ ok: true, json: async () => ({ participants }) });
const participant = { period_id: 4, period_name: 'Periode PPG', name: 'Nama Sesuai SK' };

test('normalizes phone numbers, fills one match, and requires consent before sending', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    t.mock.method(globalThis, 'fetch', async (_url, request) => {
        assert.deepEqual(JSON.parse(request.body), { identifier: '000001', whatsapp_number: '6281234567890' });
        return response([participant]);
    });
    const form = publicClaimForm(options);
    form.changed();
    assert.equal(form.phone, '6281234567890');
    assert.equal(form.ready, false);
    await form.lookup();
    assert.equal(form.name, 'Nama Sesuai SK');
    assert.equal(form.periodName, 'Periode PPG');
    assert.equal(form.periodId, '4');
    assert.equal(form.ready, true);
    let prevented = 0;
    form.submit({ preventDefault() { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal(form.showConfirmation, true);
    form.agreed = true;
    form.submit({ preventDefault() { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal(form.submitting, true);
    form.destroy();
});

test('requires a period choice for multiple matches and updates the corresponding name', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => response([participant, { period_id: 5, period_name: 'Periode Kedua', name: 'Nama SK Kedua' }]));
    const form = publicClaimForm(options);
    await form.lookup();
    assert.equal(form.ready, false);
    assert.equal(form.name, '');
    form.periodId = '5';
    form.selectPeriod();
    assert.equal(form.name, 'Nama SK Kedua');
    assert.equal(form.ready, true);
});

test('invalidates old matches immediately while typing and discards stale lookup responses', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    let release;
    t.mock.method(globalThis, 'fetch', () => new Promise(resolve => { release = resolve; }));
    const form = publicClaimForm(options);
    form.changed();
    const pending = form.lookup();
    form.name = 'Nama Lama';
    form.periodId = '4';
    form.agreed = true;
    form.showConfirmation = true;
    form.identifier = '000002';
    form.changed();
    assert.equal(form.name, '');
    assert.equal(form.periodId, '');
    assert.equal(form.agreed, false);
    assert.equal(form.showConfirmation, false);
    assert.equal(form.ready, false);
    release(response([participant]));
    await pending;
    assert.equal(form.name, '');
    assert.equal(form.participants.length, 0);
    form.destroy();
});

test('shows validation errors and allows retrying the lookup', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, status: 422, json: async () => ({ errors: { identifier: ['PEG ID tidak ditemukan.'] } }) }));
    const form = publicClaimForm(options);
    await form.lookup();
    assert.equal(form.error, 'PEG ID tidak ditemukan.');
    assert.equal(form.ready, false);
    form.retry();
    assert.equal(form.error, '');
    assert.equal(form.loading, true);
    form.destroy();
});

for (const [status, message] of [[419, /Sesi formulir berakhir/], [429, /Terlalu banyak pencarian/]]) {
    test(`explains HTTP ${status} without allowing submission`, async (t) => {
        t.mock.method(globalThis, 'fetch', async () => ({ status }));
        const form = publicClaimForm(options);
        await form.lookup();
        assert.match(form.error, message);
        assert.equal(form.ready, false);
    });
}

test('explains a network failure in Indonesian and keeps submission disabled', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => { throw new TypeError('Failed to fetch'); });
    const form = publicClaimForm(options);
    await form.lookup();
    assert.match(form.error, /Periksa koneksi internet/);
    assert.equal(form.ready, false);
    assert.equal(form.loading, false);
});

test('keeps submit disabled until both inputs are valid and avoids premature lookup', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const fetch = t.mock.method(globalThis, 'fetch', async () => response([participant]));
    const form = publicClaimForm({ ...options, phone: '08' });
    form.changed();
    t.mock.timers.tick(1000);
    assert.equal(fetch.mock.callCount(), 0);
    assert.equal(form.ready, false);
    form.destroy();
});
