@props(['rules'])
<section class="workspace-card mb-4" data-file-upload-queue wire:ignore x-data="fileUploadQueue(@js($rules))">
    <div class="panel-heading"><h2 class="h5 mb-1"><i class="bi bi-cloud-arrow-up me-2" aria-hidden="true"></i>Unggah Berkas</h2><p class="mb-0">Pilih dokumen, gambar atau video yang diizinkan. Berkas diperiksa di perangkat, lalu yang lolos diunggah otomatis.</p></div>
    <div class="panel-body">
        <div class="small border rounded-3 bg-light p-3 mb-3">
            <div>Format: <strong x-text="rules.allowed_extensions.join(', ').toUpperCase()"></strong>. Maksimal <strong x-text="(rules.max_file_size_bytes / 1048576).toFixed(1) + ' MiB' "></strong> per berkas.</div>
            <div x-show="rules.remaining_bytes !== null">Sisa kapasitas: <strong x-text="((rules.remaining_bytes ?? 0) / 1048576).toFixed(1) + ' MiB'"></strong>.</div>
            <div x-show="rules.allowed_extensions.some(extension => rules.video_extensions.includes(extension))">Video MP4, MOV, atau WebM sesuai format yang diizinkan. Durasi tidak dibatasi; ukuran mengikuti batas berkas di atas.</div>
        </div>
        <button type="button" class="btn btn-outline-purple w-100 p-4 border-2 rounded-3" :class="{'bg-warning-subtle': drag}"
            @click="$refs.files.click()" @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drag = false; add($event.dataTransfer.files)">
            <i class="bi bi-cloud-arrow-up fs-2 d-block mb-2" aria-hidden="true"></i>Pilih atau seret berkas ke sini
            <small class="d-block mt-1">Bisa beberapa berkas sekaligus. Tidak ada transfer sebelum pemeriksaan file tersebut lolos.</small>
        </button>
        <input type="file" x-ref="files" class="d-none" multiple accept="{{ collect($rules['allowed_extensions'])->map(fn($ext) => '.'.$ext)->implode(',') }}" :accept="rules.allowed_extensions.map(ext => '.' + ext).join(',')" @change="add($event.target.files)">
        <div x-cloak x-show="error" x-text="error" class="alert alert-danger mt-3" role="alert"></div>
        <div x-cloak x-show="busy" class="mt-3">
            <div class="progress" style="height:10px"><div class="progress-bar bg-purple" :style="'width:' + progress + '%'" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="Kemajuan transfer"></div></div>
            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 border-1 rounded-2 fw-semibold mt-2" @click="cancel()">Batalkan antrean</button>
        </div>
        <div class="mt-3" role="status" aria-live="polite" x-show="items.length" x-cloak>
            <strong class="small">Antrean berkas</strong>
            <template x-for="item in items" :key="item.id">
                <div class="border rounded-3 p-3 mt-2 text-break" :class="{'border-danger': ['failed', 'rejected'].includes(item.state), 'border-success': item.state === 'saved'}">
                    <strong class="d-block small" x-text="item.name"></strong>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                        <small class="text-muted" x-text="(item.size / 1048576).toFixed(2) + ' MiB'"></small>
                        <span x-show="item.state === 'saved'" class="badge text-bg-success"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Berhasil Tersimpan</span>
                        <small x-show="item.state !== 'saved'" x-text="label(item.state)"></small>
                    </div>
                    <small class="d-block text-muted" x-text="videoInfo(item)"></small>
                    <div class="small text-danger mt-1" x-show="item.error" x-text="item.error"></div>
                    <div class="d-flex flex-wrap gap-2 mt-2" x-show="!busy && ['failed', 'rejected'].includes(item.state)">
                        <button type="button" class="btn btn-sm btn-outline-purple px-2 py-1 border-1 rounded-2 fw-semibold" @click="retry(item)">Coba ulang</button>
                        <button type="button" class="btn btn-sm btn-outline-purple px-2 py-1 border-1 rounded-2 fw-semibold" @click="remove(item)">Keluarkan dari antrean</button>
                        <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 border-1 rounded-2 fw-semibold" @click="cancel()">Batalkan antrean</button>
                    </div>
                </div>
            </template>
            <p class="small text-muted mt-2 mb-0">Hanya berkas berstatus Berhasil Tersimpan yang masuk ke daftar berkas. File yang ditolak tetap perlu diperbaiki atau dipilih ulang jika diperlukan.</p>
        </div>
    </div>
</section>
