<section id="statistik-peserta-sk" class="sk-statistics py-5" data-sk-statistics data-endpoint="{{ route('public.sk-statistics') }}" aria-labelledby="sk-statistics-title">
    <div class="container">
        <script type="application/json" data-statistics-payload>{!! Illuminate\Support\Js::encode($skStatistics) !!}</script>
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
            <div class="sk-statistics-intro">
                <span class="section-badge">PESERTA SK RESMI</span>
                <h2 id="sk-statistics-title" class="section-title mt-3">Sebaran Peserta SK</h2>
                <p class="text-muted mb-2">Berdasarkan asal wilayah pada data SK, bukan domisili pengisian formulir.</p>
                <p class="small mb-0">Sebaran wilayah: <strong data-statistics-scope>{{ $skStatistics['scope']['label'] }}</strong></p>
            </div>
            <div class="sk-statistics-controls" data-statistics-controls hidden>
                <label for="sk-statistics-period" class="form-label fw-semibold">Periode untuk sebaran wilayah</label>
                <div class="d-flex gap-2 align-items-start">
                    <select id="sk-statistics-period" class="form-select" data-statistics-period>
                        <option value="">Semua periode dipublikasikan</option>
                        @foreach($skStatistics['periods'] as $item)
                            <option value="{{ $item['id'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-outline-purple text-nowrap" data-statistics-reset>Reset statistik</button>
                </div>
            </div>
        </div>
        <p class="small text-muted mb-3">Snapshot data: <time data-statistics-time datetime="{{ $skStatistics['generated_at'] }}">{{ Carbon\CarbonImmutable::parse($skStatistics['generated_at'])->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</time>. Hanya kepesertaan aktif pada periode berstatus Dipublikasikan.</p>
        <div class="alert alert-info" role="status" aria-live="polite" data-statistics-status hidden></div>
        <button type="button" class="btn btn-outline-purple mb-3" data-statistics-retry hidden>Coba lagi</button>
        <div class="row g-3 mb-4">
            @foreach(['total' => 'Total kepesertaan SK aktif', 'provinces' => 'Provinsi terwakili', 'cities' => 'Kabupaten/kota terwakili'] as $key => $label)
                <div class="col-sm-4">
                    <div class="sk-statistics-metric h-100">
                        <strong data-statistics-metric="{{ $key }}">{{ number_format($skStatistics['summary'][$key], 0, ',', '.') }}</strong>
                        <span>{{ $label }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="small text-muted" data-statistics-missing @if($skStatistics['summary']['missing_province'] + $skStatistics['summary']['missing_city'] === 0) hidden @endif>
            Asal provinsi belum tersedia: <span data-missing-province>{{ $skStatistics['summary']['missing_province'] }}</span>.
            Asal kabupaten/kota belum tersedia: <span data-missing-city>{{ $skStatistics['summary']['missing_city'] }}</span>.
            Peserta tersebut tetap masuk total kepesertaan dan grafik periode.
        </p>
        <p class="alert alert-light border" data-statistics-empty @if($skStatistics['summary']['total'] > 0) hidden @endif>Belum ada data peserta SK yang dapat ditampilkan.</p>
        <div class="row g-4">
            @foreach(['provinces' => 'Jumlah peserta per provinsi', 'cities' => 'Jumlah peserta per kabupaten/kota', 'periods' => 'Perbandingan peserta per periode dipublikasikan'] as $key => $title)
                <div class="{{ $key === 'periods' ? 'col-12' : 'col-lg-6' }}">
                    <section class="workspace-card sk-statistics-card h-100" aria-labelledby="sk-statistics-{{ $key }}-title">
                        <div class="panel-heading">
                            <h3 id="sk-statistics-{{ $key }}-title" class="h5">{{ $title }}</h3>
                            @if($key === 'provinces')
                                <p>Klik provinsi atau gunakan pilihan provinsi untuk melihat kabupaten/kotanya.</p>
                            @elseif($key === 'periods')
                                <p>Seluruh periode dipublikasikan dengan peserta aktif. Klik batang untuk memilih sebaran wilayah; pilihan ditandai emas.</p>
                            @else
                                <p>Semua daerah dengan peserta tersedia melalui gulir, pencarian, dan tabel.</p>
                            @endif
                        </div>
                        <div class="panel-body">
                            @if($key === 'cities')
                                <div class="row g-2 mb-3" data-statistics-controls hidden>
                                    <div class="col-sm-6">
                                        <label for="sk-statistics-province" class="form-label">Provinsi</label>
                                        <select id="sk-statistics-province" class="form-select" data-statistics-province>
                                            <option value="">Semua provinsi</option>
                                            @foreach($skStatistics['provinces'] as $item)
                                                <option value="{{ $item['code'] }}">{{ $item['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="sk-statistics-search" class="form-label">Cari kabupaten/kota</label>
                                        <input id="sk-statistics-search" type="search" class="form-control" data-statistics-search maxlength="100" placeholder="Nama atau kode wilayah">
                                    </div>
                                </div>
                                <p class="small text-muted" data-statistics-city-caption>Menampilkan {{ count($skStatistics['cities']) }} dari {{ count($skStatistics['cities']) }} wilayah.</p>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-statistics-clear-search hidden>Hapus pencarian</button>
                            @endif
                            <div class="sk-statistics-chart" data-statistics-chart="{{ $key }}" aria-label="{{ $title }}" hidden></div>
                            <p class="small text-muted" data-statistics-chart-empty="{{ $key }}" @if(count($skStatistics[$key]) > 0) hidden @endif>{{ $key === 'cities' ? 'Wilayah tidak ditemukan pada pilihan ini.' : 'Belum ada data untuk grafik ini.' }}</p>
                            <details class="sk-statistics-table" data-statistics-table="{{ $key }}" open>
                                <summary class="text-purple fw-semibold mb-2">Lihat tabel data {{ $key === 'provinces' ? 'provinsi' : ($key === 'cities' ? 'kabupaten/kota' : 'periode') }}</summary>
                                <div class="table-responsive sk-statistics-table-scroll" tabindex="0" aria-label="Tabel {{ $title }}">
                                    <table class="table table-sm align-middle mb-0">
                                        <caption class="visually-hidden">{{ $title }}. Jumlah adalah kepesertaan SK aktif.</caption>
                                        <thead><tr><th scope="col">{{ $key === 'periods' ? 'Periode' : 'Wilayah' }}</th>@if($key === 'cities')<th scope="col">Provinsi</th>@endif<th scope="col" class="text-end">Peserta</th></tr></thead>
                                        <tbody>
                                            @foreach($skStatistics[$key] as $item)
                                                <tr><th scope="row">{{ $item['label'] }}</th>@if($key === 'cities')<td>{{ $item['province_label'] }}</td>@endif<td class="text-end">{{ number_format($item['count'], 0, ',', '.') }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
        <p class="text-muted small mt-3 mb-0">Satu orang pada dua periode dihitung sebagai dua kepesertaan. Statistik ini tidak menyatakan peserta telah lolos pemeriksaan atau menyelesaikan pendaftaran. Persentase wilayah dihitung terhadap total cakupan sebelum pencarian.</p>
    </div>
</section>
@pushOnce('scripts', 'public-sk-statistics')
    @vite('resources/js/public-sk-statistics.js')
@endPushOnce
