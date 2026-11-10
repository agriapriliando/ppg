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
            placement: 'both'
        },
        openEdit(link) {
            this.activeEditLink = { ...link, placement: link.is_active ? (link.placement || 'both') : 'hidden' };
            this.editLinkModal = true;
        }
    }" @edit-quick-link.window="openEdit($event.detail.link)">
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

                            <div class="mb-4">
                                <label for="setting-claim-peg-id" class="form-label fw-bold">Peg ID pada klaim WhatsApp</label>
                                <select id="setting-claim-peg-id" name="claim_peg_id_required" class="form-select">
                                    @foreach([0 => 'Tidak wajib', 1 => 'Wajib'] as $value => $label)
                                        <option value="{{ $value }}" @selected((int) \App\Support\FormInput::old('claim_peg_id_required', $setting?->value['claim_peg_id_required'] ?? false) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Jika wajib, peserta mengisi PEG ID dan nomor HP; nama serta periode muncul otomatis dari SK. Jika tidak wajib, formulir menampilkan empat kolom untuk diisi manual.</div>
                                <x-field-error name="claim_peg_id_required" />
                            </div>

                            <button class="btn btn-purple">Simpan Pengaturan</button>
                        </form>
                    </div>
                </section>

                <!-- Daftar Tombol Tautan Cepat (Quick Links) -->
                <section class="workspace-card mb-4">
                    <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h2 class="h5 mb-1 fw-bold text-purple"><i class="bi bi-link-45deg me-1"></i>Tombol Tautan Cepat</h2>
                            <p class="text-muted small mb-0">Kelola tombol tautan dan tentukan apakah tampil di Homepage, Dashboard, keduanya, atau disembunyikan.</p>
                        </div>
                        <button type="button" @click="addLinkModal = true" class="btn btn-sm btn-purple d-inline-flex align-items-center gap-1">
                            <i class="bi bi-plus-circle" aria-hidden="true"></i>
                            <span>Tambah Tautan Baru</span>
                        </button>
                    </div>
                    <div class="panel-body p-0">
                        <livewire:admin.quick-links-table />
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
                        <p>Setiap tombol dapat ditampilkan di <strong>Homepage</strong>, <strong>Dashboard</strong>, keduanya, atau disembunyikan melalui pilihan <strong>Tampilkan di</strong>.</p>
                        <ul class="ps-3 mb-0">
                            <li class="mb-2">Gunakan tombol <strong>panah atas/bawah</strong> untuk mengatur urutan tombol tanpa memuat ulang halaman.</li>
                            <li class="mb-2">Tautan dapat berupa URL lengkap seperti <code>https://lms.iaknpky.ac.id</code> atau <code>https://ukin.kemdikbud.go.id</code>.</li>
                            <li>Pilihan Tidak tampil menyembunyikan tombol dari semua halaman tanpa menghapus datanya.</li>
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
                            <div class="col-md-6">
                                <label for="new-placement" class="form-label fw-bold">Tampilkan di</label>
                                <select id="new-placement" name="placement" class="form-select">
                                    <option value="dashboard">Dashboard</option>
                                    <option value="homepage">Homepage</option>
                                    <option value="both" selected>Semua (Homepage dan Dashboard)</option>
                                    <option value="hidden">Tidak tampil</option>
                                </select>
                                <x-field-error name="placement" />
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
                            <div class="col-md-6">
                                <label for="edit-placement" class="form-label fw-bold">Tampilkan di</label>
                                <select id="edit-placement" name="placement" class="form-select" x-model="activeEditLink.placement">
                                    <option value="dashboard">Dashboard</option>
                                    <option value="homepage">Homepage</option>
                                    <option value="both">Semua (Homepage dan Dashboard)</option>
                                    <option value="hidden">Tidak tampil</option>
                                </select>
                                <x-field-error name="placement" />
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
