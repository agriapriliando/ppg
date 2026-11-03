<x-layouts::app title="Berkas Pendaftaran">
    <div class="participant-heading mb-4">
        <div>
            <h2>{{ $period->name }}</h2>
            <p>{{ $period->academic_year }} · Gelombang {{ $period->wave }} · {{ $period->availabilityLabel() }}</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-purple" href="{{ route('dashboard') }}">
                <i class="bi bi-arrow-left me-1"></i>Ruang Kerja
            </a>
            <a class="btn btn-purple" href="{{ route('applicant.form.edit', $period) }}">
                <i class="bi bi-file-earmark-text me-1"></i>Formulir
            </a>
        </div>
    </div>

    <livewire:documents.document-manager
        :workspaceId="$workspace->id"
        :periodId="$period->id"
        mode="applicant"
    />
</x-layouts::app>
