<x-layouts::app title="Berkas Pendaftar">
    <div class="participant-heading mb-4">
        <div>
            <h2>{{ $participant->name_on_sk }}</h2>
            <p>
                <strong>{{ $application->application_number }}</strong> · {{ $period->name }} ·
                Pemilik: <span class="fw-semibold text-dark">{{ $workspace->user?->name ?? 'Pendaftar' }}</span>
                @if ($workspace->biodata?->whatsapp_number)
                    ({{ $workspace->biodata->whatsapp_number }})
                @endif
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="status-pill {{ $application->status->value }}">{{ $application->status->label() }}</span>
            <a class="btn btn-outline-purple" href="{{ route('reviews.show', $application) }}">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Detail Pemeriksaan
            </a>
        </div>
    </div>

    <div class="alert alert-light border small text-muted mb-4 d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-person-badge text-purple me-1"></i>
            Petugas Aktif: <strong class="text-dark">{{ auth()->user()->name }}</strong> ({{ auth()->user()->role->label() }})
            · Berkas yang diunggah petugas akan langsung terlihat oleh pendaftar.
        </div>
    </div>

    <livewire:documents.document-manager
        :workspaceId="$workspace->id"
        :periodId="$period->id"
        :applicationId="$application->id"
        mode="reviewer"
    />
</x-layouts::app>
