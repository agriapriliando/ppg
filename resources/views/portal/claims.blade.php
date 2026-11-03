<x-layouts::app title="Klaim & WhatsApp">
    <div x-data="{ showGuide: false }">
        <div class="notice-box info">
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
            <p class="mb-0">Periksa identitas dan kepesertaan sebelum menyetujui klaim. Bukti balasan WhatsApp dan penyerahan akses dicatat secara terpisah.</p>
        </div>

        <section class="workspace-card">
            <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
                <div>
                    <h2 class="h5 mb-1 fw-bold text-purple">Daftar Klaim & Akses WhatsApp</h2>
                    <p class="text-muted small mb-0">Kelola pengajuan klaim akun dan verifikasi kepemilikan nomor WhatsApp calon mahasiswa.</p>
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
                        <label for="claim-search" class="visually-hidden">Pencarian klaim</label>
                        <input id="claim-search" name="search" class="form-control" value="{{ $search }}" placeholder="Cari nama, nomor WA, atau referensi…" maxlength="100">
                    </div>
                    <div class="col-lg-3">
                        <label for="claim-status" class="visually-hidden">Filter status</label>
                        <select id="claim-status" name="status" class="form-select">
                            <option value="">Semua status</option>
                            <option value="pending" @selected($status==='pending')>Menunggu pemeriksaan</option>
                            <option value="needs_information" @selected($status==='needs_information')>Perlu informasi</option>
                            <option value="approved" @selected($status==='approved')>Disetujui</option>
                            <option value="declined" @selected($status==='declined')>Belum disetujui</option>
                            <option value="cancelled" @selected($status==='cancelled')>Dibatalkan</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label for="claim-period" class="visually-hidden">Periode</label>
                        <select id="claim-period" name="period_id" class="form-select">
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
                            <th>Pemohon / periode</th>
                            <th>Jalur</th>
                            <th>Status</th>
                            <th>Penugasan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $claim)
                            <tr>
                                <td>
                                    <strong>{{ $claim->submitted_name }}</strong>
                                    <small class="d-block text-muted">{{ $claim->period->name }} · {{ preg_replace('/.(?=.{4})/', '•', $claim->whatsapp_number) }}</small>
                                </td>
                                <td>{{ $claim->access_path === 'google' ? 'Google' : 'WhatsApp' }}</td>
                                <td>
                                    <span class="status-pill {{ $claim->status }}">
                                        {{ ['pending' => 'Menunggu pemeriksaan', 'approved' => 'Disetujui', 'needs_information' => 'Perlu informasi', 'declined' => 'Belum disetujui', 'cancelled' => 'Dibatalkan'][$claim->status] ?? $claim->status }}
                                    </span>
                                </td>
                                <td>{{ $claim->candidate?->assignedOperator?->name ?? $claim->reviewOwner?->name ?? 'Triase administrator' }}</td>
                                <td>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a class="btn btn-sm btn-outline-purple" href="{{ route('claims.show', $claim) }}">Periksa</a>
                                        @if($claim->status === 'pending')
                                            <form method="post" action="{{ route('claims.destroy', $claim) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus permohonan klaim oleh {{ $claim->submitted_name }} ini?')">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Permohonan Klaim">
                                                    <i class="bi bi-trash" aria-hidden="true"></i> Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted py-4 text-center">Tidak ada permohonan klaim dalam filter atau penugasan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="panel-body">{{ $claims->links() }}</div>
        </section>

        <!-- Pop-up Modal Petunjuk Klaim & WhatsApp (Alpine.js Mobile Responsive) -->
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
            aria-labelledby="modal-guide-claims-title"
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
                            <i class="bi bi-whatsapp" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-guide-claims-title" class="h6 mb-0 fw-bold text-purple">Petunjuk Klaim & Verifikasi WhatsApp</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showGuide = false" aria-label="Tutup"></button>
                </div>

                <div class="ui-modal-body">
                    <div class="mb-4">
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-palette text-purple" aria-hidden="true"></i> Arti Warna Status Klaim
                        </h3>
                        <p class="text-muted small mb-3">Warna status membantu mengenali tahapan klaim secara cepat:</p>

                        <div class="guide-status-list">
                            <div class="guide-status-item">
                                <span class="status-pill pending">Menunggu pemeriksaan</span>
                                <p><strong>Menunggu Pemeriksaan (Biru):</strong> Permohonan klaim baru masuk, menunggu pemeriksaan identitas oleh operator.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill approved">Disetujui</span>
                                <p><strong>Disetujui (Hijau):</strong> Klaim terbukti sah. Akun pendaftar telah berhasil dibuat atau ditautkan dengan data SK.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill needs_information">Perlu informasi</span>
                                <p><strong>Perlu Informasi (Kuning/Oranye):</strong> Operator memerlukan bukti identitas tambahan atau klarifikasi nomor WhatsApp dari pemohon.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill declined">Belum disetujui</span>
                                <p><strong>Belum Disetujui / Ditolak (Merah):</strong> Klaim tidak dapat diverifikasi atau tidak sesuai dengan data kepesertaan SK.</p>
                            </div>
                            <div class="guide-status-item">
                                <span class="status-pill cancelled">Dibatalkan</span>
                                <p><strong>Dibatalkan (Abu-abu):</strong> Klaim dibatalkan karena penyesuaian administratif atau transfer kepemilikan.</p>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: #efe8f6;">

                    <div>
                        <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-check2-circle text-purple" aria-hidden="true"></i> Langkah Pemeriksaan Klaim
                        </h3>
                        <div class="guide-steps-list mt-3">
                            <div class="guide-step">
                                <span class="guide-step-num">1</span>
                                <div class="guide-step-content">
                                    <strong>Cari & Buka Klaim</strong>
                                    <span>Gunakan kolom pencarian nama/WA atau filter status, lalu klik tombol <em>Periksa</em>.</span>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="guide-step-num">2</span>
                                <div class="guide-step-content">
                                    <strong>Cocokkan Peserta SK</strong>
                                    <span>Pastikan nama pemohon dan nomor WhatsApp sesuai dengan peserta pada daftar SK resmi.</span>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="guide-step-num">3</span>
                                <div class="guide-step-content">
                                    <strong>Catat Bukti WhatsApp</strong>
                                    <span>Verifikasi bahwa pesan konfirmasi WhatsApp telah dibalas secara aktif oleh pemohon.</span>
                                </div>
                            </div>
                            <div class="guide-step">
                                <span class="guide-step-num">4</span>
                                <div class="guide-step-content">
                                    <strong>Setujui atau Minta Info</strong>
                                    <span>Klik <em>Setujui Klaim</em> untuk menerbitkan akses pendaftaran, atau tulis catatan publik jika memerlukan konfirmasi tambahan.</span>
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
