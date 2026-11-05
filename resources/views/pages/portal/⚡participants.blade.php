<?php

use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Enums\PeriodState;
use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daftar Peserta SK')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'operator_id', except: '')]
    public string $operatorId = '';

    #[Url(as: 'period_id', except: '')]
    public string $periodId = '';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    public bool $showCreate = false;
    public string $message = '';
    public array $form = ['admission_period_id' => '', 'name_on_sk' => '', 'sk_number' => '', 'sk_date' => '', 'sk_order' => '', 'official_participant_id' => '', 'internal_note' => ''];

    public function openCreate(): void
    {
        $this->mount();
        $this->reset('form');
        $this->resetValidation();
        $this->form['admission_period_id'] = $this->periodId;
        $this->showCreate = true;
    }

    public function saveParticipant(RecordAudit $audit): void
    {
        $this->mount();
        foreach ($this->form as $key => $value) {
            if (is_string($value)) {
                $this->form[$key] = trim($value);
            }
        }
        $data = $this->validate([
            'form.admission_period_id' => ['required', 'integer', 'exists:admission_periods,id'],
            'form.name_on_sk' => ['required', 'string', 'min:3', 'max:255'],
            'form.sk_number' => ['required', 'string', 'max:100'],
            'form.sk_date' => ['required', 'date_format:Y-m-d'],
            'form.sk_order' => ['required', 'integer', 'min:1', 'max:999999'],
            'form.official_participant_id' => ['nullable', 'string', 'max:100'],
            'form.internal_note' => ['nullable', 'string', 'max:1000'],
        ], ['required' => ':attribute wajib diisi.'], [
            'form.admission_period_id' => 'Periode', 'form.name_on_sk' => 'Nama sesuai SK',
            'form.sk_number' => 'Nomor SK', 'form.sk_date' => 'Tanggal SK', 'form.sk_order' => 'Nomor urut SK',
        ])['form'];

        DB::transaction(function () use ($data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($data['admission_period_id']);
            if ($period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['form.admission_period_id' => 'Periode yang diarsipkan tidak dapat menerima peserta baru.']);
            }
            $data['sk_number'] = mb_strtoupper($data['sk_number']);
            $participants = SkParticipant::query()->where('admission_period_id', $period->id);
            if ((clone $participants)->where('sk_number', $data['sk_number'])->where('sk_order', $data['sk_order'])->exists()) {
                throw ValidationException::withMessages(['form.sk_order' => 'Nomor urut pada SK ini sudah digunakan dalam periode tersebut.']);
            }
            $data['official_participant_id'] = $data['official_participant_id'] ?: null;
            if ($data['official_participant_id'] !== null && (clone $participants)->where('official_participant_id', $data['official_participant_id'])->exists()) {
                throw ValidationException::withMessages(['form.official_participant_id' => 'Peg ID sudah digunakan dalam periode tersebut.']);
            }
            $data['normalized_name'] = mb_strtolower(Str::squish($data['name_on_sk']));
            $participant = SkParticipant::query()->create($data);
            $period->lock_version++;
            $period->save();
            $audit->handle(auth()->user(), 'Menambah peserta SK', $participant, ['period_id' => $period->id]);
        });

        $this->periodId = (string) $data['admission_period_id'];
        $this->reset('form', 'showCreate', 'search', 'operatorId');
        $this->resetPage();
        $this->message = 'Peserta SK berhasil ditambahkan.';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOperatorId(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodId(): void
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
        $user = auth()->user();
        abort_unless($user !== null && $user->account_state === AccountState::Active && ! $user->must_change_password && in_array($user->role, [UserRole::Operator, UserRole::Administrator], true), 403);
    }

    /** @return LengthAwarePaginator<SkParticipant> */
    public function getParticipantsProperty(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $operatorId = $this->operatorId;
        $periodId = $this->periodId;

        return SkParticipant::query()
            ->when(! empty($periodId), fn ($q) => $q->where('admission_period_id', (int) $periodId))
            ->when($operatorId === 'unassigned', fn ($q) => $q->whereNull('assigned_operator_id'))
            ->when(! empty($operatorId) && $operatorId !== 'unassigned', fn ($q) => $q->where('assigned_operator_id', (int) $operatorId))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('name_on_sk', 'like', '%'.$search.'%')->orWhere('official_participant_id', 'like', '%'.$search.'%')))
            ->with(['assignedOperator', 'period', 'application', 'claimedUser'])
            ->orderBy('sk_number')
            ->orderBy('sk_order')
            ->orderBy('id')
            ->paginate($this->perPage);
    }

    /** @return Collection<int, AdmissionPeriod> */
    public function getPeriodsProperty(): Collection
    {
        return AdmissionPeriod::query()->orderByDesc('id')->get();
    }

    /** @return Collection<int, User> */
    public function getOperatorsProperty(): Collection
    {
        return User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->orderBy('name')->get();
    }
};
?>

