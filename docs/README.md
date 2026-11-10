# Dokumentasi aplikasi PMB PPG

Untuk petunjuk penggunaan per peran, baca **[Panduan penggunaan aplikasi](panduan/README.md)**: akses akun, pendaftaran, pemeriksaan operator, pengelolaan administrator, impor SK, penugasan, pemulihan, dan pemecahan masalah.

**Aplikasi sudah mulai diimplementasikan.** Baca [panduan menjalankan dan fitur aktual](implementasi/README.md) serta [kemajuan tiap fase](implementasi/kemajuan.md) untuk membedakan kode yang tersedia dari rencana lanjutan.

Mulai dari **[Rancangan lengkap dan tahapan pembangunan](rancangan-aplikasi/README.md)**. Paket ini disusun pada 18 Oktober 2026 berdasarkan prototipe `ppgui` dan kondisi repositori, untuk menjadi acuan implementasi aplikasi PMB PPG IAKN Palangka Raya.

Tampilan produksi dirancang menggunakan **Bootstrap 5 + Blade + Livewire 4**, mengikuti prototipe `ppgui`, dengan CSS khusus untuk identitas visual PPG.

| Bahan | Kegunaan |
| --- | --- |
| [SEO portal publik](seo.md) | Metadata, canonical, sitemap, pengaturan indeks per lingkungan, dan pemeriksaan setelah rilis |
| [Rancangan lengkap](rancangan-aplikasi/README.md) | Kebutuhan, alur, layar, skema, arsitektur, integrasi, tahapan kerja, pengujian, dan operasi |
| [Impor ulang SK berdasarkan ID](implementasi/impor-ulang-sk-id.md) | Implementasi lokal: update Excel menggunakan periode + id_peserta_sk, dua jenis impor, diff dan perlindungan data terikat; [acuan rancangan](rancangan-aplikasi/16-impor-ulang-sk-berdasarkan-id.md) |
| [Rancangan unggah video](rancangan-aplikasi/14-unggah-video.md) | Usulan 10 November 2026: validasi frontend sebelum upload, pemeriksaan minimum server, tombol buka video di tab baru dan pengujian; belum diimplementasikan |
| [Rancangan validasi frontend seluruh unggahan](rancangan-aplikasi/15-validasi-frontend-unggahan.md) | Dokumen, gambar dan video melalui area unggah lama untuk pendaftar/operator; pemeriksaan sebelum transfer, antrean, kuota dan retry; belum diimplementasikan |
| [Formulir, berkas, dan autosave](rancangan-aplikasi/11-formulir-berkas-dan-autosave.md) | Usulan 5 November 2026: draf otomatis saat mengetik, satu alur biodata–berkas, dan tombol kirim final di halaman berkas |
| [Rancangan penghapusan periode](rancangan-aplikasi/13-penghapusan-periode-menyeluruh.md) | Khusus administrator dengan konfirmasi kata sandi; inventaris seluruh data/file, pemrosesan bertahap, retry, dan isolasi periode lain |
| [Implementasi autosave dan kirim bersama](implementasi/formulir-berkas-autosave.md) | Perilaku aktual, migrasi, pengoperasian, hasil pengujian, dan batas QA browser |
| [Rancangan berkas pendaftar](rancangan-aplikasi/10-pengelolaan-berkas-pendaftar.md) | Unggah bebas bertahap, ubah nama, hapus permanen, akses pengelola, dan aturan/panduan berkas oleh admin; belum diimplementasikan |
| [Panduan Reset Data & Berkas](panduan-reset-data.md) | Panduan perintah console Artisan `pmb:reset-data` untuk mereset seluruh data operasional & berkas fisik tanpa menghapus data referensi |
| [Prototipe UI](ppgui/README.md) | Meninjau contoh tampilan dan simulasi interaksi tiga peran |
| [Pengukuran performa F12](implementasi/pengujian-performa.md) | Hasil 400/4.000 peserta, optimasi impor dan batas pengujian lokal |
| [Rancangan MVP sebelumnya](rancangan-aplikasi-pmb-ppg-iakn-palangka-raya.md) | Latar keputusan produk; dipertahankan sebagai bahan asal |

Dokumen rancangan bukan bukti bahwa fitur telah diimplementasikan. Data, jadwal, nomor SK, kontak, dan konfigurasi Neo dalam prototipe merupakan contoh.
