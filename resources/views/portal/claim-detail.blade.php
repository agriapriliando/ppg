<x-layouts::app title="Pemeriksaan Akun">
    @php
        $isStaff = auth()->user()?->isAdministrator() || auth()->user()?->role === \App\Enums\UserRole::Operator;
    @endphp

    <div x-data="{ showCancelApprovalModal: false }">
        <div class="row g-4">
            <div class="col-xl-7">
                <section class="workspace-card mb-4">
                    <div class="panel-heading">
                        <h2>{{ $claim->submitted_name }}</h2>
                        <p>{{ $claim->period->name }} · Jalur {{ $claim->access_path === 'google' ? 'Google' : 'WhatsApp' }}</p>
                    </div>
                    <div class="panel-body">
                        <dl class="detail-list">
                            <dt>Nama permohonan</dt>
                            <dd>{{ $claim->submitted_name }}</dd>
                            <dt>Pengenal resmi</dt>
                            <dd>{{ $claim->submitted_identifier ?? 'Tidak diisi' }}</dd>
                            <dt>Nomor permohonan</dt>
                            <dd>{{ $claim->whatsapp_number }}</dd>
                            <dt>Kandidat kepesertaan</dt>
                            <dd>{{ $claim->candidate?->name_on_sk ?? 'Belum cocok / nama ambigu' }} @if($claim->candidate)· {{ $claim->candidate->sk_number }} · urut {{ $claim->candidate->sk_order }}@endif</dd>
                            <dt>Status klaim</dt>
                            <dd>{{ ['approved'=>'Disetujui','pending'=>'Menunggu pemeriksaan','needs_information'=>'Perlu informasi','declined'=>'Belum disetujui'][$claim->status] ?? $claim->status }}</dd>
                            @if($application)
                                <dt>Nomor PMB</dt>
                                <dd>{{ $application->application_number }}</dd>
                                <dt>Nomor formulir saat ini</dt>
                                <dd>{{ $application->workspace->biodata?->whatsapp_number ?? 'Belum diisi' }}</dd>
                            @endif
                        </dl>

                        @if($claim->status === 'approved')
                            <div class="p-3 mt-3 rounded-3 border bg-light">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Klaim Disetujui
                                        </span>
                                        <p class="small text-muted mb-0 mt-1">Akun dan pendaftaran resmi telah aktif terhubung dengan kepesertaan SK.</p>
                                    </div>
                                    @can('review', $claim)
                                        <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center gap-2" @click="showCancelApprovalModal = true">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                            <span>Batalkan Persetujuan Klaim</span>
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @endif

                        @if(!in_array($claim->status, ['approved','cancelled']))
                            <form method="post" action="{{ route('claims.approve', $claim) }}">
                                @csrf
                                <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                <div class="checklist-box">
                                    <label>
                                        <input type="checkbox" class="form-check-input" name="identity_checked" value="1" required>
                                        <span>Identitas pemohon telah dicocokkan menggunakan pembeda yang sah.</span>
                                    </label>
                                    <label>
                                        <input type="checkbox" class="form-check-input" name="sk_checked" value="1" required>
                                        <span>Kandidat SK tepat, tidak ambigu, dan belum dimiliki pihak lain.</span>
                                    </label>
                                </div>
                                <button class="btn btn-purple mt-3" @disabled(!$claim->candidate)>Setujui Akun</button>
                            </form>
                        @endif
                    </div>
                </section>

                @if($isStaff && !in_array($claim->status, ['approved','cancelled']))
                    <section class="workspace-card">
                        <div class="panel-heading">
                            <h2>Pencocokan administratif</h2>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="{{ route('claims.candidate', $claim) }}">
                                @csrf
                                <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                <label for="claim-candidate" class="form-label">Kandidat resmi pada periode ini</label>
                                <select id="claim-candidate" name="candidate_id" class="form-select mb-3" required>
                                    <option value="">Pilih setelah identitas diperiksa</option>
                                    @foreach($candidates as $candidate)
                                        <option value="{{ $candidate->id }}" @selected($claim->candidate_sk_participant_id === $candidate->id)>
                                            {{ $candidate->name_on_sk }} · {{ $candidate->sk_number }} · {{ $candidate->sk_order }}
                                        </option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-purple">Simpan Kandidat</button>
                            </form>
                        </div>
                    </section>
                @endif
            </div>

            <div class="col-xl-5">
                @if($isStaff && !$claim->candidate && in_array($claim->status, ['pending','needs_information']))
                    <section class="workspace-card mb-4">
                        <div class="panel-heading">
                            <h2>Penanggung jawab klaim</h2>
                            <p>Klaim tanpa kandidat dapat ditriase oleh satu operator periode.</p>
                        </div>
                        <div class="panel-body">
                            <p>Saat ini: {{ $claim->reviewOwner?->name ?? 'Administrator' }}</p>
                            <form method="post" action="{{ route('claims.delegate', $claim) }}">
                                @csrf
                                <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                <label for="claim-owner" class="form-label">Operator pemeriksa *</label>
                                <select id="claim-owner" name="operator_id" class="form-select mb-3" required>
                                    <option value="">Pilih operator periode</option>
                                    @foreach($operators as $operator)
                                        <option value="{{ $operator->id }}" @selected($claim->review_owner_id === $operator->id)>
                                            {{ $operator->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <label for="delegation-reason" class="form-label">Alasan penugasan *</label>
                                <textarea id="delegation-reason" name="reason" class="form-control mb-3" minlength="10" maxlength="1000" required></textarea>
                                <button class="btn btn-outline-purple">Simpan Penanggung Jawab</button>
                            </form>
                        </div>
                    </section>
                @endif

                @if($claim->responses->isNotEmpty())
                    <section class="workspace-card mb-4">
                        <div class="panel-heading">
                            <h2>Informasi tambahan pemohon</h2>
                        </div>
                        <div class="panel-body">
                            @foreach($claim->responses as $response)
                                <div class="border-bottom pb-3 mb-3">
                                    <small class="text-muted">{{ $response->created_at->timezone(config('pmb.business_timezone'))->format('d/m/Y H:i') }} WIB</small>
                                    <p class="mb-0 text-break" style="white-space: pre-line">{{ $response->message }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($isStaff && $claim->status === 'approved' && $claim->access_path === 'whatsapp')
                    <a class="btn btn-outline-danger w-100 mb-4" href="{{ route('admin.whatsapp-recovery.edit', $claim) }}">
                        Pemulihan Saat Nomor Lama Hilang
                    </a>
                @endif

                @if($claim->status === 'approved' && $claim->access_path === 'whatsapp')
                    <section class="workspace-card mb-4">
                        <div class="panel-heading">
                            <h2>Penyerahan akses manual</h2>
                            <p>{{ $claim->requester?->account_state->label() }}</p>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="{{ route('credentials.issue', $claim) }}">
                                @csrf
                                <p class="small text-muted">Lihat akses yang sudah tersedia. Jika belum ada, akses awal akan dibuat.</p>
                                <button class="btn btn-gold">Generate Akun</button>
                            </form>
                            <form method="post" action="{{ route('credentials.regenerate', $claim) }}" class="mt-3">
                                @csrf
                                <p class="small text-muted">Generate ulang mengganti password dan mengeluarkan sesi akun sebelumnya.</p>
                                <button class="btn btn-outline-danger">Generate Ulang Password</button>
                            </form>
                        </div>
                    </section>
                @endif

                @if(!in_array($claim->status, ['approved','cancelled']))
                    <section class="workspace-card">
                        <div class="panel-heading">
                            <h2>Catatan tindak lanjut</h2>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="{{ route('claims.note', $claim) }}">
                                @csrf
                                <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                <label for="claim-result" class="form-label">Hasil pemeriksaan</label>
                                <select id="claim-result" name="status" class="form-select mb-3">
                                    <option value="needs_information">Perlu informasi tambahan</option>
                                    <option value="declined">Klaim belum dapat disetujui</option>
                                </select>
                                <label for="claim-note" class="form-label">Catatan kepada pemohon</label>
                                <textarea id="claim-note" name="public_note" class="form-control mb-3" required minlength="10" maxlength="1000" rows="3">{{ \App\Support\FormInput::old('public_note', $claim->public_note) }}</textarea>
                                <button class="btn btn-outline-purple">Simpan Catatan</button>
                            </form>
                        </div>
                    </section>
                @endif
            </div>
        </div>

        <!-- Pop-up Modal Konfirmasi Pembatalan Persetujuan Klaim (Alpine.js Mobile Responsive) -->
        <div 
            x-cloak 
            x-show="showCancelApprovalModal" 
            class="ui-modal-backdrop" 
            @keydown.escape.window="showCancelApprovalModal = false" 
            @click.self="showCancelApprovalModal = false"
            style="display: none;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-cancel-approval-title"
        >
            <div 
                class="ui-modal"
                x-show="showCancelApprovalModal"
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
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem; background: #dc3545; color: #fff;">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        </span>
                        <h2 id="modal-cancel-approval-title" class="h6 mb-0 fw-bold text-dark">Konfirmasi Pembatalan Persetujuan</h2>
                    </div>
                    <button type="button" class="btn-close" @click="showCancelApprovalModal = false" aria-label="Tutup"></button>
                </div>

                <div class="ui-modal-body">
                    <p class="text-muted small mb-3">
                        Apakah Anda yakin ingin <strong>membatalkan persetujuan</strong> permohonan klaim ini?
                    </p>

                    <div class="p-3 mb-3 rounded-3 border bg-light">
                        <div class="row g-2 small">
                            <div class="col-sm-4 text-muted">Nama Pemohon</div>
                            <div class="col-sm-8 fw-semibold text-dark">{{ $claim->submitted_name }}</div>

                            <div class="col-sm-4 text-muted">Kandidat SK</div>
                            <div class="col-sm-8 text-dark">{{ $claim->candidate?->name_on_sk ?? '-' }}</div>

                            @if($application)
                                <div class="col-sm-4 text-muted">No. PMB</div>
                                <div class="col-sm-8 fw-semibold text-dark">{{ $application->application_number }}</div>
                            @endif

                            <div class="col-sm-4 text-muted">WhatsApp</div>
                            <div class="col-sm-8 text-dark">{{ $claim->whatsapp_number }}</div>
                        </div>
                    </div>

                    <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 small mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-6 mt-1 flex-shrink-0 text-danger"></i>
                        <div>
                            Persetujuan klaim akan dibatalkan, pendaftaran resmi dan relasi kepesertaan SK akan dilepas kembali, serta status klaim kembali menjadi <strong>Menunggu pemeriksaan</strong>.
                        </div>
                    </div>

                    <form id="cancel-approval-form" method="post" action="{{ route('claims.cancel-approval', $claim) }}">
                        @csrf
                        <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirm-cancel-approval" required>
                            <label class="form-check-label small text-dark fw-medium" for="confirm-cancel-approval">
                                Saya memahami konsekuensi pembatalan ini dan telah memeriksa data pemohon.
                            </label>
                        </div>
                    </form>
                </div>

                <div class="modal-actions d-flex align-items-center justify-content-end gap-2 p-3 bg-light border-top">
                    <button type="button" class="btn btn-light border px-3" @click="showCancelApprovalModal = false">
                        Batal
                    </button>
                    <button type="submit" form="cancel-approval-form" class="btn btn-danger px-4 fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        <span>Ya, Batalkan Persetujuan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
