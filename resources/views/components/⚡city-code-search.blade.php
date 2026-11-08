<?php

use App\Models\AdmissionPeriod;
use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public function with(): array
    {
        Gate::authorize('viewAny', AdmissionPeriod::class);
        $datasetId = ReferenceDataset::query()->where('state', 'active')->value('id');
        $search = trim(mb_substr($this->search, 0, 100));
        $results = collect();
        if ($datasetId !== null && mb_strlen($search) >= 2) {
            $query = ReferenceRegion::from('reference_regions as c')
                ->join('reference_regions as p', function ($join) use ($datasetId): void {
                    $join->on('p.code', '=', 'c.parent_code')->where('p.dataset_id', $datasetId)->where('p.level', 'province');
                })
                ->where('c.dataset_id', $datasetId)->where('c.level', 'city');
            foreach (array_slice(preg_split('/\s+/u', $search), 0, 5) as $term) {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
                $query->where(fn ($q) => $q->whereRaw("LOWER(c.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(p.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("c.code LIKE ? ESCAPE '!'", [$pattern]));
            }
            $results = $query->orderBy('c.name')->orderBy('c.code')->limit(21)
                ->get(['c.id', 'c.code', 'c.name', 'p.name as province_name']);
        }

        return ['hasDataset' => $datasetId !== null, 'searched' => mb_strlen($search) >= 2, 'hasMore' => $results->count() > 20, 'results' => $results->take(20)];
    }
};
?>

<section class="workspace-card mt-4" aria-label="Pencarian kode kota atau kabupaten asal">
    <div class="panel-heading">
        <div><h2 class="h5 mb-1"><i class="bi bi-geo-alt text-purple me-2" aria-hidden="true"></i>Cari Kode Kota/Kabupaten Asal</h2><p class="mb-0">Temukan kode untuk kolom <code>kode_kota_kabupaten_asal</code> pada Excel impor.</p></div>
    </div>
    <div class="panel-body">
        <label for="origin-city-search" class="form-label fw-semibold">Nama kota/kabupaten, provinsi, atau kode</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input id="origin-city-search" type="search" class="form-control" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="Contoh: Palangka Raya, Kalimantan Tengah, atau kode wilayah" autocomplete="off" aria-describedby="origin-city-help">
            @if($search !== '')<button type="button" class="btn btn-outline-purple" wire:click="$set('search', '')" aria-label="Bersihkan pencarian">Reset</button>@endif
        </div>
        <p id="origin-city-help" class="form-text mb-3">Ketik minimal 2 karakter. Salin kode sebagai teks agar angka nol di depan tetap utuh. Hasil mengikuti referensi wilayah aktif.</p>
        <div wire:loading.delay wire:target="search" class="small text-purple mb-2" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Mencari wilayah…</div>
        <div aria-live="polite" wire:loading.class="opacity-50" wire:target="search">
            @if(!$hasDataset)
                <div class="alert alert-warning mb-0">Referensi wilayah aktif belum tersedia. Hubungi administrator untuk memuat dan mengaktifkan referensi wilayah.</div>
            @elseif(!$searched)
                <div class="border rounded-3 bg-light p-3 text-muted small">Mulai dengan mengetik nama kota/kabupaten, provinsi, atau kode yang ingin dicari.</div>
            @elseif($results->isEmpty())
                <div class="border rounded-3 bg-light p-3 text-muted">Wilayah tidak ditemukan. Coba kata kunci lain atau periksa ejaan nama daerah.</div>
            @else
                <p class="small text-muted mb-2">{{ $hasMore ? 'Menampilkan 20 hasil pertama. Tambahkan kata kunci agar lebih spesifik.' : $results->count().' kota/kabupaten ditemukan.' }}</p>
                <div class="d-flex flex-column gap-2">
                    @foreach($results as $region)
                        <div class="border rounded-3 p-3 d-flex flex-column flex-sm-row justify-content-between gap-3" wire:key="origin-city-{{ $region->id }}"
                            x-data="{ copied: false, copyError: false, async copyCode() {
                                this.copied = false; this.copyError = false;
                                try {
                                    if (navigator.clipboard && window.isSecureContext) {
                                        await navigator.clipboard.writeText(this.$refs.code.value);
                                    } else {
                                        this.$refs.code.focus(); this.$refs.code.select();
                                        if (!document.execCommand('copy')) throw new Error('copy');
                                    }
                                    this.copied = true;
                                    setTimeout(() => { this.copied = false; }, 2000);
                                } catch (error) {
                                    this.$refs.code.focus(); this.$refs.code.select(); this.copyError = true;
                                }
                            } }">
                            <div class="text-break"><strong>{{ $region->name }}</strong><span class="d-block small text-muted">{{ $region->getAttribute('province_name') }}</span></div>
                            <div class="flex-shrink-0">
                                <div class="input-group input-group-sm" style="width: 14rem; max-width: 100%;">
                                    <input x-ref="code" type="text" class="form-control font-monospace fw-bold" value="{{ $region->code }}" readonly aria-label="Kode {{ $region->name }}" @click="$el.select()">
                                    <button type="button" class="btn btn-outline-purple" @click="copyCode()" aria-label="Salin kode {{ $region->code }}"><i class="bi" :class="copied ? 'bi-check2' : 'bi-copy'" aria-hidden="true"></i><span class="ms-1" x-text="copied ? 'Tersalin' : 'Salin'">Salin</span></button>
                                </div>
                                <span class="small text-success" role="status" x-cloak x-show="copied">Kode berhasil disalin.</span>
                                <div class="small text-muted" role="status" x-cloak x-show="copyError">Kode sudah dipilih. Tekan Ctrl+C atau salin manual.</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
