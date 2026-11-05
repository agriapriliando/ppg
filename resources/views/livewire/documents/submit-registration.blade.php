<section class="workspace-card mt-4" x-data="{ uploading: false }" @registration-uploading.window="uploading = $event.detail.busy" @registration-documents-changed.window="$wire.$refresh()">
    <div class="panel-heading"><h2>Berkas &amp; Kirim Pendaftaran</h2><p>Periksa biodata dan berkas yang tersimpan sebelum mengirim.</p></div>
    <div class="panel-body">
        @if(!$workspace->application || $workspace->application->status->editable())
            <div class="alert {{ $issues ? 'alert-warning' : 'alert-success' }}" role="status">
                <strong>{{ $issues ? 'Biodata masih perlu dilengkapi' : 'Biodata lengkap' }}</strong>
                <span class="d-block">{{ $documents->count() }} berkas tersimpan.</span>
            </div>
            @if($issues)
                <ul class="small">
                    @foreach($issues as $key => $messages)
                        @php($section = str_starts_with($key, 'education_history') || in_array($key, ['degree_level', 'pt_name', 'prodi_name', 'graduation_year']) ? 4 : (\App\Support\ApplicantFields::definitions()[$key]['step'] ?? 1))
                        <li><a href="{{ route('applicant.form.edit', ['period' => $workspace->admission_period_id, 'section' => $section]) }}">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('applicant.form.edit', $workspace->admission_period_id) }}" class="btn btn-outline-purple mb-3">Perbaiki Biodata</a>
            <h3 class="h6 fw-bold">Berkas yang akan dikirim</h3>
            <ul class="list-group mb-3">
                @forelse($documents as $document)
                    <li class="list-group-item text-break" wire:key="submission-file-{{ $document->id }}">{{ $document->file_name }} <small class="text-muted">({{ number_format($document->byte_size / 1024, 1) }} KB)</small></li>
                @empty
                    <li class="list-group-item text-warning-emphasis">Belum ada berkas tersimpan. Periksa kembali panduan sebelum mengirim.</li>
                @endforelse
            </ul>
            @if($busy)<div class="alert alert-warning">Selesaikan atau batalkan unggahan yang belum selesai. <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancelPendingUploads">Batalkan antrean unggah</button></div>@endif
            @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
            <button type="button" class="btn btn-gold w-100" wire:click="openConfirmation" wire:loading.attr="disabled" :disabled="uploading" @disabled($busy || $issues || !$workspace->application)>
                {{ $workspace->application?->status === \App\Enums\ApplicationStatus::Correction ? 'Periksa & Kirim Ulang Perbaikan' : 'Periksa & Kirim Pendaftaran' }}
            </button>
        @else
            <div class="alert alert-info">Pendaftaran sudah dikirim. Biodata dan berkas dapat dibaca; pengubahan dibuka ketika operator meminta perbaikan.</div>
            <a href="{{ route('applicant.application.status', $workspace->admission_period_id) }}" class="btn btn-purple">Lihat Status Pendaftaran</a>
        @endif
    </div>
    <div x-cloak x-show="$wire.showConfirmation" x-trap.inert.noscroll="$wire.showConfirmation" class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="registration-confirm-title" @keydown.escape.window="$wire.showConfirmation = false" @click.self="$wire.showConfirmation = false">
        <div class="ui-modal">
            <div class="ui-modal-header"><h2 id="registration-confirm-title" class="h6 fw-bold">Konfirmasi Pengiriman Pendaftaran</h2><button class="btn-close" type="button" @click="$wire.showConfirmation = false" aria-label="Tutup"></button></div>
            <form wire:submit="submit">
                <div class="ui-modal-body">
                    <p>Biodata dan {{ $documents->count() }} berkas akan dikirim sebagai satu revisi. Setelah dikirim, data dan berkas dikunci selama pemeriksaan.</p>
                    @if($excludedLevels)<p class="text-warning-emphasis">Riwayat {{ implode(', ', $excludedLevels) }} di draf tidak disertakan karena melebihi pendidikan terakhir yang Anda pilih.</p>@endif
                    @if($documents->isEmpty())<p class="text-warning-emphasis">Anda akan mengirim tanpa berkas. Pastikan telah membaca panduan.</p>@endif
                    <label class="consent-box"><input type="checkbox" class="form-check-input" wire:model.live="consent" required><span>{{ $statement?->body }}<small class="d-block mt-2">Pernyataan versi {{ $statement?->version }}</small></span></label>
                    @if($errors->any())<div class="alert alert-danger mt-3" role="alert">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end border-top p-3">
                    <button class="btn btn-outline-purple" type="button" @click="$wire.showConfirmation = false">Batal</button>
                    <button class="btn btn-gold" type="submit" wire:loading.attr="disabled" @disabled(!$consent)><span wire:loading.remove wire:target="submit">Kirim Pendaftaran</span><span wire:loading wire:target="submit">Mengirim…</span></button>
                </div>
            </form>
        </div>
    </div>
</section>
