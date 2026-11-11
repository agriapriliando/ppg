<div x-data="{ info: null }" x-init="$nextTick(() => $el.scrollIntoView({behavior:'smooth', block:'start'}))">
    <section class="workspace-card mt-4" aria-label="Berkas periode {{ $period->name }}">
        <div class="panel-heading">
            <div>
                <h2 class="h5 mb-1"><i class="bi bi-folder2-open text-purple me-2" aria-hidden="true"></i>Berkas Periode: {{ $period->name }}</h2>
                <p class="mb-0">{{ $period->code }} · {{ $period->academic_year }}. Berkas dikelola oleh pengelola periode.</p>
            </div>
        </div>
        <div class="panel-body">
            @if($message)<div class="alert alert-info" role="status">{{ $message }}</div>@endif
            @if($uploadErrors)<div class="alert alert-danger" role="alert"><strong>File berikut belum tersimpan. Pilih ulang hanya file yang gagal:</strong>@foreach($uploadErrors as $error)<div class="mt-1" style="overflow-wrap:anywhere">{{ $error }}</div>@endforeach</div>@endif
            @if(!$canModify)
                <div class="alert alert-secondary">Periode diarsipkan. Berkas dapat dilihat dan diunduh.</div>
            @else
                <x-file-upload-queue :rules="$uploadRules" />
            @endif
            <h3 class="h6 fw-bold">Berkas yang Telah Diunggah <span class="badge text-bg-light border">{{ $documents->count() }}</span></h3>
            @forelse($documents as $doc)
                <div class="border rounded-3 p-3 mt-3" wire:key="period-file-{{ $doc->id }}">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div class="d-flex align-items-start gap-2" style="min-width:0">
                            <i class="bi {{ $doc->iconClass() }} fs-4 flex-shrink-0" aria-hidden="true"></i>
                            <div style="overflow-wrap:anywhere">
                                <strong>{{ $doc->file_name }}</strong>
                                <button type="button" class="btn btn-link text-purple p-2" style="min-width:44px;min-height:44px" aria-label="Informasi berkas {{ $doc->file_name }}"
                                    @click="info=@js(['name'=>$doc->file_name,'original'=>$doc->original_name,'size'=>$doc->formattedSize(),'format'=>strtoupper($doc->extension),'uploadedAt'=>$doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB','uploader'=>$doc->uploader?->name ?? 'Pengelola'])"><i class="bi bi-info-circle" aria-hidden="true"></i></button>
                                <small class="d-block text-muted">{{ $doc->formattedSize() }} · {{ strtoupper($doc->extension) }} · {{ $doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>
                                @if($doc->isDeleting())<span class="badge text-bg-warning mt-2">Menunggu penghapusan fisik</span>@endif
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center flex-shrink-0">
                            @if($doc->isAvailable())
                                @if($doc->isPreviewable())<a class="btn btn-sm btn-outline-purple" href="{{ route('admin.period-documents.preview', [$period,$doc]) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ $doc->isVideo() ? 'Buka Video' : 'Lihat' }}</a>@endif
                                <a class="btn btn-sm btn-outline-purple" href="{{ route('admin.period-documents.download', [$period,$doc]) }}"><i class="bi bi-download me-1" aria-hidden="true"></i>Unduh</a>
                                @if($canModify)<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openRename('{{ $doc->id }}')">Ubah Nama</button>@endif
                            @endif
                            @if($canModify)<button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete('{{ $doc->id }}')">{{ $doc->isDeleting() ? 'Coba Ulang Hapus' : 'Hapus' }}</button>@endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted border rounded-3 p-4"><i class="bi bi-folder2-open fs-3 d-block mb-2" aria-hidden="true"></i>Belum ada berkas pada periode ini.</div>
            @endforelse
        </div>
    </section>
    <div x-cloak x-show="$wire.dialog !== ''" x-trap.inert.noscroll="$wire.dialog !== ''" class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="period-file-dialog-title-{{ $periodId }}" @keydown.escape.window="$wire.dialog=''" @click.self="$wire.dialog=''">
        <div class="ui-modal">
            <div class="ui-modal-header"><h2 id="period-file-dialog-title-{{ $periodId }}" x-text="$wire.dialog === 'rename' ? 'Ubah Nama Berkas Periode' : 'Hapus Berkas Periode?'"></h2><button type="button" class="btn-close" @click="$wire.dialog=''" aria-label="Tutup"></button></div>
            <form wire:submit="saveRename" x-show="$wire.dialog === 'rename'">
                <div class="ui-modal-body"><label for="period-rename-{{ $periodId }}" class="form-label">Nama berkas tanpa ekstensi</label><div class="input-group"><input id="period-rename-{{ $periodId }}" class="form-control" wire:model="name" maxlength="180" required><span class="input-group-text">.{{ $extension }}</span></div><x-field-error name="name" /><x-field-error name="document" /></div>
                <div class="d-flex justify-content-end gap-2 border-top p-3"><button type="button" class="btn btn-light border" @click="$wire.dialog=''">Batal</button><button type="submit" class="btn btn-purple" wire:loading.attr="disabled">Simpan Nama</button></div>
            </form>
            <form wire:submit="executeDelete" x-show="$wire.dialog === 'delete'">
                <div class="ui-modal-body"><p class="fw-bold" style="overflow-wrap:anywhere">{{ $name }}.{{ $extension }}</p><div class="alert alert-danger">File akan dihapus permanen dari periode ini dan tidak dapat dipulihkan melalui aplikasi.</div><label class="d-flex gap-2"><input type="checkbox" class="form-check-input flex-shrink-0" wire:model="deleteConfirmed" required><span>Saya menyetujui penghapusan berkas ini.</span></label><x-field-error name="deleteConfirmed" /><x-field-error name="document" /></div>
                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top p-3"><button type="button" class="btn btn-light border" @click="$wire.dialog=''">Batal</button><button type="submit" class="btn btn-danger" wire:loading.attr="disabled">Hapus Permanen</button></div>
            </form>
        </div>
    </div>
    <div x-cloak x-show="info !== null" x-trap.inert.noscroll="info !== null" class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="period-file-info-title-{{ $periodId }}" @keydown.escape.window="info=null" @click.self="info=null">
        <div class="ui-modal"><div class="ui-modal-header"><h2 id="period-file-info-title-{{ $periodId }}">Informasi Berkas</h2><button type="button" class="btn-close" @click="info=null" aria-label="Tutup"></button></div><div class="ui-modal-body"><dl class="mb-0" style="overflow-wrap:anywhere"><dt>Nama tampilan</dt><dd x-text="info?.name"></dd><dt>Nama asli file</dt><dd x-text="info?.original"></dd><dt>Ukuran / Format</dt><dd x-text="(info?.size || '')+' / '+(info?.format || '')"></dd><dt>Waktu unggah</dt><dd x-text="info?.uploadedAt"></dd><dt>Pengunggah</dt><dd x-text="info?.uploader"></dd></dl></div><div class="p-3 border-top text-end"><button type="button" class="btn btn-purple" @click="info=null">Tutup</button></div></div>
    </div>
</div>