<div x-data="{ showGuide: false }">
    @if($message)
        <div class="alert alert-success" role="status">{{ $message }}</div>
    @endif
    <section class="workspace-card">
        <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
            <div>
                <h2 class="h5 mb-1 fw-bold text-purple">Daftar Seluruh Peserta SK</h2>
                <p class="text-muted small mb-0">Pantau kepesertaan SK resmi, aktivasi akun, dan progres antrean pendaftaran seluruh operator.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" wire:click="openCreate" class="btn btn-purple d-inline-flex align-items-center gap-2" wire:loading.attr="disabled" @disabled($this->periods->where('state', '!=', PeriodState::Archived)->isEmpty())>
                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                    <span>Tambah Peserta SK</span>
                </button>
                <a href="{{ route('participants.export', array_filter(['search' => $search, 'operator_id' => $operatorId, 'period_id' => $periodId])) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2" title="Ekspor seluruh data peserta & biodata ke Excel">
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
            @if($this->periods->isEmpty())
                <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 mb-3 rounded-3" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <span class="small">Belum ada periode pendaftaran di sistem. Buat periode baru dan unggah SK peserta untuk mulai mengelola daftar peserta resmi.</span>
                    </div>
                    @if(auth()->user()?->isAdministrator())
                        <a href="{{ route('admin.periods') }}" class="btn btn-sm btn-purple">Kelola Periode</a>
                    @endif
                </div>
            @endif

            <div class="row g-3">
                <div class="col-lg-5">
                    <label for="participant-search" class="visually-hidden">Nama atau Peg ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input class="form-control" id="participant-search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau Peg ID…" maxlength="100">
                    </div>
                </div>
                <div class="col-lg-4">
                    <label for="participant-operator" class="visually-hidden">Filter Operator</label>
                    <select class="form-select" id="participant-operator" wire:model.live="operatorId">
                        <option value="">Semua operator</option>
                        <option value="unassigned">Belum ditugaskan</option>
                        @foreach($this->operators as $operator)
                            <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <label for="participant-period" class="visually-hidden">Periode</label>
                    <select class="form-select" id="participant-period" wire:model.live="periodId">
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
                    Total: <strong>{{ $this->participants->total() }}</strong> peserta
                </div>
            </div>
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
                    @forelse($this->participants as $participant)
                        <tr wire:key="participant-{{ $participant->id }}">
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

        <div class="d-flex flex-wrap align-items-center justify-content-end p-3 border-top">
            <div>
                {{ $this->participants->links() }}
            </div>
        </div>
    </section>

    <div x-cloak x-show="$wire.showCreate" x-trap.inert.noscroll="$wire.showCreate"
        @keydown.escape.window="$wire.showCreate = false" @click.self="$wire.showCreate = false"
        class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="create-participant-title">
        <div class="ui-modal">
            <div class="ui-modal-header">
                <h2 id="create-participant-title" class="h6 fw-bold text-purple">Tambah Peserta SK</h2>
                <button type="button" class="btn-close" @click="$wire.showCreate = false" aria-label="Tutup"></button>
            </div>
            <form wire:submit="saveParticipant">
                <div class="ui-modal-body">
                    <p class="text-muted small">Masukkan data sesuai SK resmi. Kolom bertanda * wajib diisi.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="create-participant-period" class="form-label">Periode *</label>
                            <select id="create-participant-period" class="form-select" wire:model="form.admission_period_id" required>
                                <option value="">Pilih periode</option>
                                @foreach($this->periods->where('state', '!=', PeriodState::Archived) as $period)
                                    <option wire:key="create-period-{{ $period->id }}" value="{{ $period->id }}">{{ $period->name }} ({{ $period->academic_year }})</option>
                                @endforeach
                            </select>
                            <x-field-error name="form.admission_period_id" />
                        </div>
                        <div class="col-12">
                            <label for="create-participant-name" class="form-label">Nama sesuai SK *</label>
                            <input id="create-participant-name" class="form-control" wire:model="form.name_on_sk" required minlength="3" maxlength="255" autocomplete="off">
                            <x-field-error name="form.name_on_sk" />
                        </div>
                        <div class="col-sm-6">
                            <label for="create-participant-sk" class="form-label">Nomor SK *</label>
                            <input id="create-participant-sk" class="form-control" wire:model="form.sk_number" required maxlength="100">
                            <x-field-error name="form.sk_number" />
                        </div>
                        <div class="col-sm-6">
                            <label for="create-participant-date" class="form-label">Tanggal SK *</label>
                            <input id="create-participant-date" type="date" class="form-control" wire:model="form.sk_date" required>
                            <x-field-error name="form.sk_date" />
                        </div>
                        <div class="col-sm-6">
                            <label for="create-participant-order" class="form-label">Nomor urut SK *</label>
                            <input id="create-participant-order" type="number" class="form-control" wire:model="form.sk_order" required min="1" max="999999">
                            <x-field-error name="form.sk_order" />
                        </div>
                        <div class="col-sm-6">
                            <label for="create-participant-id" class="form-label">Peg ID</label>
                            <input id="create-participant-id" class="form-control" wire:model="form.official_participant_id" maxlength="100">
                            <x-field-error name="form.official_participant_id" />
                        </div>
                        <div class="col-12">
                            <label for="create-participant-note" class="form-label">Catatan internal</label>
                            <textarea id="create-participant-note" class="form-control" wire:model="form.internal_note" rows="3" maxlength="1000"></textarea>
                            <x-field-error name="form.internal_note" />
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top p-3 bg-light">
                    <button type="button" class="btn btn-outline-purple" @click="$wire.showCreate = false" wire:loading.attr="disabled" wire:target="saveParticipant">Batal</button>
                    <button type="submit" class="btn btn-purple" wire:loading.attr="disabled" wire:target="saveParticipant">
                        <span wire:loading.remove wire:target="saveParticipant">Simpan Peserta</span>
                        <span wire:loading wire:target="saveParticipant">Menyimpan…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

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
                    <p class="text-muted small mb-3">Menandakan status akun calon mahasiswa yang tercantum di SK resmi:</p>
                    <div class="guide-status-list">
                        <div class="guide-status-item">
                            <span class="status-pill diterima_lengkap">Akun Terhubung</span>
                            <p><strong>Akun Terhubung (Hijau):</strong> Peserta telah mengklaim kepesertaannya dan memiliki akun pendaftar yang aktif.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill perbaikan">Menunggu Aktivasi</span>
                            <p><strong>Menunggu Aktivasi (Kuning):</strong> Akun peserta telah disetujui, namun peserta belum mengaktifkan kata sandi.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill declined">Nonaktif</span>
                            <p><strong>Nonaktif (Merah):</strong> Akun peserta dinonaktifkan sementara oleh pengelola.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill draf">Belum Ada Akun</span>
                            <p><strong>Belum Ada Akun (Abu-abu):</strong> Peserta belum melakukan klaim akun atau permohonan klaimnya masih dalam peninjauan.</p>
                        </div>
                    </div>
                </div>

                <hr class="my-4" style="border-color: #efe8f6;">

                <div>
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-list-check text-purple" aria-hidden="true"></i> Status Antrean Verifikasi
                    </h3>
                    <p class="text-muted small mb-3">Menandakan tahapan pendaftaran berkas peserta di dalam sistem:</p>
                    <div class="guide-status-list">
                        <div class="guide-status-item">
                            <span class="status-pill terkirim">Terkirim</span>
                            <p><strong>Terkirim (Biru):</strong> Berkas dan formulir telah dikirim oleh pendaftar. Operator dapat langsung memeriksa dan menetapkan <em>Diterima Lengkap</em> atau meminta perbaikan jika ada kekeliruan data.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill diterima_lengkap">Diterima lengkap</span>
                            <p><strong>Diterima Lengkap (Hijau):</strong> Berkas dan biodata pendaftaran telah resmi disetujui. Operator dapat mengembalikan status berkas ke antrean bila diperlukan.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill perbaikan">Perlu perbaikan</span>
                            <p><strong>Perlu Perbaikan (Kuning):</strong> Operator memberikan catatan perbaikan pada berkas atau biodata yang perlu direvisi pendaftar.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill draf">Belum Masuk Antrean</span>
                            <p><strong>Belum Masuk Antrean (Abu-abu):</strong> Peserta belum mengisi formulir pendaftaran atau masih dalam draf pengerjaan.</p>
                        </div>
                    </div>
                </div>

                <hr class="my-4" style="border-color: #efe8f6;">

                <div>
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-sliders text-purple" aria-hidden="true"></i> Filter & Paginasi Cepat (Livewire)
                    </h3>
                    <p class="text-muted small mb-2">Halaman ini telah menggunakan Livewire reaktif tanpa reload halaman:</p>
                    <ul class="text-muted small ps-3 mb-0">
                        <li class="mb-1"><strong>Filter Real-time:</strong> Pencarian nama/Peg ID, pilihan operator penugasan, dan filter periode langsung memperbarui daftar secara instan.</li>
                        <li class="mb-1"><strong>Jumlah Data Per Halaman:</strong> Tombol pilihan <strong>10, 25, 50, 100</strong> diletakkan di atas tabel untuk memudahkan navigasi data sebelum menelusuri tabel.</li>
                        <li><strong>Pindah Halaman Instan:</strong> Berpindah halaman navigasi berlangsung cepat tanpa memuat ulang seluruh halaman browser.</li>
                    </ul>
                </div>

                <hr class="my-4" style="border-color: #efe8f6;">

                <div>
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-excel text-success" aria-hidden="true"></i> Ekspor Data ke Excel
                    </h3>
                    <p class="text-muted small mb-0">Klik tombol <strong>Ekspor Excel</strong> di kanan atas untuk mengunduh rekapitulasi data seluruh peserta ke file Excel (.xlsx), meliputi status akun, status antrean verifikasi, status nomor WhatsApp, dan seluruh rincian biodata peserta secara lengkap sesuai filter yang sedang aktif.</p>
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
