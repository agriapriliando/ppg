<?php

use App\Actions\Admissions\RecheckClaimMatch;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Klaim & WhatsApp')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'period_id', except: '')]
    public string $periodId = '';

    #[Url(as: 'operator_id', except: '')]
    public string $operatorId = '';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    public string $matchMessage = '';
    public string $matchResult = '';

    public function recheckMatch(int $claimId, int $version, RecheckClaimMatch $recheck): void
    {
        $this->resetValidation('match');
        $this->reset('matchMessage', 'matchResult');
        $claim = SkClaim::query()->findOrFail($claimId);
        $this->matchResult = $recheck->handle(auth()->user(), $claim, $version);
        if ($this->matchResult === 'matched') {
            $this->resetPage();
        }
        $this->matchMessage = match ($this->matchResult) {
            'matched' => 'Kandidat ditemukan dan berhasil dicocokkan untuk '.$claim->submitted_name.'. Buka Periksa untuk melanjutkan pemeriksaan akun.',
            'ambiguous' => 'Ditemukan lebih dari satu peserta SK yang sesuai untuk '.$claim->submitted_name.'. Buka Periksa dan pilih kandidat secara manual.',
            default => 'Belum ditemukan peserta SK yang sesuai untuk '.$claim->submitted_name.'. Periksa nama, Peg ID, periode, dan kelayakan peserta melalui tombol Periksa.',
        };
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodId(): void
    {
        $this->resetPage();
    }

    public function updatedOperatorId(): void
    {
        $this->resetPage();
    }

    public function setPerPage(int $perPage): void
    {
        if (in_array($perPage, [10, 25, 50, 100], true)) {
            $this->perPage = $perPage;
            $this->resetPage();
        }
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', SkClaim::class);
    }

    /** @return LengthAwarePaginator<SkClaim> */
    public function getClaimsProperty(): LengthAwarePaginator
    {
        Gate::authorize('viewAny', SkClaim::class);

        $claims = SkClaim::query()->with(['period', 'candidate.assignedOperator', 'reviewOwner', 'requester:id,login_id']);

        $search = trim($this->search);
        if ($search !== '') {
            $claims->where(function ($query) use ($search): void {
                $query->where('submitted_name', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%")
                    ->orWhere('submitted_identifier', 'like', "%{$search}%")
                    ->orWhere('public_reference', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn ($requester) => $requester->where('login_id', 'like', "%{$search}%"));
            });
        }

        if (! empty($this->status)) {
            $claims->where('status', $this->status);
        }

        if (! empty($this->periodId)) {
            $claims->where('admission_period_id', (int) $this->periodId);
        }

        if ($this->operatorId === 'unassigned') {
            $claims->where(fn ($query) => $query->whereHas('candidate', fn ($candidate) => $candidate->whereNull('assigned_operator_id'))
                ->orWhere(fn ($unmatched) => $unmatched->whereNull('candidate_sk_participant_id')->whereNull('review_owner_id')));
        } elseif ($this->operatorId !== '') {
            $operatorId = (int) $this->operatorId;
            $claims->where(fn ($query) => $query->whereHas('candidate', fn ($candidate) => $candidate->where('assigned_operator_id', $operatorId))
                ->orWhere(fn ($unmatched) => $unmatched->whereNull('candidate_sk_participant_id')->where('review_owner_id', $operatorId)));
        }

        return $claims->orderBy('status')->orderBy('created_at')->orderBy('id')->paginate($this->perPage);
    }

    /** @return Collection<int, AdmissionPeriod> */
    public function getPeriodsProperty(): Collection
    {
        return AdmissionPeriod::query()->orderByDesc('id')->get();
    }

    /** @return Collection<int, User> */
    public function getOperatorsProperty(): Collection
    {
        return User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->orderBy('name')->orderBy('id')->get();
    }
};
?>

<div x-data="{ showGuide: false, deletingClaim: null, deleteConfirmed: false, deleting: false }">
    @if($matchMessage)
        <div class="alert alert-{{ $matchResult === 'matched' ? 'success' : 'warning' }}" role="status">{{ $matchMessage }}</div>
    @endif
    <x-field-error name="match" />
    <div class="notice-box info">
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
        <p class="mb-0">Periksa identitas dan kepesertaan sebelum menyetujui klaim. Bukti balasan WhatsApp dan penyerahan akses dicatat secara terpisah.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <section class="workspace-card">
        <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
            <div>
                <h2 class="h5 mb-1 fw-bold text-purple">Akun Peserta</h2>
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
            @if($this->periods->isEmpty())
                <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 mb-3 rounded-3" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <span class="small">Belum ada periode pendaftaran aktif. Permohonan klaim akun calon mahasiswa akan masuk saat periode pendaftaran dibuka.</span>
                    </div>
                    @can('viewAny', AdmissionPeriod::class)
                        <a href="{{ route('admin.periods') }}" class="btn btn-sm btn-purple">Kelola Periode</a>
                    @endcan
                </div>
            @endif

            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <label for="claim-search" class="form-label">Pencarian klaim</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input id="claim-search" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari nama, ID login, atau nomor WA…" maxlength="100">
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="claim-operator" class="form-label">Operator</label>
                    <select id="claim-operator" wire:model.live="operatorId" class="form-select">
                        <option value="">Semua operator</option>
                        <option value="unassigned">Belum ada operator</option>
                        @foreach($this->operators as $operator)
                            <option value="{{ $operator->id }}" wire:key="claim-operator-{{ $operator->id }}">{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="claim-status" class="form-label">Status</label>
                    <select id="claim-status" wire:model.live="status" class="form-select">
                        <option value="">Semua status</option>
                        <option value="pending">Menunggu pemeriksaan</option>
                        <option value="needs_information">Perlu informasi</option>
                        <option value="approved">Disetujui</option>
                        <option value="declined">Belum disetujui</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="claim-period" class="form-label">Periode</label>
                    <select id="claim-period" wire:model.live="periodId" class="form-select">
                        <option value="">Semua periode</option>
                        @foreach($this->periods as $period)
                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 mt-3 border-top">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted fw-semibold">Jumlah per halaman:</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Jumlah per halaman">
                        @foreach([10, 25, 50, 100] as $size)
                            <button type="button"
                                wire:click="setPerPage({{ $size }})"
                                class="btn {{ $perPage === $size ? 'btn-purple' : 'btn-outline-purple' }}">
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="small text-muted">
                    Total: <strong>{{ $this->claims->total() }}</strong> klaim
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table portal-table">
                <thead>
                    <tr>
                        <th>Pemohon / periode</th>
                        <th>Jalur</th>
                        <th>Status</th>
                        <th>Kandidat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->claims as $claim)
                        <tr wire:key="claim-{{ $claim->id }}">
                            <td>
                                <strong>{{ $claim->submitted_name }}</strong>
                                <small class="d-block text-purple fw-semibold">Peg ID: {{ $claim->submitted_identifier ?: ($claim->candidate?->official_participant_id ?: 'Belum diisi') }}</small>
                                <small class="d-block text-muted">ID login: <span class="fw-semibold">{{ $claim->requester?->login_id ?: 'Belum tersedia' }}</span></small>
                                <small class="d-block text-muted">Ref: {{ $claim->public_reference }} · WA: {{ $claim->whatsapp_number }}</small>
                                <small class="d-block text-muted">{{ $claim->period->name }}</small>
                            </td>
                            <td>
                                <span class="badge text-bg-light border">
                                    <i class="bi bi-{{ $claim->access_path === 'google' ? 'google text-danger' : 'whatsapp text-success' }} me-1" aria-hidden="true"></i>
                                    {{ $claim->access_path === 'google' ? 'Google' : 'WhatsApp' }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill {{ $claim->status }}">
                                    {{ ['approved'=>'Disetujui','pending'=>'Menunggu pemeriksaan','needs_information'=>'Perlu informasi','declined'=>'Belum disetujui','cancelled'=>'Dibatalkan'][$claim->status] ?? $claim->status }}
                                </span>
                            </td>
                            <td>
                                @if($claim->candidate)
                                    <strong>{{ $claim->candidate->name_on_sk }}</strong>
                                    <small class="d-block text-muted">SK: {{ $claim->candidate->sk_number }} / urut {{ $claim->candidate->sk_order }}</small>
                                    <small class="d-block text-muted">Operator: {{ $claim->candidate->assignedOperator?->name ?? 'Belum ditugaskan' }}</small>
                                @else
                                    <span class="text-muted">Nama belum cocok</span>
                                    <small class="d-block text-muted">Triase: {{ $claim->reviewOwner?->name ?? 'Administrator' }}</small>
                                    @if(in_array($claim->status, ['pending', 'needs_information'], true) && $claim->period->state !== \App\Enums\PeriodState::Archived)
                                        @can('review', $claim)
                                            <button type="button" class="btn btn-sm btn-outline-purple mt-2"
                                                wire:click="recheckMatch({{ $claim->id }}, {{ $claim->lock_version }})" wire:loading.attr="disabled" wire:target="recheckMatch">
                                                <span wire:loading.remove wire:target="recheckMatch({{ $claim->id }}, {{ $claim->lock_version }})"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Cek Kesesuaian</span>
                                                <span wire:loading wire:target="recheckMatch({{ $claim->id }}, {{ $claim->lock_version }})"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Memeriksa…</span>
                                            </button>
                                        @endcan
                                    @endif
                                @endif
                            </td>
                            <td>
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a class="btn btn-sm btn-outline-purple" href="{{ route('claims.show', $claim) }}">Periksa</a>
                                    @if($claim->status !== 'approved' || $claim->access_path === 'whatsapp')
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus Permohonan Klaim"
                                                @click="deleteConfirmed = false; deletingClaim = @js(['url' => route('claims.destroy', $claim), 'name' => $claim->submitted_name, 'number' => $claim->whatsapp_number, 'period' => $claim->period->name, 'version' => $claim->lock_version, 'approved' => $claim->status === 'approved']);">
                                                <i class="bi bi-trash" aria-hidden="true"></i> Hapus
                                            </button>
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

        <div class="d-flex flex-wrap align-items-center justify-content-end p-3 border-top">
            <div>
                {{ $this->claims->links() }}
            </div>
        </div>
    </section>

    <div x-cloak x-show="deletingClaim !== null" x-trap.inert.noscroll="deletingClaim !== null"
        class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="delete-claim-title"
        @keydown.escape.window="if (!deleting) deletingClaim = null" @click.self="if (!deleting) deletingClaim = null">
        <div class="ui-modal">
            <div class="ui-modal-header">
                <h2 id="delete-claim-title" class="text-danger">Hapus Permohonan Klaim?</h2>
                <button type="button" class="btn-close" @click="deletingClaim = null" :disabled="deleting" aria-label="Tutup"></button>
            </div>
            <form method="post" :action="deletingClaim?.url" @submit="deleting = true">
                @csrf
                @method('delete')
                <input type="hidden" name="version" :value="deletingClaim?.version">
                <div class="ui-modal-body">
                    <dl class="p-3 border rounded bg-light" style="overflow-wrap:anywhere">
                        <dt>Nama pemohon</dt><dd x-text="deletingClaim?.name"></dd>
                        <dt>Nomor WhatsApp</dt><dd x-text="deletingClaim?.number"></dd>
                        <dt>Periode</dt><dd class="mb-0" x-text="deletingClaim?.period"></dd>
                    </dl>
                    <div class="alert alert-danger" x-show="!deletingClaim?.approved">Permohonan klaim beserta tanggapan dan catatan terkait akan dihapus permanen. Tindakan ini tidak dapat dibatalkan melalui aplikasi.</div>
                    <div class="alert alert-danger" x-show="deletingClaim?.approved">Klaim WhatsApp dan akses akun lama akan dihapus. ID login, kata sandi, dan sesi lama tidak dapat digunakan lagi. Peserta perlu mengajukan klaim ulang untuk mendapatkan akses baru.</div>
                    <p x-show="deletingClaim?.approved">Data peserta SK, biodata, pendaftaran, riwayat, dan seluruh berkas tetap tersimpan. Klaim ulang yang disetujui akan terhubung ke pendaftaran yang sama.</p>
                    <p x-show="!deletingClaim?.approved">Data peserta SK, akun login, biodata, pendaftaran, dan berkas peserta tetap tersimpan.</p>
                    <label class="d-flex align-items-start gap-2"><input type="checkbox" class="form-check-input flex-shrink-0" x-model="deleteConfirmed" required><span>Saya memahami dan menyetujui penghapusan permohonan klaim ini.</span></label>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top p-3">
                    <button type="button" class="btn btn-light border" @click="deletingClaim = null" :disabled="deleting">Batal</button>
                    <button type="submit" class="btn btn-danger" :disabled="!deleteConfirmed || deleting" x-text="deleting ? 'Menghapus...' : (deletingClaim?.approved ? 'Hapus Akun & Klaim WhatsApp' : 'Hapus Klaim')"></button>
                </div>
            </form>
        </div>
    </div>

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
                    <h2 id="modal-guide-claims-title" class="h6 mb-0 fw-bold text-purple">Petunjuk Pemeriksaan Klaim & WhatsApp</h2>
                </div>
                <button type="button" class="btn-close" @click="showGuide = false" aria-label="Tutup"></button>
            </div>

            <div class="ui-modal-body">
                <div class="mb-4">
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-purple" aria-hidden="true"></i> Apa itu Klaim Akun?
                    </h3>
                    <p class="text-muted small mb-0">Klaim akun diajukan oleh calon mahasiswa untuk menghubungkan nomor WhatsApp atau akun Google mereka dengan data resmi peserta yang tercantum di Surat Keputusan (SK) penerimaan PPG.</p>
                </div>

                <hr class="my-4" style="border-color: #efe8f6;">

                <div class="mb-4">
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-tag text-purple" aria-hidden="true"></i> Arti Status Klaim
                    </h3>
                    <div class="guide-status-list">
                        <div class="guide-status-item">
                            <span class="status-pill pending">Menunggu pemeriksaan</span>
                            <p><strong>Menunggu Pemeriksaan (Kuning):</strong> Permohonan baru masuk. Operator/Admin perlu mencocokkan identitas pemohon dengan data SK resmi.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill needs_information">Perlu informasi</span>
                            <p><strong>Perlu Informasi (Biru):</strong> Operator meminta keterangan tambahan atau konfirmasi bukti kepada pemohon.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill approved">Disetujui</span>
                            <p><strong>Disetujui (Hijau):</strong> Identitas terverifikasi cocok. Akun pendaftar telah berhasil dikaitkan dengan nomor SK.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill declined">Belum disetujui</span>
                            <p><strong>Belum Disetujui (Merah):</strong> Permohonan ditolak karena identitas tidak sesuai atau tidak memenuhi syarat.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill cancelled">Dibatalkan</span>
                            <p><strong>Dibatalkan (Abu-abu):</strong> Permohonan dibatalkan oleh pemohon atau digantikan pengajuan baru.</p>
                        </div>
                    </div>
                </div>

                <hr class="my-4" style="border-color: #efe8f6;">

                <div>
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle text-purple" aria-hidden="true"></i> Langkah Pemeriksaan Klaim
                    </h3>
                    <ol class="small text-muted ps-3 mb-0" style="line-height: 1.7;">
                        <li>Klik tombol <strong>Periksa</strong> pada baris klaim yang ingin ditinjau.</li>
                        <li>Pastikan nama pemohon dan nomor WhatsApp sesuai dengan arsip dokumen pendaftar.</li>
                        <li>Pilih kandidat peserta SK yang tepat apabila sistem belum mencocokkan secara otomatis.</li>
                        <li>Klik <strong>Setujui Akun</strong> jika seluruh data identitas telah valid dan dapat dipertanggungjawabkan.</li>
                    </ol>
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
