<?php

use App\Actions\Admissions\SaveAdmissionPeriod;
use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Periode & Gelombang')] class extends Component
{
    use WithPagination;

    #[Locked] public ?int $editingId = null;
    #[Locked] public int $version = 0;
    public array $form = ['code'=>'', 'name'=>'', 'academic_year'=>'', 'wave'=>'', 'state'=>'draft', 'opens_at'=>'', 'closes_at'=>'', 'allow_correction_after_close'=>false, 'correction_closes_at'=>'', 'is_public'=>false];
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

    public function cancel(): void
    {
        $this->reset('editingId', 'version', 'form');
        $this->resetValidation();
    }

    public function save(SaveAdmissionPeriod $save): void
    {
        $save->handle(auth()->user(), $this->editingId, $this->version, $this->form);
        $this->cancel();
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
        return ['periods'=>AdmissionPeriod::query()->orderByDesc('id')->paginate(10)];
    }
};
?>

<div>
    @if($message)<div class="alert alert-success" role="status">{{ $message }}</div>@endif
    <div class="row g-4"><div class="col-xl-7"><section class="workspace-card"><div class="panel-heading"><h2>{{ $editingId ? 'Ubah periode' : 'Buat periode penerimaan' }}</h2><p>Isi jadwal dalam WIB. Perubahan data disimpan pada server.</p></div><div class="panel-body">
    <form wire:submit="save"><div class="row g-3">
    @foreach(['code'=>'Kode periode','name'=>'Nama periode','academic_year'=>'Tahun akademik','wave'=>'Gelombang'] as $key=>$label)<div class="col-md-6"><label for="period-{{ $key }}" class="form-label">{{ $label }} *</label><input id="period-{{ $key }}" class="form-control" wire:model="form.{{ $key }}" required maxlength="{{ $key==='code' ? 40 : ($key==='academic_year' ? 20 : ($key==='wave' ? 50 : 255)) }}" @if($key==='academic_year') placeholder="2026/2027" @endif><x-field-error :name="$key" /></div>@endforeach
    <div class="col-md-6"><label for="period-opens" class="form-label">Waktu buka (WIB) *</label><input id="period-opens" type="datetime-local" class="form-control" wire:model="form.opens_at" required><x-field-error name="opens_at" /></div>
    <div class="col-md-6"><label for="period-closes" class="form-label">Waktu tutup (WIB) *</label><input id="period-closes" type="datetime-local" class="form-control" wire:model="form.closes_at" required><x-field-error name="closes_at" /></div>
    <div class="col-12"><label for="period-state" class="form-label">Status</label><select id="period-state" class="form-select" wire:model="form.state">@foreach(PeriodState::cases() as $state)<option value="{{ $state->value }}">{{ $state->label() }}</option>@endforeach</select></div>
    <div class="col-12"><div class="form-check"><input id="allow-corrections" class="form-check-input" type="checkbox" wire:model.live="form.allow_correction_after_close"><label class="form-check-label" for="allow-corrections">Izinkan perbaikan setelah penutupan</label></div></div>
    @if($form['allow_correction_after_close'])<div class="col-12"><label for="correction-closes" class="form-label">Batas perbaikan (WIB) *</label><input id="correction-closes" class="form-control" type="datetime-local" wire:model="form.correction_closes_at" required><x-field-error name="correction_closes_at" /></div>@endif
    <div class="col-12"><div class="form-check"><input id="public-period" class="form-check-input" type="checkbox" wire:model="form.is_public"><label class="form-check-label" for="public-period">Tampilkan sebagai periode pada portal publik</label></div><div class="form-text">Hanya satu periode publik ditampilkan. Periode harus berstatus Dipublikasikan.</div><x-field-error name="is_public" /></div>
    </div><div class="d-flex gap-2 mt-4"><button class="btn btn-purple" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Simpan Periode</span><span wire:loading wire:target="save">Menyimpan…</span></button>@if($editingId)<button type="button" wire:click="cancel" class="btn btn-light border">Batal</button>@endif</div></form></div></section></div>
    <div class="col-xl-5"><section class="workspace-card"><div class="panel-heading"><h2>Periode & Gelombang</h2><p>Rentang waktu dan status penerimaan.</p></div><div class="panel-body">
    @forelse($periods as $period)<article class="border-bottom pb-3 mb-3" wire:key="period-{{ $period->id }}"><div class="d-flex justify-content-between gap-2"><strong>{{ $period->name }}</strong>@if($period->is_public)<span class="badge text-bg-success">Publik</span>@endif</div><p class="small text-muted my-2">{{ $period->code }} · {{ $period->academic_year }} · Gelombang {{ $period->wave }}</p><span class="badge text-bg-light border">{{ $period->availabilityLabel() }}</span><p class="small mt-2">{{ $period->opens_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} – {{ $period->closes_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>@if($period->state !== PeriodState::Archived)<button class="btn btn-outline-purple btn-sm" wire:click="edit({{ $period->id }})">Ubah</button>@endif @if($period->state === PeriodState::Published)<button class="btn btn-light border btn-sm" wire:click="toggleClosed({{ $period->id }}, {{ $period->lock_version }})" wire:confirm="{{ $period->manual_closed_at ? 'Buka kembali periode ini sesuai jadwal yang tersimpan?' : 'Tutup penerimaan klaim dan kirim pertama sekarang?' }}" wire:loading.attr="disabled">{{ $period->manual_closed_at ? 'Buka kembali' : 'Tutup manual' }}</button>@endif</article>@empty<div class="empty-state compact"><i class="bi bi-calendar-range" aria-hidden="true"></i><h3>Belum ada periode</h3><p>Buat periode pertama melalui formulir.</p></div>@endforelse
    {{ $periods->links() }}
    </div></section></div></div>
</div>
