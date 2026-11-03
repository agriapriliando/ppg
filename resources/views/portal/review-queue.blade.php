<x-layouts::app title="Antrean Verifikasi">
    <div x-data="{ showGuide: false }">
        <section class="workspace-card">
            <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
                <div>
                    <h2 class="h5 mb-1 fw-bold text-purple">Daftar Pendaftaran & Berkas Masuk</h2>
                    <p class="text-muted small mb-0">Kelola dan lakukan verifikasi berkas pendaftaran calon mahasiswa PPG.</p>
                </div>
                <div>
                    <button type="button" @click="showGuide = true" class="btn btn-outline-purple d-inline-flex align-items-center gap-2">
                        <i class="bi bi-question-circle" aria-hidden="true"></i>
                        <span>Petunjuk</span>
                    </button>
                </div>
            </div>

            <div class="panel-body pt-3">
                <form method="get" class="row g-3">
                    <div class="col-lg-4">
                        <label for="queue-search" class="visually-hidden">Cari nama atau nomor pendaftaran</label>
                        <input id="queue-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Cari nama atau nomor PMB…" maxlength="100">
                    </div>
                    <div class="col-lg-3">
                        <label for="queue-status" class="visually-hidden">Status pendaftaran</label>
                        <select id="queue-status" name="status" class="form-select">
                            <option value="">Semua status</option>
                            @foreach(App\Enums\ApplicationStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label for="queue-period" class="visually-hidden">Periode</label>
                        <select id="queue-period" name="period_id" class="form-select">
                            <option value="">Semua periode</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" @selected(request('period_id')==$period->id)>{{ $period->name }}</option>
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
                            <th>Peserta / nomor PMB</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Operator</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $application)
                            <tr>
                                <td>
                                    <strong>{{ $application->participant->name_on_sk }}</strong>
                                    <small class="d-block text-muted">{{ $application->application_number }}</small>
                                </td>
                                <td>{{ $application->period->name }}</td>
                                <td>
                                    <span class="status-pill {{ $application->status->value }}">
                                        {{ $application->status->label() }}
                                    </span>
                                </td>
                                <td>{{ $application->participant->assignedOperator?->name ?? 'Belum ditugaskan' }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-1 text-nowrap">
                                        <a class="btn btn-sm btn-outline-purple" href="{{ route('reviews.show', $application) }}">Periksa</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('reviews.print', $application) }}" target="_blank" title="Cetak formulir biodata dan berkas">
                                            <i class="bi bi-printer me-1" aria-hidden="true"></i>Cetak
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-5">Tidak ada pendaftaran dalam penugasan atau filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="panel-body">{{ $applications->links() }}</div>
        </section>

        <!-- Pop-up Modal Petunjuk (Alpine.js Mobile Responsive) -->
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
            aria-labelledby="modal-guide-title"
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
                            <i class="bi bi-journal-text" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-guide-title" class="h6 mb-0 fw-bold text-purple">Petunjuk Verifikasi Antrean</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showGuide = false" aria-label="Tutup"></button>
                </div>

                <div class="ui-modal-body">
                    <div class="mb-4">
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-palette text-purple" aria-hidden="true"></i> Arti Warna Status Pendaftaran
                        </h3>
                        <p class="text-muted small mb-3">Setiap warna status menandai tahapan berkas peserta dalam antrean verifikasi:</p>
                        
                        <div class="guide-status-list">
                            <div class="guide-status-item">
                                <span class="status-pill terkirim">Terkirim</span>
                                <p><strong>Berkas Baru Masuk (Biru):</strong> Pendaftar telah mengirimkan formulir dan berkas. Menunggu verifikasi awal dari operator.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill diverifikasi">Diverifikasi</span>
                                <p><strong>Lolos Verifikasi Awal (Ungu):</strong> Dokumen dan data biodata telah dinyatakan valid oleh operator, siap untuk penetapan akhir.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill perbaikan">Perlu perbaikan</span>
                                <p><strong>Perlu Perbaikan (Kuning/Oranye):</strong> Terdapat data atau dokumen yang belum sesuai/lengkap. Menunggu perbaikan dari pendaftar.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill diterima_lengkap">Diterima lengkap</span>
                                <p><strong>Selesai & Diterima (Hijau):</strong> Seluruh berkas dan data sah, pendaftaran telah final dan nomor kepesertaan terkonfirmasi.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill draf">Draf</span>
                                <p><strong>Draf / Belum Kirim (Abu-abu):</strong> Calon mahasiswa masih mengisi formulir atau sedang merevisi berkas.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: #efe8f6;">

                    <div>
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-purple" aria-hidden="true"></i> Tahapan Verifikasi Operator
                        </h3>
                        <div class="guide-steps-list mt-3">
                            <div class="guide-step">
                                <span class="guide-step-num">1</span>
                                <div class="guide-step-content">
                                    <strong>Pilih Pendaftar</strong>
                                    <span>Klik tombol <em>Periksa</em> pada baris pendaftar yang berstatus <em>Terkirim</em>.</span>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="guide-step-num">2</span>
                                <div class="guide-step-content">
                                    <strong>Periksa Berkas & Dokumen</strong>
                                    <span>Gunakan tombol <em>Berkas Pendaftar</em> di halaman detail untuk melihat pratinjau dokumen PDF/JPG yang diunggah peserta.</span>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="guide-step-num">3</span>
                                <div class="guide-step-content">
                                    <strong>Cocokkan Biodata & Kepesertaan</strong>
                                    <span>Pastikan NIK, nama lengkap, dan data SK sesuai dengan dokumen resmi kementerian/LPTK.</span>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="guide-step-num">4</span>
                                <div class="guide-step-content">
                                    <strong>Tetapkan Keputusan atau Cetak Formulir</strong>
                                    <span>Jika berkas sah, klik <em>Tandai Diverifikasi</em> lalu <em>Tetapkan Diterima Lengkap</em>. Anda juga dapat menggunakan tombol <em>Cetak</em> untuk mencetak lembar formulir biodata fisik beserta daftar berkas unggahan peserta.</span>
                                </div>
                            </div>
                        </div>
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
