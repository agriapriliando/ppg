# Dokumentasi aplikasi PMB PPG

Untuk petunjuk penggunaan per peran, baca **[Panduan penggunaan aplikasi](panduan/README.md)**: akses akun, pendaftaran, pemeriksaan operator, pengelolaan administrator, impor SK, penugasan, pemulihan, dan pemecahan masalah.

**Aplikasi sudah mulai diimplementasikan.** Baca [panduan menjalankan dan fitur aktual](implementasi/README.md) serta [kemajuan tiap fase](implementasi/kemajuan.md) untuk membedakan kode yang tersedia dari rencana lanjutan.

Mulai dari **[Rancangan lengkap dan tahapan pembangunan](rancangan-aplikasi/README.md)**. Paket ini disusun pada 18 Oktober 2026 berdasarkan prototipe `ppgui` dan kondisi repositori, untuk menjadi acuan implementasi aplikasi PMB PPG IAKN Palangka Raya.

Tampilan produksi dirancang menggunakan **Bootstrap 5 + Blade + Livewire 4**, mengikuti prototipe `ppgui`, dengan CSS khusus untuk identitas visual PPG.

| Bahan | Kegunaan |
| --- | --- |
| [Rancangan lengkap](rancangan-aplikasi/README.md) | Kebutuhan, alur, layar, skema, arsitektur, integrasi, tahapan kerja, pengujian, dan operasi |
| [Rancangan berkas pendaftar](rancangan-aplikasi/10-pengelolaan-berkas-pendaftar.md) | Unggah bebas bertahap, ubah nama, hapus permanen, akses pengelola, dan aturan/panduan berkas oleh admin; belum diimplementasikan |
| [Prototipe UI](ppgui/README.md) | Meninjau contoh tampilan dan simulasi interaksi tiga peran |
| [Pengukuran performa F12](implementasi/pengujian-performa.md) | Hasil 400/4.000 peserta, optimasi impor dan batas pengujian lokal |
| [Rancangan MVP sebelumnya](rancangan-aplikasi-pmb-ppg-iakn-palangka-raya.md) | Latar keputusan produk; dipertahankan sebagai bahan asal |

Dokumen rancangan bukan bukti bahwa fitur telah diimplementasikan. Data, jadwal, nomor SK, kontak, dan konfigurasi Neo dalam prototipe merupakan contoh.
