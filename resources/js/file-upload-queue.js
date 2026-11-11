import { fileExtension, validateFile } from './file-upload-validator.js';

function uploadId() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function fileUploadQueue(initialRules) {
    return {
        rules: initialRules, items: [], busy: false, drag: false, error: '', progress: 0,
        _files: new Map(), _controller: null, _generation: 0, _uploadReject: null,
        init() {
            this._navigate = () => { if (this.busy) void this.cancel(); };
            this._refreshRules = async () => {
                if (!this.busy) {
                    try { this.rules = await this.$wire.frontendUploadRules(); } catch { /* retain last displayed rules; preflight will check again */ }
                }
            };
            window.addEventListener('livewire:navigating', this._navigate);
            window.addEventListener('registration-upload-cancelled', this._navigate);
            window.addEventListener('registration-documents-changed', this._refreshRules);
            window.addEventListener('period-documents-updated', this._refreshRules);
        },
        destroy() {
            window.removeEventListener('livewire:navigating', this._navigate);
            window.removeEventListener('registration-upload-cancelled', this._navigate);
            window.removeEventListener('registration-documents-changed', this._refreshRules);
            window.removeEventListener('period-documents-updated', this._refreshRules);
            this._controller?.abort();
            this._generation++;
        },
        announce(busy) { this.$dispatch('registration-uploading', { busy }); },
        add(files) {
            for (const file of [...(files ?? [])]) {
                const id = uploadId();
                this._files.set(id, file);
                this.items.push({ id, name: file.name, size: file.size, state: 'queued', error: '', metadata: null, policyId: null, inspectionStamp: null });
            }
            if (this.$refs?.files) this.$refs.files.value = '';
            if (!this.busy) return this.process();
        },
        async checkPending() {
            let reservedBytes = 0;
            let reservedVideos = 0;
            for (const item of this.items) {
                if (!['queued', 'ready'].includes(item.state)) continue;
                const file = this._files.get(item.id);
                try {
                    item.state = 'checking';
                    const extension = validateFile(file, this.rules, reservedBytes, reservedVideos);
                    this._controller.signal.throwIfAborted();
                    item.policyId = this.rules.policy_version_id;
                    reservedBytes += file.size;
                    if (this.rules.video_extensions.includes(extension)) reservedVideos++;
                    item.state = 'ready';
                    item.error = '';
                } catch (error) {
                    if (this._controller.signal.aborted) throw error;
                    item.state = 'rejected';
                    item.error = error?.message || 'Berkas belum dapat diperiksa.';
                }
            }
        },
        transfer(file, generation) {
            return new Promise((resolve, reject) => {
                this._uploadReject = reject;
                this.$wire.$uploadMultiple('frontendFiles', [file], resolve,
                    () => reject(new Error('Transfer belum selesai. Periksa koneksi dan coba ulang file ini.')),
                    event => { if (generation === this._generation) this.progress = event.detail.progress; },
                    () => reject(new DOMException('Unggahan dibatalkan.', 'AbortError')));
            }).finally(() => { this._uploadReject = null; });
        },
        async process() {
            if (this.busy || !this.items.some(item => item.state === 'queued')) return;
            this.busy = true;
            this.announce(true);
            this.error = '';
            this._controller = new AbortController();
            const generation = ++this._generation;
            try {
                this.rules = await this.$wire.frontendUploadRules();
                while (generation === this._generation) {
                    await this.checkPending();
                    const item = this.items.find(entry => entry.state === 'ready');
                    if (!item) break;
                    const file = this._files.get(item.id);
                    try {
                        // Reconcile saved keys before transferring a retry, and refresh policy.
                        const prepared = await this.$wire.prepareFrontendFile(item.id, item.policyId, item.metadata ?? {});
                        if (generation !== this._generation) break;
                        this.rules = prepared.rules;
                        if (prepared.changed) {
                            for (const pending of this.items.filter(entry => entry.state === 'ready')) pending.state = 'queued';
                            continue;
                        }
                        if (!prepared.saved) {
                            validateFile(file, this.rules);
                            item.state = 'uploading';
                            this.progress = 0;
                            const temporaryNames = await this.transfer(file, generation);
                            if (generation !== this._generation) break;
                            if (!Array.isArray(temporaryNames) || typeof temporaryNames[0] !== 'string') throw new Error('Konfirmasi transfer belum lengkap. Coba ulang file ini.');
                            item.state = 'saving';
                            const result = await this.$wire.storeFrontendFile(temporaryNames[0]);
                            if (generation !== this._generation) break;
                            this.rules = result.rules;
                            if (!result.saved) throw new Error(result.error || 'Berkas belum tersimpan.');
                        }
                        item.state = 'saved';
                        this._files.delete(item.id);
                    } catch (error) {
                        if (generation !== this._generation) break;
                        // A missing response must not duplicate a file already saved on the server.
                        let reconciled;
                        try { reconciled = await this.$wire.frontendUploadStatus(item.id); } catch { /* retry will reconcile again */ }
                        if (generation !== this._generation) break;
                        if (reconciled?.saved) {
                            item.state = 'saved';
                            this.rules = reconciled.rules;
                            this._files.delete(item.id);
                        } else {
                            item.state = 'failed';
                            item.error = error?.message || 'Unggahan belum selesai. Coba ulang file ini.';
                            if (reconciled) this.rules = reconciled.rules;
                        }
                    }
                }
                if (generation === this._generation) {
                    await this.$wire.finishFrontendUpload(this.items.some(item => item.state === 'failed'));
                }
            } catch (error) {
                if (generation === this._generation) {
                    this.error = error?.message || 'Aturan/izin unggah belum dapat diperoleh. Muat ulang atau coba kembali.';
                    for (const item of this.items.filter(entry => ['queued', 'checking', 'ready'].includes(entry.state))) {
                        item.state = 'failed';
                        item.error = this.error;
                    }
                    try { await this.$wire.finishFrontendUpload(true); } catch { /* batch remains guarded until cancel/expiry */ }
                }
            } finally {
                if (generation === this._generation) {
                    this.busy = false;
                    this.announce(false);
                }
            }
        },
        retry(item) {
            if (this.busy || !this._files.has(item.id)) return;
            item.state = 'queued'; item.error = ''; item.policyId = null; item.inspectionStamp = null;
            return this.process();
        },
        async remove(item) {
            if (this.busy) return;
            this._files.delete(item.id);
            this.items = this.items.filter(entry => entry.id !== item.id);
            if (!this.items.some(entry => entry.state === 'failed')) {
                try { await this.$wire.finishFrontendUpload(false); } catch { this.error = 'Antrean belum dapat diselesaikan. Coba batalkan antrean.'; }
            }
        },
        async cancel() {
            ++this._generation;
            this._controller?.abort();
            this._uploadReject?.(new DOMException('Unggahan dibatalkan.', 'AbortError'));
            try { this.$wire.cancelUpload('frontendFiles'); } catch { /* upload may not have started */ }
            for (const item of this.items.filter(entry => !['saved', 'rejected'].includes(entry.state))) {
                item.state = 'failed'; item.error = 'Unggahan dibatalkan. Pilih Coba ulang untuk memulai kembali.';
            }
            try { await this.$wire.cancelFrontendUpload(); }
            catch { this.error = 'Pembatalan belum terkonfirmasi. Muat ulang untuk memeriksa status berkas.'; }
            finally { this.busy = false; this.announce(false); }
        },
        label(state) {
            return { queued: 'Menunggu pemeriksaan', checking: 'Memeriksa di perangkat; belum diunggah', rejected: 'Tidak diunggah', ready: 'Siap, menunggu giliran', uploading: 'Mengunggah', saving: 'Menyimpan di server', saved: 'Tersimpan', failed: 'Belum tersimpan' }[state];
        },
        videoInfo(item) {
            if (!this.rules.video_extensions.includes(fileExtension(item.name)) || !item.metadata) return '';
            const meta = item.metadata;
            return `${meta.client_width} × ${meta.client_height} piksel${meta.client_duration_seconds ? ` · ${(meta.client_duration_seconds / 60).toFixed(1)} menit` : ''}`;
        },
    };
}
