<?php

use App\Actions\Admissions\SaveAdmissionPeriod;
use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Periode & Gelombang')] class extends Component
{
    use WithPagination;

    #[Locked] public ?int $editingId = null;
    #[Locked] public int $version = 0;
    #[Locked] public ?int $documentPeriodId = null;
    public array $form = ['code'=>'', 'name'=>'', 'academic_year'=>'', 'state'=>'draft', 'opens_at'=>'', 'closes_at'=>'', 'allow_correction_after_close'=>false, 'correction_closes_at'=>'', 'is_public'=>false];
    public string $message = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AdmissionPeriod::class);
    }

    public function edit(int $id): void
    {
        $period = AdmissionPeriod::findOrFail($id);
        Gate::authorize('update', $period);
        $this->editingId = $period->id;
        $this->version = $period->lock_version;
        foreach (array_keys($this->form) as $key) {
            $value = $period->getAttribute($key);
            $this->form[$key] = match($key) {
                'state' => $period->state->value,
                'opens_at', 'closes_at', 'correction_closes_at' => $value?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') ?? '',
                default => $value,
            };
        }
        $this->resetValidation();
        $this->message = '';
    }

    public function updatedFormState(): void
    {
        if ($this->form['state'] !== PeriodState::Published->value) {
            $this->form['is_public'] = false;
            $this->resetValidation('is_public');
        }
    }

    public function reopen(int $id): void
    {
        $this->edit($id);
        $this->form['state'] = PeriodState::Published->value;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'version', 'form');
        $this->resetValidation();
    }

    public function save(SaveAdmissionPeriod $save): void
    {
        $period = $save->handle(auth()->user(), $this->editingId, $this->version, $this->form);
        $this->cancel();
        $this->documentPeriodId = $period->id;
        $this->message = 'Periode berhasil disimpan.';
        $this->resetPage();
    }

    public function toggleClosed(int $id, int $version, RecordAudit $audit): void
    {
        DB::transaction(function () use ($id, $version, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($id);
            Gate::authorize('update', $period);
            abort_unless($period->lock_version === $version, 409, 'Periode telah berubah. Muat ulang halaman.');
            abort_unless($period->state === PeriodState::Published, 422);
            if ($period->manual_closed_at !== null && now()->greaterThanOrEqualTo($period->closes_at)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['period' => 'Perbarui waktu tutup terlebih dahulu sebelum membuka kembali periode yang jadwalnya sudah berakhir.']);
            }
            $period->manual_closed_at = $period->manual_closed_at === null ? now() : null;
            $period->lock_version++;
            $period->save();
            $audit->handle(auth()->user(), $period->manual_closed_at ? 'Menutup periode manual' : 'Membuka kembali periode', $period);
        });
        $this->message = 'Status periode berhasil diperbarui.';
    }

    public function with(): array
    {
        Gate::authorize('viewAny', AdmissionPeriod::class);
        return ['periods'=>AdmissionPeriod::query()->withCount(['periodDocuments' => fn ($query) => $query->where('status', 'available')])->orderByDesc('id')->paginate(10)];
    }

    public function manageDocuments(int $id): void
    {
        Gate::authorize('update', AdmissionPeriod::findOrFail($id));
        $this->documentPeriodId = $id;
    }

    public function closeDocuments(): void
    {
        $this->documentPeriodId = null;
    }

    #[On('period-documents-updated')]
    public function refreshDocumentCounts(): void
    {
        Gate::authorize('viewAny', AdmissionPeriod::class);
    }
};
?>

