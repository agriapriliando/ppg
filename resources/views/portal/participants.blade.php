<x-layouts::app title="Daftar Peserta SK">
    <div x-data="{ showGuide: false }">
        <section class="workspace-card">
            <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
                <div>
                    <h2 class="h5 mb-1 fw-bold text-purple">Daftar Seluruh Peserta SK</h2>
                    <p class="text-muted small mb-0">Pantau kepesertaan SK resmi, aktivasi akun, dan progres antrean pendaftaran seluruh operator.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('participants.export', request()->query()) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2" title="Ekspor seluruh data peserta & biodata ke Excel">
                        <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                        <span>Ekspor Excel</span>
                    </a>
                    <button type="button" @click="showGuide = true" class="btn btn-outline-purple d-inline-flex align-items-center gap-2">
                        <i class="bi bi-question-circle" aria-hidden="true"></i>
                        <span>Petunjuk</span>
                    </button>
                </div>
            </div>

            <div class="panel-body pt-3">
                <form method="get" class="row g-3">
                    <div class="col-lg-4">
                        <label for="participant-search" class="visually-hidden">Nama atau Peg ID</label>
                        <input class="form-control" id="participant-search" name="search" value="{{ $search }}" placeholder="Cari nama atau Peg ID…" maxlength="100">
                    </div>
                    <div class="col-lg-3">
                        <label for="participant-operator" class="visually-hidden">Filter Operator</label>
                        <select class="form-select" id="participant-operator" name="operator_id">
                            <option value="">Semua operator</option>
                            <option value="unassigned" @selected($operatorId === 'unassigned')>Belum ditugaskan</option>
                            @foreach($operators as $operator)
                                <option value="{{ $operator->id }}" @selected((string)$operatorId === (string)$operator->id)>{{ $operator->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label for="participant-period" class="visually-hidden">Periode</label>
                        <select class="form-select" id="participant-period" name="period_id">
                            <option value="">Semua periode</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" @selected($periodId==$period->id)>{{ $period->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-outline-purple w-100">Terapkan</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table portal-table">
                    <thead>
                        <tr>
                            <th>Nama / Peg ID</th>
                            <th>Nomor SK</th>
                            <th>Periode</th>
                            <th>Status Akun</th>
                            <th>Status Antrean</th>
                            <th>Operator</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participants as $participant)
                            <tr>
                                <td>
                                    <strong>{{ $participant->name_on_sk }}</strong>
                                    <small class="d-block text-muted">{{ $participant->official_participant_id ?? 'Tanpa pengenal tambahan' }}</small>
                                </td>
                                <td>
                                    {{ $participant->sk_number }}
                                    <small class="d-block text-muted">Urut {{ $participant->sk_order }}</small>
                                </td>
                                <td>{{ $participant->period->name }}</td>
                                <td>
                                    @if($participant->claimed_user_id)
                                        @if($participant->claimedUser?->account_state === App\Enums\AccountState::Disabled)
                                            <span class="status-pill declined">Nonaktif</span>
                                        @elseif($participant->claimedUser?->account_state === App\Enums\AccountState::PendingActivation)
                                            <span class="status-pill perbaikan">Menunggu Aktivasi</span>
                                        @else
                                            <span class="status-pill diterima_lengkap">Akun Terhubung</span>
                                        @endif
                                        <small class="d-block text-muted">{{ $participant->claimedUser?->login_id ?? $participant->claimedUser?->email }}</small>
                                    @else
                                        <span class="status-pill draf">Belum Ada Akun</span>
                                    @endif
                                </td>
                                <td>
                                    @if($participant->application)
                                        <span class="status-pill {{ $participant->application->status->value }}">
                                            {{ $participant->application->status->label() }}
                                        </span>
                                        <small class="d-block text-muted">{{ $participant->application->application_number }}</small>
                                    @else
                                        <span class="status-pill draf">Belum Masuk Antrean</span>
                                    @endif
                                </td>
                                <td>{{ $participant->assignedOperator?->name ?? 'Belum ditugaskan' }}</td>
                                <td>
                                    <a href="{{ route('participants.show', $participant) }}" class="btn btn-sm btn-outline-purple">Lihat Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">Tidak ada peserta dalam penugasan atau filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="panel-body">{{ $participants->links() }}</div>
        </section>

        <!-- Pop-up Modal Petunjuk Peserta SK (Alpine.js Mobile Responsive) -->
        <div 
            x-cloak 
            x-show="showGuide" 
            class="ui-modal-backdrop" 
            @keydown.escape.window="showGuide = false" 
            @click.self="showGuide = false"
            style="display: none;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-guide-participants-title"
        >
            <div 
                class="ui-modal"
                x-show="showGuide"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="ui-modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            <i class="bi bi-people" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-guide-participants-title" class="h6 mb-0 fw-bold text-purple">Petunjuk Informasi Peserta SK</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showGuide = false" aria-label="Tutup"></button>
                </div>

                <div class="ui-modal-body">
                    <div class="mb-4">
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge text-purple" aria-hidden="true"></i> Status Akun Portal
                        </h3>
                        <p class="text-muted small mb-3">Menandakan apakah calon mahasiswa yang tercantum di SK telah memiliki akun login di portal:</p>
                        <div class="guide-status-list">
                            <div class="guide-status-item">
                                <span class="status-pill diterima_lengkap">Akun Terhubung</span>
                                <p><strong>Akun Terhubung (Hijau):</strong> Peserta telah mengklaim kepesertaannya dan memiliki akun pendaftar aktif.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill draf">Belum Ada Akun</span>
                                <p><strong>Belum Ada Akun (Abu-abu):</strong> Peserta belum melakukan klaim atau permohonan klaimnya masih dalam peninjauan.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: #efe8f6;">

                    <div>
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-list-check text-purple" aria-hidden="true"></i> Status Antrean Verifikasi
                        </h3>
                        <p class="text-muted small mb-3">Menandakan tahapan pendaftaran peserta di dalam sistem:</p>
                        <div class="guide-status-list">
                            <div class="guide-status-item">
                                <span class="status-pill terkirim">Terkirim</span>
                                <p><strong>Terkirim (Biru):</strong> Berkas dan formulir pendaftaran telah dikirimkan, menunggu verifikasi operator.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill diverifikasi">Diverifikasi</span>
                                <p><strong>Diverifikasi (Ungu):</strong> Berkas telah lolos pemeriksaan awal operator.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill perbaikan">Perlu perbaikan</span>
                                <p><strong>Perlu Perbaikan (Kuning):</strong> Operator meminta perbaikan dokumen/biodata.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill diterima_lengkap">Diterima lengkap</span>
                                <p><strong>Diterima Lengkap (Hijau):</strong> Seluruh berkas pendaftaran telah resmi disetujui.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill draf">Belum Masuk Antrean</span>
                                <p><strong>Belum Masuk Antrean / Draf (Abu-abu):</strong> Peserta belum mengisi formulir pendaftaran atau masih dalam draf pengerjaan.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: #efe8f6;">

                    <div>
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-person-gear text-purple" aria-hidden="true"></i> Filter & Penugasan Operator
                        </h3>
                        <p class="text-muted small mb-0">Baik Administrator maupun Operator dapat melihat daftar seluruh peserta SK. Gunakan filter <strong>Operator</strong> untuk menyaring peserta berdasarkan operator yang bertugas atau memilih <em>Belum ditugaskan</em> untuk menemukan peserta yang belum memperoleh pembagian operator.</p>
                    </div>

                    <hr class="my-4" style="border-color: #efe8f6;">

                    <div>
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-excel text-success" aria-hidden="true"></i> Ekspor Seluruh Data ke Excel
                        </h3>
                        <p class="text-muted small mb-0">Gunakan tombol <strong>Ekspor Excel</strong> di kanan atas tabel untuk mengunduh rekapitulasi data seluruh peserta ke file Excel (.xlsx), meliputi status akun, status antrean verifikasi, status nomor WhatsApp, dan seluruh rincian biodata peserta secara lengkap.</p>
                    </div>
                </div>

                <div class="modal-actions border-top p-3 bg-light m-0">
                    <button type="button" class="btn btn-outline-purple" @click="showGuide = false">
                        <i class="bi bi-check2 me-1" aria-hidden="true"></i> Tutup Petunjuk
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
