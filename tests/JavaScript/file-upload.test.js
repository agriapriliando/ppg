import test from 'node:test';
import assert from 'node:assert/strict';
import { validateFile } from '../../resources/js/file-upload-validator.js';
import { fileUploadQueue } from '../../resources/js/file-upload-queue.js';

const rules = () => ({ policy_version_id: 1, allowed_extensions: ['pdf', 'png', 'mp4', 'mov', 'webm'], max_file_size_bytes: 1024 * 1024,
    remaining_bytes: 2 * 1024 * 1024, available_video_slots: 1, video_extensions: ['mp4', 'mov', 'webm'] });
const file = (name, bytes = 10, type = '') => new File([new Uint8Array(bytes)], name, { type });

function queue(options = {}) {
    const calls = { prepared: 0, transfers: [], saved: new Set(), finished: [], busy: [], active: 0, maxActive: 0 };
    const instance = fileUploadQueue(options.rules ?? rules(), { validateVideo: options.validateVideo ?? (async () => ({ client_width: 320, client_height: 180 })) });
    instance.$refs = { files: { value: '' } };
    instance.$dispatch = (event, data) => { calls.busy.push(data.busy); };
    instance.$wire = {
        frontendUploadRules: async () => options.rules ?? rules(),
        prepareFrontendFile: async (id, policyId) => {
            calls.prepared++;
            return options.prepare ? options.prepare(id, policyId) : { saved: calls.saved.has(id), ready: true, rules: options.rules ?? rules() };
        },
        $uploadMultiple: (property, files, success, error) => {
            calls.transfers.push(files[0].name); calls.active++; calls.maxActive = Math.max(calls.active, calls.maxActive);
            setTimeout(() => { calls.active--; success(['synthetic-temp']); }, 1);
        },
        storeFrontendFile: async () => options.store ? options.store() : { saved: true, rules: options.rules ?? rules() },
        frontendUploadStatus: async id => ({ saved: calls.saved.has(id), rules: options.rules ?? rules() }),
        finishFrontendUpload: async failed => calls.finished.push(failed),
        cancelFrontendUpload: async () => {}, cancelUpload: () => {},
    };
    return { instance, calls };
}

test('rejects empty, oversized and unsupported files while accepting uppercase extensions and empty browser MIME', () => {
    assert.throws(() => validateFile(file('zero.pdf', 0), rules()), /kosong/);
    assert.throws(() => validateFile(file('large.mp4', 1048577), rules()), /melebihi/);
    assert.throws(() => validateFile(file('malware.exe'), rules()), /tidak didukung/);
    assert.equal(validateFile(file('IJAZAH.PDF'), rules()), 'pdf');
    assert.equal(validateFile(file('boundary.mp4', 1048576), rules()), 'mp4');
    assert.throws(() => validateFile(file('video.mp4'), rules(), 0, 1), /jumlah video/);
    assert.throws(() => validateFile(file('file.pdf', 10), { ...rules(), remaining_bytes: 9 }), /kapasitas/);
});

test('only rejected files never prepare or transfer any upload', async () => {
    const { instance, calls } = queue();
    await instance.add([file('empty.pdf', 0), file('bad.exe'), file('large.mp4', 1048577)]);
    assert.equal(calls.prepared, 0); assert.deepEqual(calls.transfers, []);
    assert.ok(instance.items.every(item => item.state === 'rejected'));
    assert.deepEqual(calls.busy, [true, false]);
});

test('mixed files retain errors and send valid same-named files serially', async () => {
    const { instance, calls } = queue();
    await instance.add([file('same.pdf'), file('bad.exe'), file('same.pdf')]);
    assert.deepEqual(calls.transfers, ['same.pdf', 'same.pdf']);
    assert.equal(calls.maxActive, 1);
    assert.notEqual(instance.items[0].id, instance.items[2].id);
    assert.deepEqual(instance.items.map(item => item.state), ['saved', 'rejected', 'saved']);
});

test('reserved aggregate capacity and video slots reject later files before transfer', async () => {
    const { instance, calls } = queue({ rules: { ...rules(), remaining_bytes: 15 } });
    await instance.add([file('first.pdf'), file('second.pdf')]);
    assert.deepEqual(calls.transfers, ['first.pdf']);
    const video = queue();
    await video.instance.add([file('first.mov'), file('second.webm')]);
    assert.deepEqual(video.calls.transfers, ['first.mov']);
});

test('a changed policy rechecks files without sending a now oversized file', async () => {
    const updated = { ...rules(), policy_version_id: 2, max_file_size_bytes: 5 };
    const { instance, calls } = queue({ prepare: async () => ({ saved: false, changed: true, rules: updated }) });
    await instance.add([file('changed.pdf', 10)]);
    assert.deepEqual(calls.transfers, []); assert.equal(instance.items[0].state, 'rejected');
});

test('a failed save is retried individually with the same stable key', async () => {
    let saves = 0;
    const { instance, calls } = queue({ store: async () => ++saves === 1 ? { saved: false, error: 'Jaringan putus', rules: rules() } : { saved: true, rules: rules() } });
    await instance.add([file('retry.pdf'), file('success.pdf')]);
    const failed = instance.items[0]; const key = failed.id;
    assert.deepEqual(instance.items.map(item => item.state), ['failed', 'saved']);
    await instance.retry(failed);
    assert.equal(failed.id, key); assert.equal(failed.state, 'saved');
    assert.deepEqual(calls.transfers, ['retry.pdf', 'success.pdf', 'retry.pdf']);
    assert.deepEqual(calls.finished, [true, false]);
});

test('a response lost after saving is reconciled without another file transfer', async () => {
    const { instance, calls } = queue();
    instance.$wire.storeFrontendFile = async () => {
        calls.saved.add(instance.items[0].id);
        throw new Error('Lost response');
    };
    await instance.add([file('already-saved.pdf')]);
    assert.equal(instance.items[0].state, 'saved'); assert.equal(calls.transfers.length, 1);
});

