<x-layouts::app title="Audit Aktivitas">
    <section class="workspace-card">
        <div class="panel-heading">
            <h2>Riwayat tindakan aplikasi</h2>
            <p>Identitas pelaku, waktu, dan alasan perubahan penting.</p>
        </div>
        <div class="panel-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <form class="d-flex gap-2 flex-grow-1" method="get">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <label for="audit-search" class="visually-hidden">Cari tindakan</label>
                    <input class="form-control" id="audit-search" name="search" value="{{ $search }}" placeholder="Cari tindakan…" maxlength="100">
                    <button class="btn btn-outline-purple">Cari</button>
                </form>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted fw-semibold">Jumlah per halaman:</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Jumlah per halaman">
                        @foreach([10, 25, 50, 100] as $size)
                            <a href="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}"
                               class="btn {{ $perPage === $size ? 'btn-purple' : 'btn-outline-purple' }}">
                                {{ $size }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @forelse($events as $event)
                <div class="audit-line">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <div>
                        <strong>{{ $event->action }}</strong>
                        <small>{{ $event->actor?->name ?? 'Sistem / Konsol' }} · {{ $event->actor_role_snapshot ?? 'Sistem' }} · {{ $event->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</small>
                        @if($event->reason)
                            <p class="small mb-1">{{ $event->reason }}</p>
                        @endif
                        <small class="text-muted">Referensi: {{ $event->request_id }}</small>
                    </div>
                </div>
            @empty
                <div class="empty-state compact">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <h3>Belum ada aktivitas</h3>
                    <p>Ubah kata pencarian atau lakukan tindakan pengelolaan.</p>
                </div>
            @endforelse

            <div class="d-flex flex-wrap align-items-center justify-content-end gap-3 mt-4 pt-3 border-top">
                <div>
                    {{ $events->links() }}
                </div>
            </div>
        </div>
    </section>
</x-layouts::app>