<div>
    @if($message)
        <div class="alert alert-success alert-dismissible fade show mb-4 d-flex align-items-center gap-2" role="status">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ $message }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Kolom Formulir Pembuatan / Pengubahan Periode -->
        <div class="col-xl-6">
            <section class="workspace-card h-100">
                <div class="panel-heading d-flex align-items-center justify-content-between gap-3 border-bottom pb-3">
                    <div>
                        <h2 class="h5 mb-1 fw-bold text-purple d-flex align-items-center gap-2">
                            <i class="bi {{ $editingId ? 'bi-pencil-square' : 'bi-plus-circle' }}"></i>
                            <span>{{ $editingId ? 'Ubah Periode' : 'Buat Periode Penerimaan' }}</span>
                        </h2>
                        <p class="text-muted small mb-0">Isi identitas dan jadwal operasional PMB (WIB / UTC+7). Setelah disimpan, tambahkan dokumen melalui tombol Berkas pada periode.</p>
                    </div>
                    @if($editingId)
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                            Mode Edit
                        </span>
                    @endif
                </div>

                <div class="panel-body pt-3">
                    <form wire:submit="save">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="period-code" class="form-label small fw-semibold">Kode Periode *</label>
                                <input id="period-code" class="form-control" wire:model="form.code" placeholder="Contoh: 2026PPG1" required maxlength="40">
                                <div class="form-text" style="font-size: 0.72rem;">Pengenal unik sistem (alfanumerik/tanda hubung).</div>
                                <x-field-error name="code" />
                            </div>

                            <div class="col-md-6">
                                <label for="period-name" class="form-label small fw-semibold">Nama Periode *</label>
                                <input id="period-name" class="form-control" wire:model="form.name" placeholder="Contoh: PPG Guru Tertentu 2026" required maxlength="255">
                                <div class="form-text" style="font-size: 0.72rem;">Nama resmi yang ditampilkan ke publik dan peserta.</div>
                                <x-field-error name="name" />
                            </div>

                            <div class="col-12">
                                <label for="period-academic_year" class="form-label small fw-semibold">Tahun Akademik *</label>
                                <input id="period-academic_year" class="form-control" wire:model="form.academic_year" placeholder="Contoh: 2026/2027" required maxlength="20">
                                <x-field-error name="academic_year" />
                            </div>

                            <div class="col-md-6">
                                <label for="period-opens" class="form-label small fw-semibold">
                                    <i class="bi bi-calendar-check text-success me-1"></i>Waktu Buka (WIB) *
                                </label>
                                <input id="period-opens" type="datetime-local" class="form-control" wire:model="form.opens_at" required>
                                <x-field-error name="opens_at" />
                            </div>

                            <div class="col-md-6">
                                <label for="period-closes" class="form-label small fw-semibold">
                                    <i class="bi bi-calendar-x text-danger me-1"></i>Waktu Tutup (WIB) *
                                </label>
                                <input id="period-closes" type="datetime-local" class="form-control" wire:model="form.closes_at" required>
                                <x-field-error name="closes_at" />
                            </div>

                            <div class="col-12">
                                <label for="period-state" class="form-label small fw-semibold">Status Siklus Periode *</label>
                                <select id="period-state" class="form-select" wire:model.live="form.state">
                                    @foreach(PeriodState::cases() as $state)
                                        <option value="{{ $state->value }}">{{ $state->label() }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text" style="font-size: 0.72rem;">Untuk membuka kembali periode arsip, pilih "Dipublikasikan", sesuaikan jadwal, lalu simpan perubahan. Penerimaan pendaftaran mengikuti waktu buka dan tutup.</div>
                                <x-field-error name="state" />
                            </div>

                            <div class="col-12">
                                <div class="p-3 rounded-3 border bg-light">
                                    <div class="form-check mb-0">
                                        <input id="allow-corrections" class="form-check-input" type="checkbox" wire:model.live="form.allow_correction_after_close">
                                        <label class="form-check-label small fw-semibold" for="allow-corrections">
                                            Izinkan perbaikan berkas setelah pendaftaran reguler ditutup
                                        </label>
                                    </div>
                                    <div class="form-text ms-4 mt-1" style="font-size: 0.72rem;">Mengizinkan peserta memperbaiki biodata/berkas yang diminta operator meskipun jadwal reguler berakhir.</div>

                                    @if($form['allow_correction_after_close'])
                                        <div class="mt-3 pt-2 border-top">
                                            <label for="correction-closes" class="form-label small fw-semibold">
                                                <i class="bi bi-alarm text-warning-emphasis me-1"></i>Batas Akhir Perbaikan (WIB) *
                                            </label>
                                            <input id="correction-closes" class="form-control form-control-sm" type="datetime-local" wire:model="form.correction_closes_at" required>
                                            <x-field-error name="correction_closes_at" />
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="p-3 rounded-3 border bg-light">
                                    <div class="form-check mb-0">
                                        <input id="public-period" class="form-check-input" type="checkbox" wire:model="form.is_public" @disabled($form['state'] !== PeriodState::Published->value)>
                                        <label class="form-check-label small fw-semibold" for="public-period">
                                            Tampilkan sebagai periode utama di beranda publik portal
                                        </label>
                                    </div>
                                    <div class="form-text ms-4 mt-1" style="font-size: 0.72rem;">Hanya 1 periode publik yang aktif pada halaman utama portal. Periode harus berstatus "Dipublikasikan".</div>
                                    <x-field-error name="is_public" />
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-purple px-4 d-inline-flex align-items-center justify-content-center" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save">
                                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                                    {{ $editingId ? 'Simpan Perubahan' : 'Buat Periode Baru' }}
                                </span>
                                <span wire:loading wire:target="save">
                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                    Menyimpan…
                                </span>
                            </button>
                            @if($editingId)
                                <button type="button" wire:click="cancel" class="btn btn-light border px-3">
                                    Batal Edit
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- Kolom Daftar Periode & Gelombang -->
        <div class="col-xl-6">
            <section class="workspace-card h-100">
                <div class="panel-heading border-bottom pb-3">
                    <h2 class="h5 mb-1 fw-bold text-purple d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-range"></i>
                        <span>Daftar Periode & Gelombang</span>
                    </h2>
                    <p class="text-muted small mb-0">Kelola siklus hidup penerimaan, pantau jadwal buka-tutup, dan kontrol pembukaan manual.</p>
                </div>

                <div class="panel-body pt-3">
                    @forelse($periods as $period)
                        <div class="card border rounded-3 mb-3 shadow-sm overflow-hidden" wire:key="period-{{ $period->id }}" style="border-color: #e2e8f0;">
                            <div class="card-body p-3">
                                <!-- Baris Atas: Judul, Kode & Status Siklus -->
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <h3 class="h6 fw-bold mb-0 text-dark">{{ $period->name }}</h3>
                                            @if($period->is_public)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;" title="Ditampilkan di portal publik">
                                                    <i class="bi bi-globe me-1"></i>Publik
                                                </span>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap text-muted small">
                                            <span>Kode: <code>{{ $period->code }}</code></span>
                                            <span>·</span>
                                            <span><i class="bi bi-mortarboard text-purple me-1"></i>{{ $period->academic_year }}</span>
                                            <span>·</span>
                                            <span>
                                                <i class="bi bi-layers text-purple me-1"></i>
                                                {{ \Illuminate\Support\Str::startsWith(strtolower(trim($period->wave)), 'gelombang') ? $period->wave : 'Gelombang ' . $period->wave }}
                                            </span>
                                        </div>
                                    </div>

                                    @php
                                        $stateBadgeClass = match($period->state) {
                                            \App\Enums\PeriodState::Published => 'bg-purple-subtle text-purple border-purple-subtle',
                                            \App\Enums\PeriodState::Draft => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                            \App\Enums\PeriodState::Archived => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                        };
                                    @endphp
                                    <span class="badge {{ $stateBadgeClass }} border px-2 py-1 flex-shrink-0" style="font-size: 0.75rem;">
                                        {{ $period->state->label() }}
                                    </span>
                                </div>

                                <!-- Baris Status Ketersediaan Pendaftaran (Availability) -->
                                <div class="p-2 mb-3 rounded-2 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <span class="small text-muted fw-semibold d-flex align-items-center gap-1">
                                        <i class="bi bi-shield-check text-purple"></i> Status Pendaftaran:
                                    </span>
                                    @php
                                        $isAccepting = $period->acceptsApplications();
                                        $isManualClosed = $period->manual_closed_at !== null;
                                        $isPastClose = now()->greaterThanOrEqualTo($period->closes_at);
                                        $isBeforeOpen = now()->lessThan($period->opens_at);
                                    @endphp

                                    @if($period->state !== \App\Enums\PeriodState::Published)
                                        <span class="badge bg-secondary text-white px-2 py-1">
                                            <i class="bi bi-pause-circle me-1"></i>{{ $period->state->label() }}
                                        </span>
                                    @elseif($isManualClosed)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-lock-fill me-1"></i>Ditutup Manual
                                        </span>
                                    @elseif($isPastClose)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i>Pendaftaran Ditutup
                                        </span>
                                    @elseif($isAccepting)
                                        <span class="badge bg-success text-white px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i>Pendaftaran Dibuka
                                        </span>
                                    @elseif($isBeforeOpen)
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-clock-fill me-1"></i>Menunggu Jadwal Buka
                                        </span>
                                    @else
                                        <span class="badge bg-secondary text-white px-2 py-1">
                                            {{ $period->availabilityLabel() }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Baris Informasi Waktu Buka & Tutup -->
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <div class="p-2 border rounded-2 bg-white h-100">
                                            <span class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">WAKTU BUKA (WIB)</span>
                                            <div class="fw-semibold text-dark d-flex align-items-center gap-1 mt-1" style="font-size: 0.8125rem;">
                                                <i class="bi bi-calendar-check text-success"></i>
                                                <span>{{ $period->opens_at->timezone('Asia/Jakarta')->translatedFormat('d/m/Y H:i') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="p-2 border rounded-2 bg-white h-100">
                                            <span class="text-muted d-block fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.5px;">WAKTU TUTUP (WIB)</span>
                                            <div class="fw-semibold text-dark d-flex align-items-center gap-1 mt-1" style="font-size: 0.8125rem;">
                                                <i class="bi bi-calendar-x text-danger"></i>
                                                <span>{{ $period->closes_at->timezone('Asia/Jakarta')->translatedFormat('d/m/Y H:i') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    @if($period->allow_correction_after_close && $period->correction_closes_at)
                                        <div class="col-12">
                                            <div class="p-2 rounded-2 bg-warning-subtle border border-warning-subtle text-dark d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                                                <i class="bi bi-alarm text-warning-emphasis fs-6"></i>
                                                <div>
                                                    Batas Perbaikan Berkas: <strong>{{ $period->correction_closes_at->timezone('Asia/Jakarta')->translatedFormat('d/m/Y H:i') }} WIB</strong>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Baris Aksi -->
                                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 pt-2 border-top">
                                    <button type="button" class="btn btn-sm btn-outline-purple" wire:click="manageDocuments({{ $period->id }})"><i class="bi bi-folder2-open me-1" aria-hidden="true"></i>Berkas ({{ $period->period_documents_count }})</button>
                                        <button type="button" class="btn btn-sm btn-outline-purple d-inline-flex align-items-center gap-1 px-3" wire:click="edit({{ $period->id }})" title="Ubah konfigurasi periode">
                                            <i class="bi bi-pencil-square"></i>
                                            <span>Ubah</span>
                                        </button>
                                    @if($period->state === \App\Enums\PeriodState::Archived)
                                        <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 px-3" wire:click="reopen({{ $period->id }})" wire:loading.attr="disabled" title="Siapkan periode arsip untuk dibuka kembali">
                                            <i class="bi bi-unlock-fill"></i>
                                            <span>Buka kembali</span>
                                        </button>
                                    @endif

                                    @if($period->state === \App\Enums\PeriodState::Published)
                                        @if($period->manual_closed_at)
                                            <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1 px-3" 
                                                wire:click="toggleClosed({{ $period->id }}, {{ $period->lock_version }})" 
                                                wire:confirm="Buka kembali periode ini sesuai jadwal yang tersimpan?" 
                                                wire:loading.attr="disabled"
                                                title="Buka kembali penerimaan pendaftaran">
                                                <i class="bi bi-unlock-fill"></i>
                                                <span>Buka kembali</span>
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-warning d-inline-flex align-items-center gap-1 px-3" 
                                                style="border-color: #f59e0b; color: #b45309;"
                                                wire:click="toggleClosed({{ $period->id }}, {{ $period->lock_version }})" 
                                                wire:confirm="Tutup penerimaan klaim dan kirim pertama sekarang?" 
                                                wire:loading.attr="disabled"
                                                title="Tutup penerimaan secara manual">
                                                <i class="bi bi-lock-fill"></i>
                                                <span>Tutup manual</span>
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state compact py-4 text-center">
                            <i class="bi bi-calendar-range text-muted fs-1 mb-2 d-block" aria-hidden="true"></i>
                            <h3 class="h6 fw-bold">Belum ada periode</h3>
                            <p class="text-muted small mb-0">Buat periode pertama melalui formulir di sisi kiri.</p>
                        </div>
                    @endforelse

                    <div class="mt-3">
                        {{ $periods->links() }}
                    </div>
                </div>
            </section>
        </div>
    </div>
    @if($documentPeriodId !== null)
        <div class="mt-4 d-flex justify-content-end"><button type="button" class="btn btn-light border" wire:click="closeDocuments">Tutup Panel Berkas Periode</button></div>
        <livewire:documents.period-document-manager :periodId="$documentPeriodId" :key="'period-documents-'.$documentPeriodId" />
    @endif
</div>
