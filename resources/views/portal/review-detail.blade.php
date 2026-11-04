<x-layouts::app title="Pemeriksaan Pendaftaran">
    <div x-data="{ showGuide: false, showAcceptModal: false }">
        @if($application && ($application->participant->roster_state!=='active' || $application->participant->admin_hold))
            <div class="notice-box warning">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <div>
                    <strong>Kepesertaan memerlukan pemeriksaan administratif.</strong>
                    <p class="mb-0">Hubungi pengelola. Status dan bukti sebelumnya merupakan riwayat pemeriksaan dan tidak menggantikan keputusan resmi mengenai kepesertaan saat ini.</p>
                </div>
            </div>
        @endif

        <div class="participant-heading d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2>{{ $application->participant->name_on_sk }}</h2>
                <p class="mb-0">{{ $application->application_number }} · {{ $application->period->name }} · Revisi {{ $application->submission_revision }}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="status-pill {{ $application->status->value }}">{{ $application->status->label() }}</span>
                <button type="button" @click="showGuide = true" class="btn btn-outline-purple d-inline-flex align-items-center gap-2">
                    <i class="bi bi-question-circle" aria-hidden="true"></i>
                    <span>Petunjuk</span>
                </button>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="workspace-card mb-4">
                    <div class="panel-heading">
                        <h2>Biodata dan pendidikan asal</h2>
                        <p>Periksa data peserta dan kecocokan kepesertaan SK.</p>
                    </div>
                    <div class="panel-body">
                        <div class="review-grid">
                            @foreach([1=>'Identitas & kontak',2=>'Alamat',3=>'Tambahan'] as $step=>$heading)
                                <div class="review-block">
                                    <h3>{{ $heading }}</h3>
                                    <dl>
                                        @foreach($fields as $key=>$field)
                                            @continue($field['step']!==$step)
                                            <dt>{{ $field['label'] }}</dt>
                                            <dd>
                                                @if($key==='nik' && !empty($data[$key]))
                                                    <details>
                                                        <summary>•••• •••• •••• {{ substr($data[$key],-4) }}</summary>
                                                        {{ $data[$key] }}
                                                    </details>
                                                @elseif($key==='is_kps_recipient')
                                                    {{ $data[$key]===null?'Belum diisi':($data[$key]?'Ya':'Tidak') }}
                                                @else
                                                    {{ $display[$key]??($data[$key]??'—') }}
                                                @endif
                                            </dd>
                                        @endforeach
                                    </dl>
                                </div>
                            @endforeach
                            <div class="review-block">
                                <h3>Pendidikan asal</h3>
                                <dl>
                                    <dt>Perguruan tinggi</dt>
                                    <dd>{{ $application->workspace->education?->pt_name_snapshot??'Belum diisi' }}</dd>
                                    <dt>Program studi</dt>
                                    <dd>{{ $application->workspace->education?->prodi_name_snapshot??'Belum diisi' }}</dd>
                                    <dt>Tanggal kirim pertama</dt>
                                    <dd>{{ $application->submitted_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i')??'Belum dikirim' }}</dd>
                                    <dt>WhatsApp</dt>
                                    <dd>{{ $verified?'Terverifikasi':'Perlu pemeriksaan' }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </section>

                @include('portal.partials.submission-history')

                <section class="workspace-card">
                    <div class="panel-heading">
                        <h2>Riwayat pemeriksaan</h2>
                    </div>
                    <div class="panel-body">
                        @forelse($application->reviews->sortByDesc('id') as $review)
                            <div class="audit-line">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                                <div>
                                    <strong>{{ ['verify'=>'Diverifikasi','correction'=>'Perbaikan','accept'=>'Diterima lengkap','revert'=>'Diterima lengkap dikembalikan','reopen'=>'Dibuka kembali','contact_recovery'=>'Kontak dipulihkan','ownership_transfer'=>'Kepemilikan dikoreksi'][$review->action]??'Pemeriksaan' }}</strong>
                                    <small>Revisi {{ $review->submission_revision }} · {{ $review->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>
                                    <p class="small mb-0">{{ $review->public_note }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Belum ada pemeriksaan.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <a class="btn btn-purple w-100 mb-2" href="{{ route('reviews.documents', $application) }}">
                    <i class="bi bi-folder2-open me-2"></i>Berkas Pendaftar
                </a>
                <a class="btn btn-outline-secondary w-100 mb-3" href="{{ route('reviews.print', $application) }}" target="_blank">
                    <i class="bi bi-printer me-2" aria-hidden="true"></i>Cetak Formulir Biodata
                </a>

                <section class="workspace-card mb-4">
                    <div class="panel-heading">
                        <h2>Tindak lanjut pendaftaran</h2>
                    </div>
                    <div class="panel-body">
                        <form id="review-action-form" method="post" action="{{ route('reviews.update',$application) }}" novalidate>
                            @csrf
                            <input type="hidden" name="version" value="{{ $application->lock_version }}">
                            <input type="hidden" name="identity_checked" value="1">
                            <input type="hidden" name="sk_checked" value="1">
                            <input type="hidden" name="data_checked" value="1">
                            <div class="d-grid gap-2 mt-3">
                                <button type="button"
                                    @click="showAcceptModal = true"
                                    class="btn btn-gold"
                                    @disabled(!in_array($application->status, [App\Enums\ApplicationStatus::Submitted, App\Enums\ApplicationStatus::Verified], true) || !$verified)>
                                    Tetapkan Diterima Lengkap
                                </button>
                                @if($application->status === App\Enums\ApplicationStatus::Accepted)
                                    <button type="submit" class="btn btn-outline-danger w-100 mt-2 fw-semibold" name="action" value="revert" onclick="return confirm('Apakah Anda yakin ingin mengembalikan status Berkas Diterima Lengkap pendaftar ini ke antrean pemeriksaan?')">
                                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Kembalikan Berkas Diterima Lengkap
                                    </button>
                                @endif
                            </div>
                            <hr>
                            <label for="review-note" class="form-label">Catatan spesifik kepada pendaftar</label>
                            <textarea id="review-note" class="form-control mb-3" name="public_note" minlength="10" maxlength="1000" rows="4" placeholder="Tuliskan catatan perbaikan jika berkas/biodata perlu diperbaiki...">{{ \App\Support\FormInput::old('public_note') }}</textarea>
                            <button class="btn btn-outline-purple w-100" name="action" value="correction" @disabled(!in_array($application->status,[App\Enums\ApplicationStatus::Submitted,App\Enums\ApplicationStatus::Verified],true))>
                                Minta Perbaikan
                            </button>
                            @if(auth()->user()->isAdministrator() && $application->status===App\Enums\ApplicationStatus::Accepted)
                                <button class="btn btn-light border w-100 mt-2" name="action" value="reopen">
                                    Buka Kembali dengan Alasan di Atas
                                </button>
                            @endif
                        </form>
                    </div>
                </section>

                @if($claim)
                    <a class="btn btn-outline-purple w-100 mb-4" href="{{ route('claims.show',$claim) }}">
                        <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>Lihat Akun dan Whatsapp
                    </a>
                @endif

                @if(auth()->user()->isAdministrator())
                    <a class="btn btn-outline-danger w-100 mb-4" href="{{ route('admin.ownership.edit', $application) }}">
                        Koreksi Kepemilikan Akun
                    </a>
                @endif

                <a class="btn btn-light border w-100" href="{{ route('reviews.index') }}">
                    Kembali ke Antrean
                </a>
            </div>
        </div>

        <!-- Modal Petunjuk Pemeriksaan Pendaftaran (Alpine.js Mobile Responsive) -->
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
            aria-labelledby="modal-guide-detail-title"
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
                            <i class="bi bi-journal-check" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-guide-detail-title" class="h6 mb-0 fw-bold text-purple">Petunjuk Pemeriksaan Pendaftaran</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showGuide = false" aria-label="Tutup"></button>
                </div>

                <div class="ui-modal-body">
                    <div class="guide-steps-list">
                        <div class="guide-step">
                            <span class="guide-step-num">1</span>
                            <div class="guide-step-content">
                                <strong>Pemeriksaan Biodata & SK</strong>
                                <span>Periksa kesesuaian Nama, NIK, Tempat/Tanggal Lahir, Alamat, dan Pendidikan Asal dengan penetapan SK resmi pendaftar.</span>
                            </div>
                        </div>
                        <div class="guide-step">
                            <span class="guide-step-num">2</span>
                            <div class="guide-step-content">
                                <strong>Pemeriksaan Berkas Unggahan</strong>
                                <span>Klik tombol <em>Berkas Pendaftar</em> untuk memeriksa dokumen pratinjau ijazah, transkrip nilai, dan berkas persyaratan lainnya.</span>
                            </div>
                        </div>
                        <div class="guide-step">
                            <span class="guide-step-num">3</span>
                            <div class="guide-step-content">
                                <strong>Status Akun & Verifikasi WhatsApp</strong>
                                <span>Gunakan tombol <em>Lihat Akun dan Whatsapp</em> untuk memastikan riwayat klaim akun dan nomor WhatsApp pemohon telah terverifikasi aktif.</span>
                            </div>
                        </div>
                        <div class="guide-step">
                            <span class="guide-step-num">4</span>
                            <div class="guide-step-content">
                                <strong>Keputusan & Tindak Lanjut</strong>
                                <span>Bila data valid dan WhatsApp terverifikasi, klik <em>Tetapkan Diterima Lengkap</em>. Bila ada kekeliruan, isi catatan perbaikan lalu klik <em>Minta Perbaikan</em>.</span>
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

        <!-- Pop-up Modal Konfirmasi Tetapkan Diterima Lengkap (Alpine.js Mobile Responsive) -->
    <div 
        x-cloak 
        x-show="showAcceptModal" 
        class="ui-modal-backdrop" 
        @keydown.escape.window="showAcceptModal = false" 
        @click.self="showAcceptModal = false"
        style="display: none;"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-accept-title"
    >
        <div 
            class="ui-modal"
            x-show="showAcceptModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            style="max-width: 520px;"
        >
            <div class="ui-modal-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem; background: #e0a800;">
                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                    </span>
                    <h2 id="modal-accept-title" class="h6 mb-0 fw-bold text-dark">Konfirmasi Penetapan Diterima Lengkap</h2>
                </div>
                <button type="button" class="btn-close" @click="showAcceptModal = false" aria-label="Tutup"></button>
            </div>

            <div class="ui-modal-body">
                <p class="text-muted small mb-3">
                    Apakah Anda yakin ingin menetapkan pendaftaran calon mahasiswa ini sebagai <strong>Diterima Lengkap</strong>?
                </p>

                <div class="p-3 mb-3 rounded-3 border bg-light">
                    <div class="row g-2 small">
                        <div class="col-sm-4 text-muted">Nama Peserta</div>
                        <div class="col-sm-8 fw-semibold text-dark">{{ $application->participant->name_on_sk }}</div>

                        <div class="col-sm-4 text-muted">No. Pendaftaran</div>
                        <div class="col-sm-8 fw-semibold text-dark">{{ $application->application_number }}</div>

                        <div class="col-sm-4 text-muted">Periode</div>
                        <div class="col-sm-8 text-dark">{{ $application->period->name }}</div>

                        <div class="col-sm-4 text-muted">WhatsApp</div>
                        <div class="col-sm-8 text-success fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i>{{ $application->workspace->biodata?->whatsapp_number ?? '-' }} (Terverifikasi)
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3 small mb-0" role="alert">
                    <i class="bi bi-info-circle-fill fs-6 mt-1 flex-shrink-0 text-warning"></i>
                    <div>
                        Pendaftaran akan resmi disetujui. Anda tetap dapat mengembalikan status berkas ke antrean bila diperlukan di kemudian hari.
                    </div>
                </div>
            </div>

            <div class="modal-actions d-flex align-items-center justify-content-end gap-2 p-3 bg-light border-top">
                <button type="button" class="btn btn-light border px-3" @click="showAcceptModal = false">
                    Batal
                </button>
                <button type="submit" form="review-action-form" name="action" value="accept" class="btn btn-gold px-4 fw-semibold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    <span>Ya, Tetapkan Lengkap</span>
                </button>
            </div>
        </div>
    </div>
</div>
</x-layouts::app>
