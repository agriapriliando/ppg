@props(['period', 'documents', 'audience' => 'public'])
<div class="d-flex flex-column gap-3">
    @forelse($documents as $document)
        <article class="border rounded-3 p-3">
            <div class="d-flex flex-column flex-sm-row justify-content-between gap-3">
                <div class="d-flex gap-2" style="min-width:0">
                    <i class="bi {{ $document->iconClass() }} fs-3 flex-shrink-0" aria-hidden="true"></i>
                    <div style="overflow-wrap:anywhere"><h3 class="h6 fw-bold mb-1">{{ $document->file_name }}</h3><p class="small text-muted mb-0">{{ strtoupper($document->extension) }} · {{ $document->formattedSize() }}</p></div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                    @if($document->isPreviewable())<a class="btn btn-outline-purple" href="{{ route($audience.'.period-documents.preview', [$period,$document]) }}" target="_blank" rel="noopener"><i class="bi bi-eye me-1" aria-hidden="true"></i>Lihat</a>@endif
                    <a class="btn btn-purple" href="{{ route($audience.'.period-documents.download', [$period,$document]) }}"><i class="bi bi-download me-1" aria-hidden="true"></i>Unduh</a>
                </div>
            </div>
        </article>
    @empty
        <div class="text-center border rounded-3 p-4 text-muted"><i class="bi bi-folder2-open fs-2 d-block mb-2" aria-hidden="true"></i>{{ $audience === 'public' ? 'Belum ada berkas yang dipublikasikan untuk periode ini.' : 'Belum ada berkas periode yang dibagikan kepada peserta.' }}</div>
    @endforelse
</div>
