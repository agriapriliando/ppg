// Isolated Chrome harness: real browser File validation and HTTP transfers.
// Uses synthetic fixtures and a simulated Livewire transport, never a user's account.
import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { readFile, mkdtemp, rm, realpath } from 'node:fs/promises';
import { resolve, join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';

const project = resolve(new URL('../..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
const manifest = JSON.parse(await readFile(join(project, 'public/build/manifest.json'), 'utf8'));
const entry = manifest['resources/js/app.js'].file;
const fixtures = ['h264-aac.mp4', 'h264-portrait.mp4', 'h264-long.mp4', 'hevc.mp4', 'encrypted.mp4', 'audio-only.mp4'];
const uploads = [];
const html = `<!doctype html><html lang="id"><head><meta charset="utf-8"></head><body><p id="result">Memeriksa unggahan...</p>
<script type="module">
import '/build/${entry}';
window.runUploadTests = async () => {
    const checks = [];
    const must = (ok, message) => { if (!ok) throw new Error(message); checks.push(message); };
    const rules = { policy_version_id: 1, allowed_extensions: ['pdf', 'mp4'], max_file_size_bytes: 1048576, remaining_bytes: null, available_video_slots: null, video_extensions: ['mp4', 'mov', 'webm'] };
    function queue() {
        const q = window.fileUploadQueue(rules);
        const saved = new Set();
        let activeKey;
        q.$refs = { files: { value: '' } }; q.$dispatch = () => {};
        q.$wire = {
            frontendUploadRules: async () => rules,
            prepareFrontendFile: async key => { activeKey = key; return { saved: saved.has(key), ready: true, rules }; },
            $uploadMultiple: (property, files, success, failure, progress) => {
                fetch('/upload?name=' + encodeURIComponent(files[0].name), { method: 'POST', body: files[0] })
                    .then(response => { if (!response.ok) throw new Error('HTTP upload failed'); progress({ detail: { progress: 100 } }); success(['synthetic-temp']); }).catch(failure);
            },
            storeFrontendFile: async () => { saved.add(activeKey); return { saved: true, rules }; },
            frontendUploadStatus: async key => ({ saved: saved.has(key), rules }),
            finishFrontendUpload: async () => {}, cancelFrontendUpload: async () => {}, cancelUpload: () => {},
        };
        return q;
    }
    const invalid = queue();
    await invalid.add([new File([], 'empty.pdf'), new File(['bad'], 'bad.exe'), new File([new Uint8Array(1048577)], 'too-large.mp4')]);
    must(invalid.items.every(item => item.state === 'rejected'), 'Invalid files are rejected before any transfer');
    const mixed = queue();
    await mixed.add([new File(['test'], 'valid.pdf'), new File(['bad'], 'bad.exe')]);
    must(mixed.items[0].state === 'saved' && mixed.items[1].state === 'rejected', 'Mixed selection saves only the valid file');
    const media = queue();
    const videos = [];
    for (const name of ${JSON.stringify(fixtures)}) videos.push(new File([await (await fetch('/fixtures/' + name)).blob()], name, { type: 'video/mp4' }));
    await media.add(videos);
    for (const item of media.items) {
        const expected = 'saved';
        must(item.state === expected, item.name + ': ' + item.state + (item.error ? ' (' + item.error + ')' : ''));
    }
    must(document.querySelectorAll('video, iframe').length === 0, 'No inline player, iframe or local video preview exists');
    document.getElementById('result').textContent = 'PASSED';
    return { checks, states: media.items.map(item => ({ name: item.name, state: item.state })) };
};
</script></body></html>`;

const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST' && url.pathname === '/upload') {
            let bytes = 0;
            for await (const part of request) bytes += part.length;
            uploads.push({ name: url.searchParams.get('name'), bytes });
            response.writeHead(200, { 'Content-Type': 'application/json' }).end('{"ok":true}');
            return;
        }
        if (url.pathname === '/') { response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }).end(html); return; }
        let path;
        if (url.pathname.startsWith('/build/assets/') && /^\/build\/assets\/[a-zA-Z0-9_.-]+$/.test(url.pathname)) {
            path = join(project, 'public', url.pathname.slice(1));
            response.setHeader('Content-Type', path.endsWith('.js') ? 'text/javascript' : 'application/octet-stream');
        } else if (fixtures.includes(url.pathname.slice('/fixtures/'.length)) && url.pathname.startsWith('/fixtures/')) {
            path = join(project, 'tests/Fixtures/media', url.pathname.slice('/fixtures/'.length));
            response.setHeader('Content-Type', 'video/mp4');
        } else { response.writeHead(404).end(); return; }
        response.end(await readFile(path));
    } catch { response.writeHead(500).end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const chromePath = process.env.CHROME_PATH ?? ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
if (!chromePath) { server.close(); throw new Error('Set CHROME_PATH to an installed Chrome/Edge binary.'); }
const profile = await mkdtemp(join(tmpdir(), 'ppg-upload-browser-'));
const chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let socket;
try {
    let portFile;
    for (let count = 0; count < 100; count++) {
        try { portFile = await readFile(join(profile, 'DevToolsActivePort'), 'utf8'); break; } catch { await new Promise(resolve => setTimeout(resolve, 150)); }
    }
    if (!portFile) throw new Error('Chrome remote debugging did not start.');
    const [port, endpoint] = portFile.trim().split(/\r?\n/);
    socket = new WebSocket('ws://127.0.0.1:' + port + endpoint);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    const pending = new Map(); let counter = 0; let networkTransfers = 0;
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.id) {
            const callback = pending.get(message.id); pending.delete(message.id);
            if (message.error) callback?.reject(new Error(message.error.message)); else callback?.resolve(message.result);
        } else if (message.method === 'Network.requestWillBeSent' && message.params.request.url.includes('/upload?name=')) networkTransfers++;
    };
    const call = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++counter;
        pending.set(id, { resolve, reject }); socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await call('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await call('Target.attachToTarget', { targetId, flatten: true });
    await call('Network.enable', {}, sessionId);
    await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
    await call('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port }, sessionId);
    const result = await call('Runtime.evaluate', { expression: `new Promise((resolve, reject) => { let n=0; const poll=()=>{ if(window.runUploadTests) window.runUploadTests().then(resolve,reject); else if(++n>200) reject(new Error('Harness did not load')); else setTimeout(poll,50); }; poll(); })`, awaitPromise: true, returnByValue: true }, sessionId);
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
    assert.deepEqual(uploads.map(item => item.name), ['valid.pdf', ...fixtures]);
    assert.equal(networkTransfers, 1 + fixtures.length);
    console.log(JSON.stringify({ result: 'passed', browser: 'Chrome/Edge headless, mobile viewport 390x844', checks: result.result.value.checks, network_transfers: networkTransfers, transferred_files: uploads.map(item => item.name) }, null, 2));
    await call('Browser.close');
} finally {
    socket?.close();
    chrome.kill();
    server.closeAllConnections(); server.close();
    await new Promise(resolve => setTimeout(resolve, 500));
    // Delete only the isolated directory created above, never an existing browser profile.
    const target = await realpath(profile);
    if (dirname(target).toLowerCase() !== (await realpath(tmpdir())).toLowerCase() || !target.includes('ppg-upload-browser-')) throw new Error('Unexpected browser profile path; cleanup refused.');
    await rm(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 300 });
}
