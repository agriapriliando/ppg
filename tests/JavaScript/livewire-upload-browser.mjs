// Real local Laravel/Livewire upload test in an isolated headless Chrome profile.
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { existsSync } from 'node:fs';
import { readFile, writeFile, mkdir, mkdtemp, rm, realpath } from 'node:fs/promises';
import { resolve, join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import assert from 'node:assert/strict';

const project = resolve(new URL('../..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
const run = promisify(execFile);
const root = await mkdtemp(join(tmpdir(), 'ppg-upload-browser-'));
const workflow = process.argv[2] ?? 'applicant';
assert.ok(['applicant', 'staff', 'period'].includes(workflow));
const contextPath = join(root, 'context.json');
const environment = { ...process.env, PPG_UPLOAD_SMOKE_CONTEXT: contextPath, PPG_UPLOAD_SMOKE_WORKFLOW: workflow };
const php = ['artisan', 'tinker', '--execute=require base_path(\'tests/JavaScript/prepare-upload-browser.php\');'];
let context; let chrome; let socket; let call;
try {
    await run('php', php, { cwd: project, env: environment, windowsHide: true });
    context = JSON.parse(await readFile(contextPath, 'utf8'));
    await writeFile(join(root, 'valid.pdf'), '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF');
    await writeFile(join(root, 'invalid.exe'), 'synthetic test text');
    await writeFile(join(root, 'empty.pdf'), '');
    const chromePath = process.env.CHROME_PATH ?? ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
    if (!chromePath) throw new Error('Set CHROME_PATH to Chrome or Edge.');
    const profile = join(root, 'profile');
    chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
    let portFile;
    for (let count = 0; count < 100; count++) {
        try { portFile = await readFile(join(profile, 'DevToolsActivePort'), 'utf8'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    if (!portFile) throw new Error('Chrome did not start.');
    const [port, endpoint] = portFile.trim().split(/\r?\n/);
    socket = new WebSocket('ws://127.0.0.1:' + port + endpoint);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    const pending = new Map(); let counter = 0; const requests = []; const errors = [];
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.id) {
            const callback = pending.get(message.id); pending.delete(message.id);
            message.error ? callback?.reject(new Error(message.error.message)) : callback?.resolve(message.result);
        } else if (message.method === 'Network.requestWillBeSent' && message.params.request.method === 'POST' && message.params.request.url.includes('/upload-file')) {
            requests.push(message.params.request.url);
        } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description ?? message.params.exceptionDetails.text);
    };
    call = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++counter; const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout: ' + method)); }, 70000); pending.set(id, { resolve: value => { clearTimeout(timer); resolve(value); }, reject: error => { clearTimeout(timer); reject(error); } });
        socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await call('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await call('Target.attachToTarget', { targetId, flatten: true });
    await call('Network.enable', {}, sessionId); await call('Runtime.enable', {}, sessionId);
    await call('Network.setCookie', { name: context.cookie_name, value: context.cookie_value, domain: 'ppg.test', path: '/', httpOnly: true, sameSite: 'Lax' }, sessionId);
    await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true }, sessionId);
    const page = workflow === 'period' ? '/admin/periode' : workflow === 'staff' ? '/admin/berkas/workspace/' + context.workspace_id : '/pendaftar/berkas/' + context.period_id;
    await call('Page.navigate', { url: 'http://ppg.test' + page }, sessionId);
    const evaluate = async expression => {
        const value = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId);
        if (value.exceptionDetails) throw new Error(value.exceptionDetails.exception?.description ?? value.exceptionDetails.text);
        return value.result.value;
    };
    if (workflow === 'period') {
        await evaluate(`new Promise((resolve,reject)=>{let n=0;const poll=()=>{const root=Array.from(document.getElementsByTagName('*')).find(el=>el.hasAttribute('wire:id'));if(window.Livewire&&root&&Livewire.find(root.getAttribute('wire:id')))resolve(true);else if(++n>200)reject(new Error('Period page did not load'));else setTimeout(poll,100)};poll()})`);
        await evaluate(`Livewire.find(Array.from(document.getElementsByTagName('*')).find(el=>el.hasAttribute('wire:id')).getAttribute('wire:id')).manageDocuments(${context.period_id})`);
    }
    await evaluate(`new Promise((resolve,reject)=>{let n=0;const poll=()=>{const el=document.querySelector('[data-file-upload-queue]');if(el&&window.Alpine&&Alpine.$data(el).add)resolve(true);else if(++n>200)reject(new Error('Upload UI did not initialize: '+location.pathname));else setTimeout(poll,100)};poll()})`);
    await call('DOM.enable', {}, sessionId);
    const { root: domRoot } = await call('DOM.getDocument', {}, sessionId);
    const { nodeId } = await call('DOM.querySelector', { nodeId: domRoot.nodeId, selector: '[data-file-upload-queue] input[type=file]' }, sessionId);
    await call('DOM.setFileInputFiles', { nodeId, files: [join(root, 'valid.pdf'), join(root, 'invalid.exe'), join(root, 'empty.pdf'), join(project, 'tests/Fixtures/media/hevc.mp4'), join(project, 'tests/Fixtures/media/sample.mov'), join(project, 'tests/Fixtures/media/sample.webm')] }, sessionId);
    const result = await evaluate(`new Promise((resolve,reject)=>{let n=0;const poll=()=>{const q=Alpine.$data(document.querySelector('[data-file-upload-queue]'));if(q.items.length===6&&!q.busy)resolve({items:q.items.map(i=>({name:i.name,state:i.state,error:i.error})),error:q.error,overflow:document.documentElement.scrollWidth>innerWidth});else if(++n>600)reject(new Error('Upload timeout: '+JSON.stringify(q.items.map(i=>({state:i.state,error:i.error})))));else setTimeout(poll,100)};poll()})`);
    assert.deepEqual(result.items.map(item => item.state), ['saved', 'rejected', 'rejected', 'saved', 'saved', 'saved'], JSON.stringify({ result, errors }));
    assert.equal(result.overflow, false, 'Upload UI should not overflow the mobile viewport');
    assert.equal(requests.length, 4, 'Only four valid files may reach Livewire temporary upload');
    assert.equal(errors.length, 0, JSON.stringify(errors));
    const links = await evaluate(`Array.from(document.querySelectorAll('a')).filter(a=>a.textContent.includes('Buka Video')).map(a=>({target:a.target,rel:a.rel,href:a.href}))`);
    assert.ok(links.length > 0); assert.equal(links[0].target, '_blank');
    assert.ok(links[0].rel.includes('noopener'));
    await evaluate(`document.querySelector('[data-file-upload-queue]').scrollIntoView({block:'start'});true`);
    const screenshot = await call('Page.captureScreenshot', { format: 'png' }, sessionId);
    const artifacts = join(project, 'storage/framework/testing');
    await mkdir(artifacts, { recursive: true });
    await writeFile(join(artifacts, 'video-upload-' + workflow + '-mobile.png'), Buffer.from(screenshot.data, 'base64'));
    console.log(JSON.stringify({ result: 'passed', workflow, browser: 'Chrome, real Laravel/Livewire, mobile viewport 390x844', valid_files_stored: 4, rejected_files_transferred: 0, temporary_upload_requests: requests.length, new_tab_video_links: links.length, horizontal_overflow: result.overflow }, null, 2));
    await call('Browser.close');
} finally {
    socket?.close(); chrome?.kill();
    if (context) {
        const cleanup = await run('php', php, { cwd: project, env: { ...environment, PPG_UPLOAD_SMOKE_CLEANUP: '1' }, windowsHide: true });
        console.log(cleanup.stdout.trim());
    }
    await new Promise(resolve => setTimeout(resolve, 500));
    const target = await realpath(root);
    if (dirname(target).toLowerCase() !== (await realpath(tmpdir())).toLowerCase() || !target.includes('ppg-upload-browser-')) throw new Error('Unexpected test directory; cleanup refused.');
    await rm(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 300 });
}
