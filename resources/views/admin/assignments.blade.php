<x-layouts::app title="Pembagian Operator">
    <div class="notice-box info">
        <i class="bi bi-diagram-3" aria-hidden="true"></i>
        <div>
            <strong>Pembagian berdasarkan seluruh peserta SK</strong>
            <p class="mb-0">Semua akun Operator aktif tersedia untuk seluruh periode tanpa pendaftaran terpisah. Gunakan halaman ini hanya untuk membagi peserta secara merata kepada operator yang dipilih. Jika operator bertambah atau berubah, pembagian disesuaikan secara proporsional saat ditinjau.</p>
        </div>
    </div>

    <section class="workspace-card">
        <div class="panel-heading">
            <h2>Pilih periode dan operator aktif</h2>
            <p>Pratinjau memperlihatkan beban awal, penyesuaian, dan beban akhir.</p>
        </div>
        <div class="panel-body">
            @if($periods->isEmpty())
                <div class="alert alert-warning p-4 rounded-3 text-center mb-0" role="alert">
                    <i class="bi bi-calendar-x fs-1 text-warning d-block mb-3"></i>
                    <h3 class="h5 fw-bold text-dark">Belum Ada Periode Pendaftaran</h3>
                    <p class="text-muted small mb-4">
                        Pembagian tugas operator dilakukan per periode pendaftaran. Silakan buat dan aktifkan periode terlebih dahulu.
                    </p>
                    <a href="{{ route('admin.periods') }}" class="btn btn-purple btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i>Kelola Periode & Gelombang
                    </a>
                </div>
            @else
                <form method="get">
                    <div class="mb-4">
                        <label for="assignment-period" class="form-label">Periode *</label>
                        <select id="assignment-period" name="period_id" class="form-select" required>
                            <option value="">Pilih periode</option>
                            @foreach($periods as $option)
                                <option value="{{ $option->id }}" @selected($period?->id===$option->id)>{{ $option->name }} · {{ $option->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-grid">
                        @forelse($operators as $operator)
                            <label class="operator-card">
                                <input type="checkbox" class="form-check-input" name="operators[]" value="{{ $operator->id }}" @checked(in_array($operator->id, $selected, true))>
                                <span class="user-avatar">{{ $operator->initials() }}</span>
                                <h3>{{ $operator->name }}</h3>
                                <small>{{ $operator->email ?? $operator->login_id }}</small>
                                <span class="status-pill diterima_lengkap">Aktif</span>
                                <strong>{{ $loads[$operator->id] ?? 0 }} peserta</strong>
                            </label>
                        @empty
                            <p class="text-muted">Belum ada operator aktif. Tambahkan akun operator melalui Kelola Pengguna.</p>
                        @endforelse
                    </div>
                    <button class="btn btn-outline-purple mt-4">Tinjau Pembagian</button>
                </form>

            @if($token && $period)
                <hr class="my-4">
                <h3 class="h5">
                    @if($unassigned > 0)
                        {{ $unassigned }} peserta belum ditugaskan
                    @else
                        Distribusi seluruh peserta periode
                    @endif
                    <span class="text-muted fs-6 fw-normal ms-1">(Total: {{ $totalParticipants }} peserta aktif)</span>
                </h3>
                <div class="table-responsive">
                    <table class="table portal-table">
                        <thead>
                            <tr>
                                <th>Operator</th>
                                <th>Beban awal</th>
                                <th>Tambahan</th>
                                <th>Beban akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selected as $index=>$id)
                                @php($operator=$operators->firstWhere('id',$id))
                                @php($before = $loads[$id] ?? 0)
                                @php($after = $targetLoads[$id] ?? 0)
                                @php($diff = $after - $before)
                                <tr>
                                    <td>{{ $operator?->name ?? 'Operator tidak aktif' }}</td>
                                    <td>{{ $before }}</td>
                                    <td>
                                        @if($diff > 0)
                                            <span class="text-success fw-bold">+{{ $diff }}</span>
                                        @elseif($diff < 0)
                                            <span class="text-danger fw-bold">{{ $diff }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $after }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="post" action="{{ route('admin.assignments.store') }}">
                    @csrf
                    <input type="hidden" name="preview_token" value="{{ $token }}">
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="assignment-confirm" type="checkbox" required>
                        <label class="form-check-label" for="assignment-confirm">Saya telah memeriksa operator dan distribusi penugasan.</label>
                    </div>
                    <button class="btn btn-gold">Jalankan Pembagian Peserta</button>
                </form>
            @endif

                <a href="{{ route('admin.assignments.bulk', ['period_id'=>$period?->id]) }}" class="btn btn-outline-purple mt-4">Transfer Massal & Penyeimbangan Beban</a>
            @endif
        </div>
    </section>
</x-layouts::app>
