<x-layouts::app title="Pengaturan Aplikasi">
    <div x-data="{
        addLinkModal: false,
        editLinkModal: false,
        activeEditLink: {
            id: null,
            title: '',
            url: '',
            description: '',
            icon: 'bi-box-arrow-up-right',
            button_color: 'purple',
            target: '_blank',
            is_active: true
        },
        openEdit(link) {
            this.activeEditLink = { ...link };
            this.editLinkModal = true;
        }
    }">
        <div class="row g-4">
            <div class="col-xl-8">
                <!-- Identitas & Layanan PMB -->
                <section class="workspace-card mb-4">
                    <div class="panel-heading">
                        <h2>Identitas & Layanan PMB</h2>
                        <p>Gunakan hanya kontak dan pengumuman yang disahkan kampus.</p>
                    </div>
                    <div class="panel-body">
                        <form method="post" action="{{ route('admin.settings.update') }}">
                            @csrf
                            @method('put')
                            <input type="hidden" name="version" value="{{ \App\Support\FormInput::old('version', $setting?->version ?? 0) }}">
                            
                            @foreach(['helpdesk_name' => 'Nama helpdesk', 'helpdesk_whatsapp' => 'Nomor WhatsApp resmi', 'service_hours' => 'Jam layanan (WIB)'] as $key => $label)
                                <div class="mb-3">
                                    <label for="setting-{{ $key }}" class="form-label fw-bold">{{ $label }}</label>
                                    <input id="setting-{{ $key }}" name="{{ $key }}" class="form-control" value="{{ \App\Support\FormInput::old($key, $setting?->value[$key] ?? '') }}" @required($key === 'helpdesk_name') maxlength="255">
                                </div>
                            @endforeach

                            <div class="mb-4">
                                <label for="setting-notice" class="form-label fw-bold">Pengumuman untuk pendaftar</label>
                                <textarea id="setting-notice" name="notice" class="form-control" rows="4" maxlength="2000">{{ \App\Support\FormInput::old('notice', $setting?->value['notice'] ?? '') }}</textarea>
                                <div class="form-text">Tempel tautan lengkap, misalnya https://kampus.ac.id/pengumuman. Tautan otomatis bisa diklik. Gunakan baris baru agar informasi mudah dibaca. Kosongkan untuk menyembunyikan pengumuman.</div>
                            </div>

                            <div class="mb-4">
                                <label for="setting-notice-placement" class="form-label fw-bold">Tampilkan pengumuman di</label>
                                <select id="setting-notice-placement" name="notice_placement" class="form-select">
                                    @foreach(['both' => 'Homepage dan Dashboard', 'homepage' => 'Homepage saja', 'dashboard' => 'Dashboard saja', 'hidden' => 'Tidak ditampilkan'] as $value => $label)
                                        <option value="{{ $value }}" @selected(\App\Support\FormInput::old('notice_placement', $setting?->value['notice_placement'] ?? 'both') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Pilih Tidak ditampilkan untuk menyembunyikan pengumuman di semua halaman tanpa menghapus teksnya. Pilih kembali lokasi tampilan untuk menampilkan pengumuman lagi.</div>
                                <x-field-error name="notice_placement" />
                            </div>

                            <button class="btn btn-purple">Simpan Pengaturan</button>
                        </form>
                    </div>
                </section>

                <!-- Daftar Tombol Tautan Cepat (Quick Links) -->
                <section class="workspace-card mb-4">
                    <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h2 class="h5 mb-1 fw-bold text-purple"><i class="bi bi-link-45deg me-1"></i>Tombol Tautan Cepat (Ruang Kerja)</h2>
                            <p class="text-muted small mb-0">Kelola tombol tautan langsung ke LMS, UKIN, Web IAKNPKY, atau sistem eksternal lainnya yang tampil di Beranda Ruang Kerja.</p>
                        </div>
                        <button type="button" @click="addLinkModal = true" class="btn btn-sm btn-purple d-inline-flex align-items-center gap-1">
                            <i class="bi bi-plus-circle" aria-hidden="true"></i>
                            <span>Tambah Tautan Baru</span>
                        </button>
                    </div>
                    <div class="panel-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 90px;" class="text-center">Urutan</th>
                                        <th>Nama Tombol & Deskripsi</th>
                                        <th>URL Tujuan</th>
                                        <th style="width: 110px;" class="text-center">Tampilan</th>
                                        <th style="width: 90px;" class="text-center">Status</th>
                                        <th style="width: 120px;" class="text-end pe-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($quickLinks as $index => $link)
                                        <tr>
                                            <td class="text-center">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <span class="badge text-bg-light border text-muted me-1">{{ $loop->iteration }}</span>
                                                    <div class="btn-group-vertical btn-group-sm">
                                                        <form method="post" action="{{ route('admin.quick-links.move-up', $link) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary p-0 px-1" title="Pindah ke Atas" @disabled($loop->first)>
                                                                <i class="bi bi-arrow-up" style="font-size: 0.75rem;"></i>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="{{ route('admin.quick-links.move-down', $link) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary p-0 px-1" title="Pindah ke Bawah" @disabled($loop->last)>
                                                                <i class="bi bi-arrow-down" style="font-size: 0.75rem;"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="dayak-shield-badge" style="width: 30px; height: 30px; font-size: 0.8rem; background-color: #f3e8fa; color: #4A154B;">
                                                        <i class="bi {{ $link->icon ?: 'bi-box-arrow-up-right' }}" aria-hidden="true"></i>
                                                    </span>
                                                    <div>
                                                        <strong class="d-block text-dark">{{ $link->title }}</strong>
                                                        @if($link->description)
                                                            <small class="text-muted">{{ $link->description }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none small text-truncate d-inline-block" style="max-width: 200px;" title="{{ $link->url }}">
                                                    {{ $link->url }} <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.65rem;"></i>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge" style="
                                                    @if($link->button_color === 'purple') background-color: #6a3283; color: white;
                                                    @elseif($link->button_color === 'gold') background-color: #eab308; color: black;
                                                    @elseif($link->button_color === 'blue') background-color: #2563eb; color: white;
                                                    @elseif($link->button_color === 'green') background-color: #16a34a; color: white;
                                                    @elseif($link->button_color === 'teal') background-color: #0d9488; color: white;
                                                    @else background-color: #334155; color: white;
                                                    @endif
                                                ">
                                                    {{ ucfirst($link->button_color) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if($link->is_active)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" @click="openEdit({{ json_encode($link) }})" class="btn btn-sm btn-outline-purple" title="Edit Tautan">
                                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                                    </button>
                                                    <form method="post" action="{{ route('admin.quick-links.destroy', $link) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tautan ini?')">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Tautan">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-link-45deg fs-3 d-block mb-2 text-secondary"></i>
                                                Belum ada tombol tautan cepat yang ditambahkan.<br>
                                                <button type="button" @click="addLinkModal = true" class="btn btn-sm btn-purple mt-2">
                                                    <i class="bi bi-plus-circle me-1"></i>Tambah Tautan Pertama
                                                </button>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <div class="notice-box info mb-4">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <div>
                        <strong>Zona waktu layanan</strong>
                        <p>Jadwal dan tanggal bisnis menggunakan Asia/Jakarta (WIB).</p>
                        <p class="mb-0">Penyerahan akses WhatsApp dilakukan manual oleh operator resmi setelah pemeriksaan identitas.</p>
                    </div>
                </div>

                <div class="workspace-card">
                    <div class="panel-heading">
                        <h2 class="h6 mb-0 fw-bold"><i class="bi bi-lightbulb text-warning me-2"></i>Petunjuk Tautan Cepat</h2>
                    </div>
                    <div class="panel-body small text-muted">
                        <p>Tombol tautan cepat akan langsung tampil di <strong>Beranda Ruang Kerja</strong> (<code>/ruang-kerja</code>) untuk seluruh pengguna (pendaftar, operator, dan administrator).</p>
                        <ul class="ps-3 mb-0">
                            <li class="mb-2">Gunakan tombol <strong>panah atas/bawah</strong> untuk mengatur urutan tombol sesuai prioritas.</li>
                            <li class="mb-2">Tautan dapat berupa URL lengkap seperti <code>https://lms.iaknpky.ac.id</code> atau <code>https://ukin.kemdikbud.go.id</code>.</li>
                            <li>Tautan nonaktif akan disembunyikan dari dashboard tanpa perlu menghapus datanya.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Tautan Baru -->
        <div 
            class="ui-modal-backdrop"
            x-show="addLinkModal"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
        >
            <div 
                class="ui-modal"
                style="max-width: 600px;"
                x-show="addLinkModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="addLinkModal = false"
            >
                <div class="ui-modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            <i class="bi bi-plus-circle" aria-hidden="true"></i>
                        </span>
                        <h2 class="h6 mb-0 fw-bold text-purple">Tambah Tautan Cepat Baru</h2>
                    </div>
                    <button type="button" class="btn-close" @click="addLinkModal = false" aria-label="Tutup"></button>
                </div>

                <form method="post" action="{{ route('admin.quick-links.store') }}">
                    @csrf
                    <div class="ui-modal-body">
                        <div class="mb-3">
                            <label for="new-title" class="form-label fw-bold">Judul / Nama Tombol *</label>
                            <input type="text" id="new-title" name="title" class="form-control" required maxlength="100" placeholder="Misal: LMS PPG, UKIN, Web IAKNPKY">
                        </div>

                        <div class="mb-3">
                            <label for="new-url" class="form-label fw-bold">URL Tujuan *</label>
                            <input type="url" id="new-url" name="url" class="form-control" required maxlength="500" placeholder="https://...">
                        </div>

                        <div class="mb-3">
                            <label for="new-desc" class="form-label fw-bold">Deskripsi Singkat (Opsional)</label>
                            <input type="text" id="new-desc" name="description" class="form-control" maxlength="255" placeholder="Misal: Sistem Pembelajaran Daring">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="new-icon" class="form-label fw-bold">Icon (Bootstrap Icon Class)</label>
                                <select id="new-icon" name="icon" class="form-select">
                                    <option value="bi-mortarboard-fill">bi-mortarboard-fill (Topi Toga)</option>
                                    <option value="bi-journal-check">bi-journal-check (Jurnal / Ujian)</option>
                                    <option value="bi-globe2">bi-globe2 (Website / Portal)</option>
                                    <option value="bi-laptop">bi-laptop (Sistem Komputer)</option>
                                    <option value="bi-patch-check">bi-patch-check (Sertifikasi / Hasil)</option>
                                    <option value="bi-box-arrow-up-right" selected>bi-box-arrow-up-right (Tautan Luar)</option>
                                    <option value="bi-calendar-event">bi-calendar-event (Jadwal Akademik)</option>
                                    <option value="bi-book">bi-book (Perpustakaan / Buku)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="new-color" class="form-label fw-bold">Warna Tombol</label>
                                <select id="new-color" name="button_color" class="form-select">
                                    <option value="purple" selected>Ungu (Purple Khas)</option>
                                    <option value="gold">Kuning Emas (Gold)</option>
                                    <option value="blue">Biru (Blue Primary)</option>
                                    <option value="green">Hijau (Green Success)</option>
                                    <option value="teal">Teal (Cyan / Toska)</option>
                                    <option value="dark">Gelap (Dark Slate)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="new-target" class="form-label fw-bold">Buka Di</label>
                                <select id="new-target" name="target" class="form-select">
                                    <option value="_blank" selected>Tab Baru (_blank)</option>
                                    <option value="_self">Tab Saat Ini (_self)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center pt-3">
                                <div class="form-check">
                                    <input type="checkbox" id="new-is-active" name="is_active" class="form-check-input" value="1" checked>
                                    <label for="new-is-active" class="form-check-label fw-bold">Tampilkan di Beranda (Aktif)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions border-top p-3 bg-light m-0 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" @click="addLinkModal = false">Batal</button>
                        <button type="submit" class="btn btn-purple">
                            <i class="bi bi-plus-circle me-1"></i>Simpan Tautan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Edit Tautan -->
        <div 
            class="ui-modal-backdrop"
            x-show="editLinkModal"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            role="dialog"
            aria-modal="true"
        >
            <div 
                class="ui-modal"
                style="max-width: 600px;"
                x-show="editLinkModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="editLinkModal = false"
            >
                <div class="ui-modal-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                        </span>
                        <h2 class="h6 mb-0 fw-bold text-purple">Edit Tautan Cepat</h2>
                    </div>
                    <button type="button" class="btn-close" @click="editLinkModal = false" aria-label="Tutup"></button>
                </div>

                <form method="post" :action="'{{ url('/admin/tautan-cepat') }}/' + activeEditLink.id">
                    @csrf
                    @method('put')
                    <div class="ui-modal-body">
                        <div class="mb-3">
                            <label for="edit-title" class="form-label fw-bold">Judul / Nama Tombol *</label>
                            <input type="text" id="edit-title" name="title" class="form-control" x-model="activeEditLink.title" required maxlength="100">
                        </div>

                        <div class="mb-3">
                            <label for="edit-url" class="form-label fw-bold">URL Tujuan *</label>
                            <input type="url" id="edit-url" name="url" class="form-control" x-model="activeEditLink.url" required maxlength="500">
                        </div>

                        <div class="mb-3">
                            <label for="edit-desc" class="form-label fw-bold">Deskripsi Singkat (Opsional)</label>
                            <input type="text" id="edit-desc" name="description" class="form-control" x-model="activeEditLink.description" maxlength="255">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="edit-icon" class="form-label fw-bold">Icon</label>
                                <select id="edit-icon" name="icon" class="form-select" x-model="activeEditLink.icon">
                                    <option value="bi-mortarboard-fill">bi-mortarboard-fill (Topi Toga)</option>
                                    <option value="bi-journal-check">bi-journal-check (Jurnal / Ujian)</option>
                                    <option value="bi-globe2">bi-globe2 (Website / Portal)</option>
                                    <option value="bi-laptop">bi-laptop (Sistem Komputer)</option>
                                    <option value="bi-patch-check">bi-patch-check (Sertifikasi / Hasil)</option>
                                    <option value="bi-box-arrow-up-right">bi-box-arrow-up-right (Tautan Luar)</option>
                                    <option value="bi-calendar-event">bi-calendar-event (Jadwal Akademik)</option>
                                    <option value="bi-book">bi-book (Perpustakaan / Buku)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit-color" class="form-label fw-bold">Warna Tombol</label>
                                <select id="edit-color" name="button_color" class="form-select" x-model="activeEditLink.button_color">
                                    <option value="purple">Ungu (Purple Khas)</option>
                                    <option value="gold">Kuning Emas (Gold)</option>
                                    <option value="blue">Biru (Blue Primary)</option>
                                    <option value="green">Hijau (Green Success)</option>
                                    <option value="teal">Teal (Cyan / Toska)</option>
                                    <option value="dark">Gelap (Dark Slate)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="edit-target" class="form-label fw-bold">Buka Di</label>
                                <select id="edit-target" name="target" class="form-select" x-model="activeEditLink.target">
                                    <option value="_blank">Tab Baru (_blank)</option>
                                    <option value="_self">Tab Saat Ini (_self)</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-center pt-3">
                                <div class="form-check">
                                    <input type="checkbox" id="edit-is-active" name="is_active" class="form-check-input" value="1" x-model="activeEditLink.is_active">
                                    <label for="edit-is-active" class="form-check-label fw-bold">Tampilkan di Beranda (Aktif)</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions border-top p-3 bg-light m-0 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" @click="editLinkModal = false">Batal</button>
                        <button type="submit" class="btn btn-purple">
                            <i class="bi bi-check2 me-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>
