<x-layouts::app title="Pemeriksaan Akun">
    @php
        $isStaff = auth()->user()?->isAdministrator() || auth()->user()?->role === \App\Enums\UserRole::Operator;
    @endphp

    <div x-data="{ showCancelApprovalModal: false }">
        <div class="row g-4">
            <div class="col-xl-7">
                <section class="workspace-card mb-4">
                    <div class="panel-heading" x-data="{ editingIdentity: @js($errors->hasAny(['submitted_name', 'submitted_identifier'])) }">
                        <h2>{{ $claim->submitted_name }}</h2>
                        <p>{{ $claim->period->name }} · Jalur {{ $claim->access_path === 'google' ? 'Google' : 'WhatsApp' }}</p>
                        @if($isStaff && $claim->access_path === 'whatsapp' && $claim->status !== 'cancelled' && $claim->period->state !== \App\Enums\PeriodState::Archived)
                            <button type="button" class="btn btn-sm btn-outline-purple" @click="editingIdentity = !editingIdentity" :aria-expanded="editingIdentity" aria-controls="edit-claim-identity">
                                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit Nama & Peg ID
                            </button>
                            <form id="edit-claim-identity" x-cloak x-show="editingIdentity" method="post" action="{{ route('claims.update-identity', $claim) }}" class="mt-3 p-3 border rounded-3 bg-light">
                                @csrf
                                @method('put')
                                <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                <div class="mb-3">
                                    <label for="claim-submitted-name" class="form-label">Nama permohonan *</label>
                                    <input id="claim-submitted-name" name="submitted_name" class="form-control" value="{{ old('submitted_name', $claim->submitted_name) }}" required maxlength="255">
                                    <x-field-error name="submitted_name" />
                                </div>
                                <div class="mb-3">
                                    <label for="claim-submitted-identifier" class="form-label">Peg ID</label>
                                    <input id="claim-submitted-identifier" name="submitted_identifier" type="text" class="form-control" value="{{ old('submitted_identifier', $claim->submitted_identifier) }}" maxlength="100">
                                    <x-field-error name="submitted_identifier" />
                                </div>
                                <p class="small text-muted">Hanya mengubah nama dan Peg ID pemohon akun klaim WhatsApp. Periksa kembali kecocokan kandidat SK sebelum menyetujui klaim.</p>
                                <div class="d-flex flex-column flex-sm-row gap-2">
                                    <button type="submit" class="btn btn-purple">Simpan Nama & Peg ID</button>
                                    <button type="button" class="btn btn-outline-purple" @click="editingIdentity = false">Batal</button>
                                </div>
                            </form>
                        @endif
                    </div>
                    <div class="panel-body">
                        <dl class="detail-list">
                            <dt>Nama permohonan</dt>
                            <dd>{{ $claim->submitted_name }}</dd>
                            <dt>Peg ID</dt>
                            <dd>{{ $claim->submitted_identifier ?? 'Tidak diisi' }}</dd>
                            <dt>Nomor permohonan</dt>
                            <dd x-data="{ editingNumber: @js($errors->has('whatsapp_number')) }">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span>{{ preg_replace('/[^0-9]/', '', $claim->whatsapp_number) }}</span>
                                    @if($isStaff && $claim->status !== 'cancelled' && $claim->period->state !== \App\Enums\PeriodState::Archived)
                                        <button type="button" class="btn btn-sm btn-outline-purple" @click="editingNumber = !editingNumber" :aria-expanded="editingNumber" aria-controls="edit-claim-number">
                                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Edit nomor HP
                                        </button>
                                    @endif
                                </div>
                                @if($isStaff && $claim->status !== 'cancelled' && $claim->period->state !== \App\Enums\PeriodState::Archived)
                                    <form id="edit-claim-number" x-cloak x-show="editingNumber" class="mt-3 p-3 border rounded-3 bg-light" method="post" action="{{ route('claims.update-whatsapp', $claim) }}">
                                        @csrf
                                        @method('put')
                                        <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                        <label for="claim-whatsapp-number" class="form-label">Nomor HP / WhatsApp</label>
                                        <input id="claim-whatsapp-number" type="tel" class="form-control" name="whatsapp_number" value="{{ \App\Support\FormInput::old('whatsapp_number', preg_replace('/[^0-9]/', '', $claim->whatsapp_number)) }}"
                                            required maxlength="30" inputmode="numeric" pattern="628[0-9]{7,12}" placeholder="6281234567890"
                                            @input="let number = $el.value.replace(/[^0-9]/g, ''); if (number.startsWith('08')) number = '62' + number.slice(1); else if (number.startsWith('8')) number = '62' + number; $el.value = number;">
                                        <div class="form-text">Nomor ini juga menjadi tujuan pengiriman akun WhatsApp.</div>
                                        <x-field-error name="whatsapp_number" />
                                        <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                                            <button class="btn btn-purple" type="submit">Simpan Nomor</button>
                                            <button class="btn btn-outline-purple" type="button" @click="editingNumber = false">Batal</button>
                                        </div>
                                    </form>
                                @endif
                            </dd>
                            <dt>Kandidat kepesertaan</dt>
                            <dd>{{ $claim->candidate?->name_on_sk ?? 'Belum cocok / nama ambigu' }} @if($claim->candidate)· {{ $claim->candidate->sk_number }} · urut {{ $claim->candidate->sk_order }}@endif</dd>
                            <dt>Status klaim</dt>
                            <dd>
                                <span @class([
                                    'badge rounded-pill px-3 py-2 border fw-semibold',
                                    'bg-warning-subtle text-warning-emphasis border-warning-subtle' => $claim->status === 'pending',
                                    'bg-success-subtle text-success-emphasis border-success-subtle' => $claim->status === 'approved',
                                    'bg-info-subtle text-info-emphasis border-info-subtle' => $claim->status === 'needs_information',
                                    'bg-danger-subtle text-danger-emphasis border-danger-subtle' => $claim->status === 'declined',
                                    'bg-secondary-subtle text-secondary-emphasis border-secondary-subtle' => !in_array($claim->status, ['pending', 'approved', 'needs_information', 'declined']),
                                ])>
                                    <i @class(['bi me-1', 'bi-clock-history' => $claim->status === 'pending', 'bi-check-circle-fill' => $claim->status === 'approved', 'bi-info-circle-fill' => $claim->status === 'needs_information', 'bi-x-circle-fill' => $claim->status === 'declined', 'bi-dash-circle' => !in_array($claim->status, ['pending', 'approved', 'needs_information', 'declined'])]) aria-hidden="true"></i>
                                    {{ ['approved'=>'Disetujui','pending'=>'Menunggu pemeriksaan','needs_information'=>'Perlu informasi','declined'=>'Belum disetujui', 'cancelled'=>'Dibatalkan'][$claim->status] ?? $claim->status }}
                                </span>
                            </dd>
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
                                <livewire:claim-candidate-search :claim-id="$claim->id" />
                                <x-field-error name="candidate_id" />
                                <button class="btn btn-purple">Setujui Akun</button>
                            </form>
                        @endif
                    </div>
                </section>

            </div>

            <div class="col-xl-5">
                @if($isStaff && !$claim->candidate && in_array($claim->status, ['pending','needs_information']))
                    <section class="workspace-card mb-4">
                        <div class="panel-heading">
                            <h2>Penanggung jawab klaim</h2>
                            <p>Klaim tanpa kandidat dapat ditriase oleh satu operator aktif dari seluruh periode.</p>
                        </div>
                        <div class="panel-body">
                            <p>Saat ini: {{ $claim->reviewOwner?->name ?? 'Administrator' }}</p>
                            <form method="post" action="{{ route('claims.delegate', $claim) }}">
                                @csrf
                                <input type="hidden" name="version" value="{{ $claim->lock_version }}">
                                <label for="claim-owner" class="form-label">Operator pemeriksa *</label>
                                <select id="claim-owner" name="operator_id" class="form-select mb-3" required>
                                    <option value="">Pilih operator aktif</option>
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

                @if($claim->status === 'approved' && $claim->access_path === 'whatsapp')
                    @include('portal.partials.claim-credentials', $credentials)
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
