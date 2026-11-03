<x-layouts::app title="Berkas Workspace Pendaftar">
    <div class="participant-heading mb-4">
        <div>
            <h2>Workspace {{ $user?->name ?? 'Pendaftar' }}</h2>
            <p>
                {{ $period->name }} ·
                @if ($application)
                    Aplikasi: <strong>{{ $application->application_number }}</strong>
                @else
                    <span class="badge text-bg-warning">Pra-Klaim (Belum Terhubung SK)</span>
                @endif
                · Akun: {{ $user?->email ?? '-' }}
            </p>
        </div>
        <div>
            <a class="btn btn-outline-purple" href="{{ route('admin.document-policies.uploads', ['period_id' => $period->id]) }}">
                <i class="bi bi-arrow-left me-1"></i>Daftar Seluruh Unggahan
            </a>
        </div>
    </div>

    <div class="alert alert-light border small text-muted mb-4">
        <i class="bi bi-shield-lock text-purple me-1"></i>
        Mode Pengelolaan: Anda bertindak sebagai {{ auth()->user()->role->label() }} untuk membantu pendaftar.
    </div>

    <livewire:documents.document-manager
        :workspaceId="$workspace->id"
        :periodId="$period->id"
        mode="admin"
    />
</x-layouts::app>
