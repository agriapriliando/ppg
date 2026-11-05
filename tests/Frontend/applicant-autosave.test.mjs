import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { randomFillSync } from 'node:crypto';
import { runInNewContext } from 'node:vm';
import '../../public/js/applicant-autosave.js';

const setup = (factory = globalThis.applicantAutosave) => {
    const inputs = [{ name: 'full_name', type: 'text', value: 'Nama Awal' }, { name: 'nik', type: 'text', value: '123' }];
    const version = { value: 0 };
    const state = factory({ version: 0, url: '/draft', csrf: 'test', fields: ['full_name', 'nik'], documentsUrl: '/documents' });
    state.$refs = { applicationForm: { querySelectorAll: () => inputs, querySelector: () => version } };
    state.acknowledged = { full_name: 'Nama Awal', nik: '123' };
    return { state, inputs, version };
};

test('saves and moves to the next step on HTTP without crypto.randomUUID', async () => {
    const batches = [];
    const sandbox = {
        crypto: { getRandomValues: array => randomFillSync(array) },
        setTimeout, clearTimeout,
        fetch: async (_url, request) => {
            const batch = JSON.parse(request.body); batches.push(batch);
            assert.match(batch.batch_id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
            return { ok: true, json: async () => ({ batch_id: batch.batch_id, version: batches.length, saved_at: '10:00' }) };
        },
    };
    runInNewContext(readFileSync(new URL('../../public/js/applicant-autosave.js', import.meta.url), 'utf8'), sandbox);
    const { state, inputs } = setup(sandbox.applicantAutosave);
    inputs[0].value = 'Nama HTTP';
    state.step = 1;
    await state.moveStep(2);
    assert.equal(state.step, 2);
    assert.equal(state.saveState, 'saved');
    assert.equal(state.hasUnsavedChanges(), false);
    inputs[1].value = '1234';
    await state.moveStep(3);
    assert.equal(state.step, 3);
    assert.notEqual(batches[0].batch_id, batches[1].batch_id);
});

test('keeps changes and reports a visible error if crypto is unavailable', async () => {
    const sandbox = { setTimeout, clearTimeout };
    runInNewContext(readFileSync(new URL('../../public/js/applicant-autosave.js', import.meta.url), 'utf8'), sandbox);
    const { state, inputs } = setup(sandbox.applicantAutosave);
    inputs[0].value = 'Belum Disimpan';
    state.captureChanges();
    assert.equal(await state.flushDraft(), false);
    assert.equal(state.saveState, 'blocked');
    assert.equal(state.hasUnsavedChanges(), true);
    assert.match(state.saveMessage, /belum tersimpan/);
});

test('groups changed fields and waits for the debounce before saving', async () => {
    const { state, inputs } = setup();
    let calls = 0;
    globalThis.fetch = async (_url, request) => {
        calls++;
        const batch = JSON.parse(request.body);
        assert.deepEqual(batch.changes, { full_name: 'Nama Baru', nik: '1234' });
        return { ok: true, json: async () => ({ batch_id: batch.batch_id, version: 1, saved_at: '10:00' }) };
    };
    inputs[0].value = 'Nama Baru'; state.captureChanges();
    inputs[1].value = '1234'; state.captureChanges();
    assert.equal(calls, 0);
    await new Promise(resolve => setTimeout(resolve, 850));
    assert.equal(calls, 1);
    assert.equal(state.saveState, 'saved');
    assert.equal(state.hasUnsavedChanges(), false);
});

test('queues typing during a save and does not replace it with an old response', async () => {
    const { state, inputs } = setup();
    let release;
    const requests = [];
    globalThis.fetch = async (_url, request) => {
        const batch = JSON.parse(request.body); requests.push(batch);
        if (requests.length === 1) await new Promise(resolve => { release = resolve; });
        return { ok: true, json: async () => ({ batch_id: batch.batch_id, version: requests.length, saved_at: '10:01' }) };
    };
    inputs[0].value = 'Nama Pertama'; state.captureChanges();
    const saving = state.flushDraft();
    inputs[0].value = 'Nama Terbaru'; state.captureChanges();
    release(); await saving;
    assert.equal(requests.length, 2);
    assert.equal(requests[1].version, 1);
    assert.equal(requests[1].changes.full_name, 'Nama Terbaru');
    assert.equal(inputs[0].value, 'Nama Terbaru');
    assert.equal(state.hasUnsavedChanges(), false);
});

test('retries an unknown network result with the same batch ID', async () => {
    const { state, inputs } = setup();
    let firstId;
    globalThis.fetch = async (_url, request) => {
        firstId = JSON.parse(request.body).batch_id;
        throw new Error('Koneksi gagal');
    };
    inputs[0].value = 'Perubahan'; state.captureChanges();
    assert.equal(await state.flushDraft(), false);
    assert.equal(state.saveState, 'failed');
    assert.equal(state.hasUnsavedChanges(), true);
    globalThis.fetch = async (_url, request) => {
        const batch = JSON.parse(request.body);
        assert.equal(batch.batch_id, firstId);
        return { ok: true, json: async () => ({ batch_id: batch.batch_id, version: 1, saved_at: '10:02' }) };
    };
    assert.equal(await state.flushDraft(), true);
});

test('stops on a tab conflict and blocks navigation without erasing local input', async () => {
    const { state, inputs } = setup();
    globalThis.fetch = async () => ({ ok: false, status: 409 });
    inputs[0].value = 'Belum Tersimpan'; state.captureChanges();
    assert.equal(await state.flushDraft(), false);
    assert.equal(state.stopped, true);
    assert.equal(state.saveState, 'conflict');
    assert.equal(state.hasUnsavedChanges(), true);
    assert.equal(inputs[0].value, 'Belum Tersimpan');
    assert.equal(await state.flushDraft(), false);
});
