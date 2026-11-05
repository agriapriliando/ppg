// Draf berada di memori tab; data pribadi tidak disalin ke browser storage.
globalThis.applicantAutosave = function (config) {
    return {
        draftVersion: config.version, saveState: 'saved', savedTime: '', saveMessage: '',
        pendingChanges: {}, acknowledged: {}, activeBatch: null, sending: false,
        timer: null, retryTimer: null, retryCount: 0, stopped: false, navigating: false,
        init() {
            this.leaveGuard = (event) => {
                if (this.hasUnsavedChanges()) { event.preventDefault(); event.returnValue = ''; }
            };
            window.addEventListener('beforeunload', this.leaveGuard);
            this.$nextTick(() => { if (!config.locked) this.captureChanges(true); });
        },
        destroy() {
            clearTimeout(this.timer); clearTimeout(this.retryTimer);
            window.removeEventListener('beforeunload', this.leaveGuard);
        },
        hasUnsavedChanges() { return !!this.activeBatch || Object.keys(this.pendingChanges).length > 0; },
        createBatchId() {
            const cryptoApi = globalThis.crypto;
            if (typeof cryptoApi?.randomUUID === 'function') return cryptoApi.randomUUID();
            if (typeof cryptoApi?.getRandomValues !== 'function') {
                throw new Error('Browser belum mendukung penyimpanan otomatis. Gunakan browser terbaru. Isian Anda belum tersimpan.');
            }
            // getRandomValues also works on HTTP; preserve UUID v4 version and variant bits.
            const bytes = cryptoApi.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
            return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        },
        captureChanges(immediate = false) {
            if (config.locked || this.stopped) return;
            for (const input of this.$refs.applicationForm.querySelectorAll('[name]')) {
                const path = input.name.replace(/\[([^\]]+)\]/g, '.$1');
                if (!config.fields.includes(path.split('.')[0]) || (input.type === 'radio' && !input.checked)) continue;
                const value = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value;
                const baseline = this.activeBatch && Object.hasOwn(this.activeBatch.changes, path) ? this.activeBatch.changes[path] : this.acknowledged[path];
                if (baseline !== value) this.pendingChanges[path] = value;
                else delete this.pendingChanges[path];
            }
            if (!this.hasUnsavedChanges()) return;
            this.saveState = 'pending';
            clearTimeout(this.timer);
            if (immediate) return this.flushDraft();
            this.timer = setTimeout(() => this.flushDraft(), 700);
        },
        async flushDraft() {
            clearTimeout(this.timer); clearTimeout(this.retryTimer);
            if (config.locked || this.stopped) return false;
            if (this.sending) { await this.inFlight; return this.flushDraft(); }
            if (!this.activeBatch && !Object.keys(this.pendingChanges).length) return true;
            if (!this.activeBatch) {
                let batchId;
                try { batchId = this.createBatchId(); }
                catch (error) {
                    this.stopped = true; this.saveState = 'blocked'; this.saveMessage = error.message;
                    return false;
                }
                this.activeBatch = { version: this.draftVersion, batch_id: batchId, changes: { ...this.pendingChanges } };
                this.pendingChanges = {};
            }
            this.sending = true; this.saveState = 'saving';
            this.inFlight = this.sendBatch();
            const success = await this.inFlight;
            this.sending = false;
            if (success && Object.keys(this.pendingChanges).length) return this.flushDraft();
            return success;
        },
        async sendBatch() {
            try {
                const response = await fetch(config.url, {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                    body: JSON.stringify(this.activeBatch),
                });
                if (!response.ok) {
                    if ([401, 419].includes(response.status)) { this.stopped = true; this.saveState = 'expired'; throw new Error('Sesi berakhir. Masuk kembali untuk menyimpan.'); }
                    if (response.status === 409) { this.stopped = true; this.saveState = 'conflict'; throw new Error('Draf berubah di tab/perangkat lain. Salin isian yang belum tersimpan sebelum memuat ulang.'); }
                    if ([403, 413, 422].includes(response.status)) { this.stopped = true; this.saveState = 'blocked'; throw new Error('Penyimpanan ditolak. Periksa status pendaftaran dan isian Anda.'); }
                    throw new Error('Belum tersimpan. Periksa koneksi lalu coba lagi.');
                }
                const result = await response.json();
                if (result.batch_id !== this.activeBatch.batch_id) throw new Error('Konfirmasi penyimpanan tidak sesuai. Coba kembali.');
                Object.assign(this.acknowledged, this.activeBatch.changes);
                this.draftVersion = result.version;
                this.$refs.applicationForm.querySelector('[name="version"]').value = result.version;
                this.activeBatch = null; this.retryCount = 0;
                this.saveState = 'saved'; this.savedTime = result.saved_at; this.saveMessage = '';
                return true;
            } catch (error) {
                this.saveMessage = error.message;
                if (!this.stopped) {
                    this.saveState = 'failed';
                    const delays = [1000, 2000, 5000];
                    if (this.retryCount < delays.length) this.retryTimer = setTimeout(() => this.flushDraft(), delays[this.retryCount++] + Math.random() * 250);
                }
                return false;
            }
        },
        async moveStep(target) {
            if (!config.locked) { this.captureChanges(); if (!await this.flushDraft()) return; }
            this.step = Math.max(1, Math.min(5, target));
        },
        async goToDocuments() {
            if (this.navigating) return;
            this.navigating = true;
            if (!config.locked) { this.captureChanges(); if (!await this.flushDraft()) { this.navigating = false; return; } }
            window.location.assign(config.documentsUrl);
        },
    };
};
