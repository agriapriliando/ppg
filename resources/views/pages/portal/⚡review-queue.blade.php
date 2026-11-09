<?php

use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Antrean Verifikasi')] class extends Component
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

    #[Url(as: 'origin_city', except: '')]
    public string $originCityCode = '';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

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

    public function updatedOriginCityCode(): void
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

    /** @return LengthAwarePaginator<Application> */
    public function getApplicationsProperty(): LengthAwarePaginator
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $search = trim($this->search);
        $status = $this->status;
        $periodId = $this->periodId;

        return Application::query()
            ->whereHas('participant', fn ($participants) => $participants->originCity($this->originCityCode)
                ->when($this->operatorId === 'unassigned', fn ($q) => $q->whereNull('assigned_operator_id'))
                ->when($this->operatorId !== '' && $this->operatorId !== 'unassigned', fn ($q) => $q->where('assigned_operator_id', (int) $this->operatorId)))
            ->with(['participant.assignedOperator', 'period'])
            ->when(! empty($status), fn ($q) => $q->where('status', $status))
            ->when(! empty($periodId), fn ($q) => $q->where('admission_period_id', (int) $periodId))
            ->when($search !== '', fn ($q) => $q->whereHas('participant', fn ($participants) => $participants->where('name_on_sk', 'like', '%'.$search.'%')->orWhere('official_participant_id', 'like', '%'.$search.'%')))
            ->orderBy('submitted_at')
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
        return User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->orderBy('name')->orderBy('id')->get();
    }

    /** @return Collection<int, SkParticipant> */
    public function getOriginCitiesProperty(): Collection
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        return SkParticipant::query()->whereHas('application')
            ->whereNotNull('origin_city_code')->where('origin_city_code', '!=', '')
            ->select(['origin_city_code', 'origin_city_name', 'origin_province_name'])
            ->distinct()->orderBy('origin_city_name')->orderBy('origin_city_code')->get()->unique('origin_city_code')->values();
    }
};
?>

<div x-data="{ showGuide: false }">
    <section class="workspace-card">
        <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom">
            <div>
                <h2 class="h5 mb-1 fw-bold text-purple">Daftar Pendaftaran & Berkas Masuk</h2>
                <p class="text-muted small mb-0">Semua operator aktif dapat melihat seluruh pendaftaran, termasuk draf. Gunakan filter untuk memilih status atau operator.</p>
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
                        <span class="small">Belum ada periode pendaftaran aktif. Antrean verifikasi berkas akan muncul setelah periode dibuka dan calon mahasiswa mengirimkan formulir pendaftaran.</span>
                    </div>
                    @if(auth()->user()?->isAdministrator())
                        <a href="{{ route('admin.periods') }}" class="btn btn-sm btn-purple">Kelola Periode</a>
                    @endif
                </div>
            @endif

            <div class="row g-3">
                <div class="col-sm-6 col-xl">
                    <label for="queue-search" class="form-label">Nama atau Peg ID</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input id="queue-search" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari nama atau Peg ID…" maxlength="100">
                    </div>
                </div>
                <div class="col-sm-6 col-xl">
                    <label for="queue-operator" class="form-label">Operator</label>
                    <select id="queue-operator" wire:model.live="operatorId" class="form-select">
                        <option value="">Semua operator</option>
                        <option value="unassigned">Belum ditugaskan</option>
                        @foreach($this->operators as $operator)
                            <option value="{{ $operator->id }}" wire:key="queue-operator-{{ $operator->id }}">{{ $operator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-xl">
                    <label for="queue-period" class="form-label">Periode</label>
                    <select id="queue-period" wire:model.live="periodId" class="form-select">
                        <option value="">Semua periode</option>
                        @foreach($this->periods as $period)
                            <option value="{{ $period->id }}">{{ $period->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-xl">
                    <label for="queue-origin" class="form-label">Asal Wilayah</label>
                    <select id="queue-origin" wire:model.live="originCityCode" class="form-select">
                        <option value="">Semua kota/kabupaten</option>
                        <option value="unfilled">Asal wilayah belum diisi</option>
                        @foreach($this->originCities as $city)
                            <option value="{{ $city->origin_city_code }}" wire:key="queue-origin-{{ $city->origin_city_code }}">{{ $city->origin_city_name ?: $city->origin_city_code }}{{ $city->origin_province_name ? ' - '.$city->origin_province_name : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-xl">
                    <label for="queue-status" class="form-label">Status pendaftaran</label>
                    <select id="queue-status" wire:model.live="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach(ApplicationStatus::cases() as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
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
                    Total: <strong>{{ $this->applications->total() }}</strong> pendaftaran
                </div>
            </div>
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
                    @forelse($this->applications as $application)
                        <tr wire:key="app-{{ $application->id }}">
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
                                    <a class="btn btn-sm btn-outline-purple" href="{{ route('reviews.show', $application) }}">{{ auth()->user()->can('review', $application) ? 'Periksa' : 'Lihat Detail' }}</a>
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

        <div class="d-flex flex-wrap align-items-center justify-content-end p-3 border-top">
            <div>
                {{ $this->applications->links() }}
            </div>
        </div>
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
                            <p><strong>Diterima Lengkap (Hijau):</strong> Seluruh berkas pendaftaran telah resmi disetujui.</p>
                        </div>
                        <div class="guide-status-item">
                            <span class="status-pill draf">Draf</span>
                            <p><strong>Draf / Belum Mengirim (Abu-abu):</strong> Calon mahasiswa masih dalam proses pengisian formulir dan belum menekan tombol kirim pendaftaran.</p>
                        </div>
                    </div>
                </div>

                <hr class="my-4" style="border-color: #efe8f6;">

                <div>
                    <h3 class="h6 fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-printer text-purple" aria-hidden="true"></i> Cetak Berkas Pendaftaran
                    </h3>
                    <p class="text-muted small mb-0">Klik tombol <strong>Cetak</strong> untuk mencetak formulir biodata lengkap dan berkas unggahan pendaftar dalam format siap cetak/simpan PDF untuk arsip resmi kampus.</p>
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
