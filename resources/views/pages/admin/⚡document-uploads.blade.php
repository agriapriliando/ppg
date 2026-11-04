<?php

use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Daftar Unggahan')] class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'period_id', except: '')]
    public string $periodId = '';

    #[Url(except: '')]
    public string $extension = '';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodId(): void
    {
        $this->resetPage();
    }

    public function updatedExtension(): void
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

    public function resetFilters(): void
    {
        $this->reset('search', 'periodId', 'extension');
        $this->resetPage();
    }

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && in_array($user->role, [UserRole::Operator, UserRole::Administrator], true), 403);
    }

    /** @return LengthAwarePaginator<ApplicantDocument> */
    public function getDocumentsProperty(): LengthAwarePaginator
    {
        $query = ApplicantDocument::query()
            ->where('status', ApplicantDocument::STATUS_AVAILABLE)
            ->with(['period', 'owner', 'uploader', 'workspace.biodata', 'workspace.application.participant']);

        if (! empty($this->periodId)) {
            $query->where('admission_period_id', (int) $this->periodId);
        }

        if (! empty($this->extension)) {
            $query->where('extension', strtolower($this->extension));
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($sub) use ($search): void {
                $sub->where('display_name', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('workspace.biodata', fn ($b) => $b->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('workspace.application', fn ($a) => $a->where('application_number', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($this->perPage);
    }

    /** @return Collection<int, AdmissionPeriod> */
    public function getPeriodsProperty(): Collection
    {
        return AdmissionPeriod::query()->orderByDesc('id')->get();
    }
};
?>

<div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold text-purple"><i class="bi bi-files me-2" aria-hidden="true"></i>Daftar Unggahan</h1>
            <p class="text-muted small mb-0">Daftar seluruh berkas yang telah diunggah oleh pendaftar lintas periode.</p>
        </div>
    </div>

    @if($this->periods->isEmpty())
        <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2 p-3 mb-4 rounded-3" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                <span class="small">Belum ada periode pendaftaran aktif di sistem. Berkas unggahan pendaftar akan muncul setelah periode dibuka dan peserta mengunggah dokumen.</span>
            </div>
            @if(auth()->user()?->isAdministrator())
                <a href="{{ route('admin.periods') }}" class="btn btn-sm btn-purple">Kelola Periode</a>
            @endif
        </div>
    @endif

    <section class="workspace-card mb-4">
        <div class="panel-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="filter-period" class="form-label small fw-semibold">Periode</label>
                    <select id="filter-period" wire:model.live="periodId" class="form-select form-select-sm">
                        <option value="">Semua Periode</option>
                        @foreach ($this->periods as $p)
                            <option value="{{ $p->id }}">
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="filter-search" class="form-label small fw-semibold">Pencarian</label>
                    <input type="text" id="filter-search" wire:model.live.debounce.300ms="search" class="form-control form-control-sm"
                        placeholder="Nama pendaftar / nama berkas / no pendaftaran">
                </div>
                <div class="col-md-2">
                    <label for="filter-ext" class="form-label small fw-semibold">Ekstensi</label>
                    <select id="filter-ext" wire:model.live="extension" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach (['pdf', 'jpg', 'jpeg', 'png', 'docx', 'doc', 'xlsx', 'xls'] as $ext)
                            <option value="{{ $ext }}">{{ strtoupper($ext) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="workspace-card">
        <div class="panel-heading">
            <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                <div>
                    <h2 class="h5 mb-1">Seluruh Dokumen Terunggah</h2>
                    <p class="text-muted small mb-0">Total ditemukan: <strong>{{ $this->documents->total() }}</strong> berkas</p>
                </div>
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
            </div>
        </div>
        <div class="panel-body p-0">
            @if ($this->documents->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted mb-2 d-block"></i>
                    <h3 class="h6 text-muted">Tidak ada berkas yang cocok dengan filter</h3>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Pemilik Workspace</th>
                                <th>Nama Berkas</th>
                                <th>Periode</th>
                                <th>Ukuran</th>
                                <th>Waktu</th>
                                <th>Pengunggah</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->documents as $doc)
                                <tr wire:key="doc-{{ $doc->id }}">
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark">
                                            {{ $doc->workspace?->biodata?->full_name ?? ($doc->owner?->name ?? 'Pendaftar') }}
                                        </div>
                                        <small class="text-muted">
                                            @if ($doc->workspace?->application)
                                                {{ $doc->workspace->application->application_number }}
                                            @else
                                                <span class="badge text-bg-warning">Pra-Klaim</span>
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $doc->iconClass() }} fs-5"></i>
                                            <div>
                                                <span class="fw-semibold text-dark">{{ $doc->display_name }}</span><span class="text-muted">.{{ $doc->extension }}</span>
                                                <small class="text-muted d-block" style="font-size: 0.72rem;">Asli: {{ $doc->original_name }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small">{{ $doc->period?->name }}</span>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold">{{ $doc->formattedSize() }}</span>
                                    </td>
                                    <td>
                                        <div class="small">{{ $doc->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $doc->created_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</small>
                                    </td>
                                    <td>
                                        @if ($doc->uploaded_by === $doc->owner_user_id)
                                            <span class="badge text-bg-success">Pendaftar</span>
                                        @else
                                            <span class="small fw-semibold text-secondary">
                                                {{ $doc->uploader?->name ?? 'Petugas' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="doc-actions-group" role="group" aria-label="Aksi berkas {{ $doc->file_name }}">
                                            <a href="{{ route('admin.workspaces.documents.show', $doc->workspace_id) }}"
                                                class="doc-btn doc-btn-open" title="Buka Workspace Berkas">
                                                <i class="bi bi-box-arrow-up-right"></i><span>Buka</span>
                                            </a>
                                            @if ($doc->isPreviewable())
                                                <a href="{{ route('admin.workspaces.documents.preview', ['workspace' => $doc->workspace_id, 'document' => $doc->id]) }}"
                                                    target="_blank" class="doc-btn doc-btn-preview" title="Lihat di tab baru">
                                                    <i class="bi bi-eye"></i><span>Lihat</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('admin.workspaces.documents.download', ['workspace' => $doc->workspace_id, 'document' => $doc->id]) }}"
                                                class="doc-btn doc-btn-download" title="Unduh berkas">
                                                <i class="bi bi-download"></i><span>Unduh</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-end p-3 border-top">
                    <div>
                        {{ $this->documents->links() }}
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
