@props(['application'])
@php($submission = $application->submissions->where('ownership_epoch', $application->ownership_epoch)->sortByDesc('revision')->first())
<section class="workspace-card mb-4">
    <div class="panel-heading"><h2>Berkas pada pengiriman {{ $submission?->revision }}</h2><p>Daftar berkas sesuai revisi yang dikirim peserta.</p></div>
    <div class="panel-body">
        @if(!$submission || $submission->documents_version === null)
            <p class="text-muted">Riwayat sebelum pengiriman berkas disatukan. Daftar berkas saat ini bukan bukti daftar pada waktu pengiriman lama.</p>
        @else
            <ul class="list-group">
                @forelse($submission->documents as $item)
                    <li class="list-group-item d-flex flex-wrap justify-content-between gap-2 text-break">
                        <span>{{ $item->file_name }} <small class="text-muted">{{ number_format($item->byte_size / 1024, 1) }} KB</small></span>
                        @if($item->document && auth()->user()->can('download', $item->document))
                            @if($item->document->isVideo())
                                <a class="btn btn-sm btn-outline-purple" target="_blank" rel="noopener noreferrer" href="{{ auth()->user()->role === \App\Enums\UserRole::Applicant ? route('applicant.documents.preview', ['period' => $item->document->admission_period_id, 'document' => $item->document_id]) : route('admin.workspaces.documents.preview', ['workspace' => $item->document->workspace_id, 'document' => $item->document_id]) }}">Buka Video (tab baru)</a>
                            @endif
                            <a class="btn btn-sm btn-outline-purple" href="{{ route('applicant.documents.download', ['period' => $item->document->admission_period_id, 'document' => $item->document_id]) }}">Unduh</a>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">Tidak ada berkas pada revisi ini.</li>
                @endforelse
            </ul>
        @endif
    </div>
</section>
