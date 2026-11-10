<x-layouts::public
    :interactive="false"
    :title="$period->name.' | Jadwal dan Pengumuman PPG IAKN Palangka Raya'"
    :description="'Informasi resmi '.$period->name.' PPG IAKN Palangka Raya: jadwal penerimaan, status pendaftaran, serta berkas dan pengumuman untuk peserta.'"
>
    <section class="section-light py-4">
        <div class="container">
            <a href="{{ route('home') }}" class="btn btn-sm btn-outline-purple mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke Beranda</a>

            <section class="workspace-card p-3 p-md-4 mb-3" aria-label="Ringkasan periode">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3">
                    <h1 class="h4 fw-bold text-purple mb-0 text-break">{{ $period->name }}</h1>
                    <span class="badge rounded-pill text-bg-{{ $period->acceptsApplications() ? 'success' : 'secondary' }} px-3 py-2" aria-label="Status pendaftaran">{{ $period->availabilityLabel() }}</span>
                </div>
                <p class="text-muted mt-3 mb-0">Informasi jadwal dan pengumuman resmi {{ $period->name }} PPG IAKN Palangka Raya. Periksa berkas di bawah untuk ketentuan dan persyaratan yang diterbitkan pengelola.</p>
                <dl class="row g-3 mt-0 mb-0">
                    <div class="col-sm-6">
                        <dt class="small text-muted fw-normal mb-1"><i class="bi bi-calendar-check text-success me-1" aria-hidden="true"></i>Tanggal mulai</dt>
                        <dd class="fw-semibold mb-0">{{ $period->opens_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i').' WIB' }}</dd>
                    </div>
                    <div class="col-sm-6">
                        <dt class="small text-muted fw-normal mb-1"><i class="bi bi-calendar-x text-danger me-1" aria-hidden="true"></i>Tanggal selesai</dt>
                        <dd class="fw-semibold mb-0">{{ $period->closes_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i').' WIB' }}</dd>
                    </div>
                </dl>
                <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                    <a href="{{ route('public.claim') }}" class="btn btn-gold"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Pendaftaran Akun</a>
                    <a href="{{ route('login') }}" class="btn btn-outline-purple"><i class="bi bi-person-check me-2" aria-hidden="true"></i>Login</a>
                    <a href="{{ route('guide') }}" class="btn btn-outline-purple">Panduan Pendaftaran</a>
                </div>
            </section>

            <section class="workspace-card"><div class="panel-heading"><h2 class="h5 mb-0">Berkas dan Informasi</h2></div><div class="panel-body"><x-period-file-list :period="$period" :documents="$documents" /></div></section>
        </div>
    </section>
</x-layouts::public>
