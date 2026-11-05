<x-layouts::app title="Berkas Pendaftaran">
    <x-registration-progress :active="2" :period="$period" />
    <div class="participant-heading mb-4">
        <div>
            <h2>Pendaftaran PPG — Berkas &amp; Kirim</h2>
            <p>{{ $period->academic_year }} · Gelombang {{ $period->wave }} · {{ $period->availabilityLabel() }}</p>
        </div>
    </div>

    <livewire:documents.document-manager
        :workspaceId="$workspace->id"
        :periodId="$period->id"
        mode="applicant"
    />
    <livewire:documents.submit-registration :workspaceId="$workspace->id" />
</x-layouts::app>
