<x-layouts::app title="Impor Pengguna dari Excel">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div><h2 class="h4 fw-bold text-purple mb-1">Impor Pengguna dari Excel</h2><p class="text-muted mb-0">Tambah akun Administrator dan Operator sekaligus, dengan pemeriksaan sebelum disimpan.</p></div>
        <a href="{{ route('admin.users') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke Pengguna</a>
    </div>
    <x-notice />
    <section class="workspace-card mb-4">
        <div class="panel-heading"><h3 class="h5 mb-0">1. Siapkan dan unggah Excel</h3></div>
        <div class="panel-body">
            <p class="mb-2">Gunakan sheet <strong>Pengguna</strong> pada template. Isi kolom <strong>nama, id_login, email, peran, kata_sandi</strong>. Email boleh kosong; peran kosong otomatis menjadi Operator.</p>
            <p class="small text-muted">Format .xlsx, maksimal 2 MB dan 100 akun. Kata sandi wajib diisi tanpa batas minimal karakter. Gunakan teks biasa tanpa formula.</p>
            <div class="alert alert-light border small">Impor hanya menambah akun baru. ID login dan email yang sudah digunakan akan ditolak. Semua baris harus valid sebelum disimpan. Akun aktif dan dapat langsung login setelah impor.</div>
            <a href="{{ route('admin.users.import.template') }}" class="btn btn-outline-purple mb-3"><i class="bi bi-download me-1" aria-hidden="true"></i>Unduh Template Excel</a>
            <form method="post" action="{{ route('admin.users.import.preview') }}" enctype="multipart/form-data" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <label for="staff-import-file" class="form-label fw-semibold">Berkas Excel pengguna</label>
                <div class="d-flex flex-column flex-md-row gap-2">
                    <input id="staff-import-file" type="file" name="file" class="form-control" accept=".xlsx" required>
                    <button class="btn btn-purple flex-shrink-0" :disabled="busy"><i class="bi bi-search me-1" aria-hidden="true"></i><span x-text="busy ? 'Memeriksa…' : 'Periksa & Pratinjau'">Periksa & Pratinjau</span></button>
                </div>
                <x-field-error name="file" />
            </form>
            <p class="small text-muted mt-3 mb-0">Gunakan kata sandi berbeda untuk setiap akun dan simpan Excel di tempat terbatas. Kata sandi tidak ditampilkan dalam pratinjau.</p>
        </div>
    </section>

    @if($token !== null)
        <section class="workspace-card">
            <div class="panel-heading"><h3 class="h5 mb-0">2. Periksa {{ count($rows) }} akun sebelum menyimpan</h3></div>
            <div class="panel-body">
                <div class="alert alert-{{ $hasErrors ? 'warning' : 'success' }}" role="status">{{ $hasErrors ? 'Ada baris bermasalah. Perbaiki Excel dan unggah ulang. Belum ada akun yang disimpan.' : 'Semua baris valid. Belum ada akun yang disimpan.' }} Pratinjau berlaku selama 15 menit.</div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Baris</th><th>Nama</th><th>ID login</th><th>Email</th><th>Peran</th><th>Hasil pemeriksaan</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="{{ $row['errors'] ? 'table-warning' : '' }}">
                                    <td>{{ $row['row'] }}</td><td class="text-break">{{ $row['name'] }}</td><td class="text-break">{{ $row['login_id'] }}</td><td class="text-break">{{ $row['email'] ?? '—' }}</td>
                                    <td>{{ $row['role'] === 'administrator' ? 'Administrator' : ($row['role'] === 'operator' ? 'Operator' : $row['role']) }}</td>
                                    <td>@forelse($row['errors'] as $error)<div class="small text-danger">{{ $error }}</div>@empty<span class="badge text-bg-success">Siap ditambahkan</span>@endforelse</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if(!$hasErrors)
                    <form method="post" action="{{ route('admin.users.import.commit', $token) }}" class="mt-3" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <div class="form-check mb-3"><input id="confirm-staff-import" name="confirmed" value="1" type="checkbox" class="form-check-input" required><label for="confirm-staff-import" class="form-check-label">Saya telah memeriksa identitas dan peran seluruh akun yang akan ditambahkan.</label></div>
                        <button class="btn btn-purple" :disabled="busy"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><span x-text="busy ? 'Menyimpan…' : 'Impor {{ count($rows) }} Akun'">Impor {{ count($rows) }} Akun</span></button>
                    </form>
                @endif
            </div>
        </section>
    @endif
</x-layouts::app>
