<x-layouts::app title="Pemeriksaan Pendaftaran">
    <div x-data="{ showGuide: false }">
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
                                    <strong>{{ ['verify'=>'Diverifikasi','correction'=>'Perbaikan','accept'=>'Diterima lengkap','reopen'=>'Dibuka kembali','contact_recovery'=>'Kontak dipulihkan','ownership_transfer'=>'Kepemilikan dikoreksi'][$review->action]??'Pemeriksaan' }}</strong>
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
                        <form method="post" action="{{ route('reviews.update',$application) }}" novalidate>
                            @csrf
                            <input type="hidden" name="version" value="{{ $application->lock_version }}">
                            <div class="checklist-box">
                                @foreach(['identity_checked'=>'Identitas telah diperiksa','sk_checked'=>'Kepesertaan SK sah dan tidak berkonflik','data_checked'=>'Seluruh biodata dan pendidikan konsisten'] as $key=>$label)
                                    <label>
                                        <input type="checkbox" name="{{ $key }}" class="form-check-input" value="1">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="d-grid gap-2 mt-3">
                                <button class="btn btn-purple" name="action" value="verify" @disabled($application->status!==App\Enums\ApplicationStatus::Submitted)>
                                    Tandai Diverifikasi
                                </button>
                                <button class="btn btn-gold" name="action" value="accept" @disabled($application->status!==App\Enums\ApplicationStatus::Verified || !$verified)>
                                    Tetapkan Diterima Lengkap
                                </button>
                            </div>
                            <hr>
                            <h3 class="h6">Permintaan perbaikan</h3>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach(['Identitas','Alamat','Tambahan','Pendidikan asal','Kontak WhatsApp'] as $group)
                                    <label class="small">
                                        <input type="checkbox" class="form-check-input me-1" name="field_keys[]" value="{{ $group }}">
                                        {{ $group }}
                                    </label>
                                @endforeach
                            </div>
                            <label for="review-note" class="form-label">Catatan spesifik kepada pendaftar</label>
                            <textarea id="review-note" class="form-control mb-3" name="public_note" minlength="10" maxlength="1000" rows="4">{{ \App\Support\FormInput::old('public_note') }}</textarea>
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
                                <span>Centang ketiga kotak persetujuan kepatuhan data. Bila data valid, klik <em>Tandai Diverifikasi</em> lalu <em>Tetapkan Diterima Lengkap</em>. Bila ada kekeliruan, pilih bagian yang keliru, isi catatan perbaikan, lalu klik <em>Minta Perbaikan</em>.</span>
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
