<x-layouts::app title="Beranda {{ auth()->user()->role->label() }}">
    <x-dashboard-announcement :settings="$settings" />
    @if(auth()->user()->role === App\Enums\UserRole::Applicant)
        <div class="operator-contact" aria-label="Hubungi operator yang ditugaskan">
            @if($operatorContact)
                <a href="{{ $operatorContact['url'] }}" target="_blank" rel="noopener noreferrer" class="operator-contact-button"
                    title="Tanya {{ $operatorContact['name'] }} melalui WhatsApp" aria-label="Tanya Operator {{ $operatorContact['name'] }} melalui WhatsApp (tab baru)">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i><span>Tanya Operator</span>
                </a>
            @else
                <button type="button" class="operator-contact-button" disabled aria-describedby="operator-contact-unavailable">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i><span>Tanya Operator</span>
                </button>
                <small id="operator-contact-unavailable" class="operator-contact-note">{{ $operatorContactMessage }}</small>
            @endif
        </div>
    @endif
    @php
        $activeApplication = $ownApplications->first();
        $activePeriod = $activeApplication?->period ?? $periods->first();
        $activeDocCount = $activePeriod && isset($workspaces) ? ($workspaces->get($activePeriod->id)?->documents_count ?? 0) : 0;
    @endphp

    @if(auth()->user()->role === App\Enums\UserRole::Applicant)
        @foreach($ownApplications->filter(fn ($item) => $item->status === App\Enums\ApplicationStatus::Accepted) as $acceptedApplication)
            <section class="acceptance-banner mb-4" role="status" aria-label="Hasil pemeriksaan pendaftaran">
                <div class="acceptance-banner-icon" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></div>
                <div class="flex-grow-1">
                    <span class="acceptance-banner-label">HASIL PEMERIKSAAN ADMINISTRASI</span>
                    <h2>Pendaftaran Anda Sudah Diterima Lengkap</h2>
                    <p class="mb-2">Biodata dan berkas pendaftaran Anda telah dinyatakan lengkap oleh pengelola.</p>
                    <p class="mb-0"><strong>{{ $acceptedApplication->period->name }}</strong> &middot; {{ $acceptedApplication->application_number }}</p>
                    @if($acceptedApplication->accepted_at)
                        <small class="d-block mt-2">Disetujui {{ $acceptedApplication->accepted_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</small>
                    @endif
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="{{ route('applicant.application.status', $acceptedApplication->admission_period_id) }}" class="btn btn-light text-success fw-semibold">Lihat Status Pendaftaran <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                        @if($acceptedApplication->hasCurrentOwnerSubmission())
                            <a href="{{ route('applicant.application.proof', $acceptedApplication->admission_period_id) }}" class="btn btn-outline-light"><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>Bukti Pendaftaran</a>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    @endif

    <div class="welcome-banner dayak-pattern-bg">
        <div>
            <span class="eyebrow">PMB PPG DALAM JABATAN · BIMAS KRISTEN</span>
            <h2>{{ auth()->user()->isAdministrator() ? 'Satu pandangan untuk seluruh penerimaan.' : 'Selamat datang, '.auth()->user()->name.'.' }}</h2>
            <p>
                @if (auth()->user()->role === App\Enums\UserRole::Applicant)
                    @if ($activePeriod)
                        Periode aktif: <strong>{{ $activePeriod->name }}</strong> ({{ $activePeriod->academic_year }} · Gelombang {{ $activePeriod->wave }})
                        @if ($activeApplication)
                            · No. Registrasi: <strong>{{ $activeApplication->application_number }}</strong>
                        @endif
                    @else
                        Pantau kepesertaan SK resmi, lengkapi formulir pendaftaran, dan kelola berkas persyaratan Anda.
                    @endif
                @elseif (auth()->user()->isAdministrator())
                    Siapkan periode, kelola akun pengelola, dan pantau aktivitas penerimaan.
                @else
                    Pantau periode penerimaan dan kelola keamanan akun Anda.
                @endif
            </p>

            @if (auth()->user()->role === App\Enums\UserRole::Applicant)
                @if ($activePeriod)
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a class="btn btn-gold btn-form-primary fw-bold" href="{{ route('applicant.form.edit', $activePeriod) }}">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
                            {{ $activeApplication && ! $activeApplication->status->editable() ? 'Lihat Formulir' : 'Lengkapi Formulir' }}
                        </a>
                        <a class="btn btn-banner-glass" href="{{ route('applicant.sk-information') }}"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Informasi SK</a>
                        <a class="btn btn-banner-glass" href="{{ route('applicant.documents.index', $activePeriod) }}">
                            <i class="bi bi-folder2-open me-1" style="color: #FFD54F;" aria-hidden="true"></i>Berkas Saya
                            @if ($activeDocCount > 0)
                                <span class="badge-counter bg-warning text-dark ms-1">{{ $activeDocCount }}</span>
                            @else
                                <span class="badge-counter bg-white bg-opacity-25 text-white ms-1">0</span>
                            @endif
                        </a>
                        <a class="btn btn-banner-glass" href="{{ route('applicant.application.status', $activePeriod) }}">
                            <i class="bi bi-shield-check me-1" style="color: #FFD54F;" aria-hidden="true"></i>Status & Perbaikan
                        </a>
                        @if ($activeApplication?->hasCurrentOwnerSubmission())
                            <a class="btn btn-banner-glass" href="{{ route('applicant.application.proof', $activePeriod) }}">
                                <i class="bi bi-file-earmark-check me-1" style="color: #FFD54F;" aria-hidden="true"></i>Bukti Pendaftaran
                            </a>
                        @endif
                    </div>
                @endif
            @elseif (auth()->user()->isAdministrator())
                <a href="{{ route('admin.periods') }}" class="btn btn-gold">
                    Kelola Periode <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                </a>
            @else
                <a href="{{ route('reviews.index') }}" class="btn btn-gold">
                    Buka Antrean <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                </a>
            @endif
        </div>
        <div class="welcome-symbol">
            <img src="{{ asset('favicon_io_new/android-chrome-192x192.png') }}" alt="Logo PMB PPG IAKN" style="width: 72px; height: 72px; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));">
            <span>PPG IAKN PALANGKA RAYA</span>
        </div>
    </div>

    @if(isset($quickLinks) && $quickLinks->isNotEmpty())
        <section class="workspace-card mb-4">
            <div class="panel-heading d-flex align-items-center justify-content-between py-2 px-3 border-bottom bg-light bg-opacity-50">
                <div class="d-flex align-items-center gap-2">
                    <span class="dayak-shield-badge" style="width: 28px; height: 28px; font-size: 0.8rem;">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i>
                    </span>
                    <h2 class="h6 mb-0 fw-bold text-purple">Layanan Akademik & Tautan Penting</h2>
                </div>
                <small class="text-muted d-none d-md-block">Akses cepat portal dan sistem terkait</small>
            </div>
            <div class="panel-body p-3">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($quickLinks as $link)
                        <a href="{{ $link->url }}" target="{{ $link->target ?? '_blank' }}" rel="noopener noreferrer" 
                           class="btn d-inline-flex align-items-center gap-2 text-decoration-none shadow-sm"
                           style="
                                @if($link->button_color === 'purple')
                                    background: linear-gradient(135deg, #4A154B 0%, #6a3283 100%); color: #ffffff; border: 1px solid #4A154B;
                                @elseif($link->button_color === 'gold')
                                    background: linear-gradient(135deg, #D4AF37 0%, #f1c40f 100%); color: #1e1b18; border: 1px solid #D4AF37; font-weight: 600;
                                @elseif($link->button_color === 'blue')
                                    background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); color: #ffffff; border: 1px solid #1d4ed8;
                                @elseif($link->button_color === 'green')
                                    background: linear-gradient(135deg, #15803d 0%, #22c55e 100%); color: #ffffff; border: 1px solid #15803d;
                                @elseif($link->button_color === 'teal')
                                    background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); color: #ffffff; border: 1px solid #0f766e;
                                @else
                                    background: linear-gradient(135deg, #334155 0%, #475569 100%); color: #ffffff; border: 1px solid #334155;
                                @endif
                                padding: 8px 16px; border-radius: 8px; transition: transform 0.15s ease, box-shadow 0.15s ease;
                           "
                           onmouseover="this.style.transform='translateY(-2px)'"
                           onmouseout="this.style.transform='translateY(0)'"
                           title="{{ $link->description ?: $link->title }}">
                            <i class="bi {{ $link->icon ?: 'bi-box-arrow-up-right' }}" style="font-size: 1.05rem;" aria-hidden="true"></i>
                            <div class="text-start">
                                <span class="fw-bold d-block lh-1" style="font-size: 0.85rem;">{{ $link->title }}</span>
                                @if($link->description)
                                    <small class="d-block opacity-75 lh-1 mt-1" style="font-size: 0.68rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $link->description }}</small>
                                @endif
                            </div>
                            <i class="bi bi-arrow-up-right opacity-50 ms-1" style="font-size: 0.75rem;" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($totalSk !== null)
        {{-- TAMPILAN OPERATOR / ADMINISTRATOR --}}
        @if ($periods->isEmpty())
            @if (auth()->user()->isAdministrator())
                <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 mb-4 rounded-3 border" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <span class="dayak-shield-badge" style="width: 40px; height: 40px; background: #b45309; color: #fff;">
                            <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                        </span>
                        <div>
                            <strong class="d-block text-dark">Belum ada periode pendaftaran di sistem</strong>
                            <span class="text-muted small">Buat dan publikasikan periode penerimaan baru agar pendaftaran calon mahasiswa, impor SK, aturan berkas, dan penugasan operator dapat berjalan.</span>
                        </div>
                    </div>
                    <a href="{{ route('admin.periods') }}" class="btn btn-purple btn-sm">
                        <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Kelola Periode & Gelombang
                    </a>
                </div>
            @else
                <div class="alert alert-info d-flex align-items-center gap-3 p-3 mb-4 rounded-3 border" role="alert">
                    <i class="bi bi-info-circle-fill fs-4 text-primary"></i>
                    <div>
                        <strong class="d-block text-dark">Belum ada periode pendaftaran aktif</strong>
                        <span class="text-muted small">Administrator belum membuat atau mempublikasikan periode pendaftaran penerimaan baru.</span>
                    </div>
                </div>
            @endif
        @endif

        <section class="workspace-card mb-4">
            <div class="panel-body">
                <form method="get" class="d-flex gap-2">
                    <label class="visually-hidden" for="dashboard-period">Periode ringkasan</label>
                    <select id="dashboard-period" name="period_id" class="form-select">
                        <option value="">Semua periode</option>
                        @foreach ($periods as $option)
                            <option value="{{ $option->id }}" @selected($periodId === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-purple">Terapkan</button>
                </form>
            </div>
        </section>

        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-icon purple"><i class="bi bi-people" aria-hidden="true"></i></div>
                <span>Entri SK dalam lingkup</span>
                <strong>{{ $totalSk }}</strong>
                <small>Termasuk peserta belum mendaftar</small>
            </div>
            <div class="metric-card">
                <div class="metric-icon gold"><i class="bi bi-person" aria-hidden="true"></i></div>
                <span>Belum mendaftar</span>
                <strong>{{ $totalSk - $applicationCount }}</strong>
                <small>SK tanpa pendaftaran resmi</small>
            </div>
            <div class="metric-card">
                <div class="metric-icon blue"><i class="bi bi-send" aria-hidden="true"></i></div>
                <span>Terkirim saat ini</span>
                <strong>{{ $counts['terkirim'] ?? 0 }}</strong>
                <small>Menunggu pemeriksaan</small>
            </div>
            <div class="metric-card">
                <div class="metric-icon green"><i class="bi bi-patch-check" aria-hidden="true"></i></div>
                <span>Diterima lengkap</span>
                <strong>{{ $counts['diterima_lengkap'] ?? 0 }}</strong>
                <small>Keputusan administrasi PMB</small>
            </div>
        </div>

        <div class="d-flex gap-3 flex-wrap mb-4">
            @foreach (App\Enums\ApplicationStatus::cases() as $status)
                <span class="status-pill {{ $status->value }}">{{ $status->label() }}: {{ $counts[$status->value] ?? 0 }}</span>
            @endforeach
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="workspace-card">
                    <div class="panel-heading">
                        <h2>Periode penerimaan</h2>
                        <p>Informasi dari pengelola PMB.</p>
                    </div>
                    <div class="panel-body">
                        @forelse ($periods as $period)
                            <div class="border-bottom pb-3 mb-3">
                                <h3 class="h5">{{ $period->name }}</h3>
                                <p>{{ $period->academic_year }} · Gelombang {{ $period->wave }}</p>
                                <span class="badge text-bg-{{ $period->acceptsApplications() ? 'success' : 'secondary' }}">{{ $period->availabilityLabel() }}</span>
                                <p class="small text-muted mt-3 mb-0">{{ $period->opens_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} – {{ $period->closes_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                            </div>
                        @empty
                            <div class="empty-state compact">
                                <i class="bi bi-calendar-range" aria-hidden="true"></i>
                                <h3>Belum ada periode diumumkan</h3>
                                <p>Informasi penerimaan akan ditampilkan setelah diumumkan pengelola.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
                @if ($staffCount !== null)
                    <div class="notice-box info mt-4">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <div>
                            <strong>{{ $staffCount }} akun pengelola aktif</strong>
                            <p class="mb-0">Periksa kelengkapan akun dan penanggung jawab sebelum membuka penerimaan.</p>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-lg-5">
                <section class="workspace-card">
                    <div class="panel-heading">
                        <h2>Aktivitas terbaru</h2>
                    </div>
                    <div class="panel-body">
                        @forelse ($events as $event)
                            <div class="audit-line">
                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                                <div>
                                    <strong>{{ $event->action }}</strong>
                                    <small>{{ $event->actor?->name ?? 'Sistem' }} · {{ $event->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada aktivitas tercatat.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    @else
        {{-- TAMPILAN PENDAFTAR --}}
        <section class="mb-5" id="pendaftaran-saya">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1"><i class="bi bi-file-earmark-text text-purple me-2" aria-hidden="true"></i>Pendaftaran Saya</h2>
                    <p class="text-muted small mb-0">Periode penerimaan yang dibuka untuk pengisian formulir dan pengelolaan berkas persyaratan.</p>
                </div>
            </div>
            <div class="row g-4">
                @forelse ($periods as $period)
                    @php($application = $ownApplications->firstWhere('admission_period_id', $period->id))
                    @php($docCount = isset($workspaces) ? ($workspaces->get($period->id)?->documents_count ?? 0) : 0)
                    <div class="col-lg-6">
                        <section class="workspace-card h-100">
                            <div class="panel-heading">
                                <h2>{{ $period->name }}</h2>
                                <p>{{ $period->academic_year }} · Gelombang {{ $period->wave }}</p>
                            </div>
                            <div class="panel-body">
                                <span class="status-pill {{ $application?->status->value ?? 'draf' }}">
                                    {{ $application?->status->label() ?? 'Kepesertaan belum disetujui' }}
                                </span>
                                <p class="text-muted mt-3 mb-2">{{ $period->availabilityLabel() }}</p>
                                @if ($application)
                                    <p class="mb-2">Nomor Registrasi: <strong>{{ $application->application_number }}</strong></p>
                                @endif
                                <div class="mt-3 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <span class="small text-muted">
                                        <i class="bi bi-folder2 me-1" aria-hidden="true"></i>Berkas terunggah: <strong>{{ $docCount }}</strong> berkas
                                    </span>
                                    @if ($activePeriod?->id !== $period->id)
                                        <a class="btn btn-sm btn-outline-purple" href="{{ route('applicant.form.edit', $period) }}">
                                            Buka Periode Ini
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </section>
                    </div>
                @empty
                    <div class="col-12">
                        <section class="workspace-card empty-state">
                            <i class="bi bi-calendar-range" aria-hidden="true"></i>
                            <h2>Belum ada periode penerimaan</h2>
                            <p>Pantau pengumuman resmi dari pengelola.</p>
                        </section>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-1"><i class="bi bi-patch-check text-purple me-2" aria-hidden="true"></i>Kepesertaan SK Resmi</h2>
                        <p class="text-muted small mb-0">Status pencocokan identitas dengan SK peserta resmi PMB PPG.</p>
                    </div>
                </div>

                @forelse ($claims as $claim)
                    <section class="workspace-card mb-3">
                        <div class="panel-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-2">
                                <div>
                                    <h3 class="h5 mb-1">{{ $claim->submitted_name }}</h3>
                                    <p class="text-muted small mb-0">{{ $claim->period->name }} · Referensi {{ $claim->public_reference }}</p>
                                </div>
                                <span class="badge text-bg-{{ $claim->status === 'approved' ? 'success' : ($claim->status === 'needs_information' ? 'warning' : 'secondary') }}">
                                    {{ ['pending' => 'Menunggu pemeriksaan', 'approved' => 'Disetujui', 'needs_information' => 'Perlu informasi tambahan', 'declined' => 'Klaim belum dapat disetujui', 'cancelled' => 'Dibatalkan'][$claim->status] ?? $claim->status }}
                                </span>
                            </div>
                            @if ($claim->public_note)
                                <div class="notice-box info mt-3">
                                    <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                                    <p class="mb-0">{{ $claim->public_note }}</p>
                                </div>
                            @endif
                            @foreach ($claim->responses as $response)
                                <div class="border-start ps-3 my-3">
                                    <small class="text-muted">Tanggapan Anda · {{ $response->created_at->timezone(config('pmb.business_timezone'))->format('d/m/Y H:i') }} WIB</small>
                                    <p class="mb-0 text-break" style="white-space: pre-line">{{ $response->message }}</p>
                                </div>
                            @endforeach
                            @if ($claim->status === 'needs_information' && $claim->period->state !== \App\Enums\PeriodState::Archived)
                                <form method="post" action="{{ route('applicant.claims.respond', $claim) }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                    <label for="response-{{ $claim->id }}" class="form-label">Informasi tambahan untuk pemeriksa *</label>
                                    <textarea id="response-{{ $claim->id }}" name="message" class="form-control" rows="3" minlength="10" maxlength="1000" required></textarea>
                                    <div class="form-text mb-3">Jawab catatan pemeriksa. Jangan menulis sandi, kode akses, atau NIK lengkap. Perubahan identitas dan kepemilikan harus diperiksa petugas.</div>
                                    <button class="btn btn-outline-purple btn-sm">Kirim Informasi Tambahan</button>
                                </form>
                            @endif
                            @if (in_array($claim->status, ['pending', 'needs_information'], true))
                                <details class="mt-3">
                                    <summary class="text-muted small">Batalkan permohonan ini</summary>
                                    <form method="post" action="{{ route('applicant.claims.cancel', $claim) }}" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                        <label class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" name="confirmed" value="1" required>
                                            <span class="form-check-label small">Saya ingin membatalkan permohonan yang belum disetujui ini.</span>
                                        </label>
                                        <button class="btn btn-outline-danger btn-sm">Batalkan Permohonan</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </section>
                @empty
                    <div class="workspace-card empty-state compact mb-3">
                        <i class="bi bi-patch-check" aria-hidden="true"></i>
                        <h2>Belum ada permohonan klaim</h2>
                        <p>Gunakan nama dan pengenal yang tercantum pada SK resmi untuk mencocokkan kepesertaan.</p>
                    </div>
                @endforelse
            </div>

            <div class="col-lg-5">
                <div class="mb-3">
                    <h2 class="h4 mb-1"><i class="bi bi-shield-check text-purple me-2" aria-hidden="true"></i>Aktivitas Terbaru</h2>
                    <p class="text-muted small mb-0">Catatan riwayat tindakan keamanan dan akun Anda.</p>
                </div>
                <section class="workspace-card">
                    <div class="panel-body">
                        @forelse ($events as $event)
                            <div class="audit-line">
                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                                <div>
                                    <strong>{{ $event->action }}</strong>
                                    <small>{{ $event->actor?->name ?? 'Sistem' }} · {{ $event->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada aktivitas tercatat.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    @endif
    @if(auth()->user()->role === App\Enums\UserRole::Applicant)
        <div class="operator-contact-space" aria-hidden="true"></div>
    @endif
</x-layouts::app>
