# Pengujian database dan dua proses bersamaan

Pelaksanaan lokal: 18 Oktober 2026. Ini bukti engineering lokal, bukan berita acara UAT, hasil MySQL 8.4, atau uji beban 50 sesi.

## Lingkungan dan hasil

| Pemeriksaan | Lingkungan | Hasil |
| --- | --- | --- |
| Suite Unit/Feature | SQLite terisolasi melalui PHPUnit | 137 tes / 975 assertion lulus |
| Suite Unit/Feature | MariaDB 11.8.2, InnoDB, driver Laravel `mysql` | 137 tes / 975 assertion lulus |
| Dua proses PHP nyata | MariaDB 11.8.2, database khusus `pmb_isolated_race` | 6 tes / 49 assertion lulus |
| Instalasi skema aplikasi lokal | Database `db_ppg` pada koneksi lokal yang sudah dikonfigurasi pengguna | Seluruh 14 migrasi terpasang; penambahan skema koreksi kepemilikan tidak menghapus akun/data lokal |
| Pemeriksaan kode | Pint dan Larastan level 7 | Lulus |

Server MariaDB uji memakai direktori data terpisah di `storage/framework/testing`, loopback port 34187, dan database `pmb_isolated_feature`/`pmb_isolated_race`. Pengaturan koneksi hanya diberikan pada proses tes; `.env` tidak ditulis ulang. Fixture memakai data sintetis. Database aplikasi tidak dipakai untuk menjalankan `migrate:fresh` atau suite tes. Direktori artefak pengujian diabaikan Git.

## Skenario konkurensi

Worker ada di `tests/Support/race-worker.php`. Setiap skenario menyiapkan dua proses PHP terpisah, menunggu keduanya selesai boot, lalu membuka barrier bersama. Proses memiliki koneksi database masing-masing; ini tidak menggunakan transaksi test parent yang menyembunyikan fixture dari proses lain.

| Operasi bersamaan | Hasil yang dibuktikan |
| --- | --- |
| Dua klaim berbeda untuk satu SK | Satu persetujuan berhasil, satu konflik; hanya satu aplikasi dan pemilik SK sesuai aplikasi tersebut |
| Dua pembagian tugas dengan versi periode sama | Satu berhasil, satu konflik; versi periode bertambah sekali dan tidak ada perpindahan tambahan tersembunyi |
| Dua submit formulir identik | Keduanya mendapat hasil idempotent; hanya satu revisi, satu submission dan satu versi konfigurasi |
| Dua keputusan verifikasi dengan versi aplikasi sama | Satu berhasil, satu konflik; satu review tersimpan |
| Dua penerbitan akses sementara | Dua issuance tercatat sesuai urutan serial transaksi; hanya issuance terakhir tidak dicabut |
| Dua admin memilih pemilik tujuan berbeda untuk aplikasi yang sama | Satu koreksi berhasil, satu konflik; satu aplikasi, satu perpindahan, satu kasus dan kaitan SK sesuai pemilik baru |

Skenario tidak menjamin seluruh kemungkinan interleaving atau kinerja pada beban produksi. Race pemulihan akun, pergantian hak, bulk assignment, dan kegagalan infrastruktur masih perlu diperluas sesuai risiko staging.

## Menjalankan kembali

Suite biasa: `composer test`. PHPUnit memakai SQLite secara default kecuali variabel lingkungan database disediakan dari luar, seperti pada matriks CI.

Suite konkurensi sengaja terpisah dari suite biasa. Siapkan database SQL **khusus pengujian** bernama persis `pmb_isolated_race`; jangan tempatkan data aplikasi di sana. Setiap skenario menghapus dan membangun ulang seluruh skema database tersebut. Helper dan worker menolak koneksi selain driver `mysql`, lingkungan `testing`, dan nama database ini.

Contoh PowerShell setelah database pengujian disiapkan:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'pmb_isolated_race'
$env:DB_USERNAME = 'pengguna_database_uji'
# Isi DB_PASSWORD melalui pengelola secret lingkungan, bukan Git.
php artisan test tests/Concurrency/AdmissionsRaceTest.php
```

Gunakan terminal khusus agar variabel database pengujian tidak terbawa ke perintah operasional aplikasi. CI MySQL 8.4 sudah memiliki langkah penyiapan database khusus dan eksekusi enam skenario ini; eksekusi CI remote belum dibuktikan pada sesi ini.

## Pekerjaan penerimaan berikutnya

- Jalankan matriks MySQL 8.4 di CI/staging dan simpan hasilnya.
- Lanjutkan [pengukuran kernel 400/4.000 peserta](pengujian-performa.md) ke 50 sesi HTTP serentak pada staging; ukur p95, query, CPU/RSS, antrean dan waktu impor/ekspor. Pengukuran lokal memakai maksimal delapan worker dan belum menutup target NF-02.
- QA browser 360/768/1280 px, navigasi keyboard, lookup referensi, drawer, dan bukti A4. Inventaris sesi terakhir masih tidak menyediakan browser.
- UAT pemulihan, pengesahan SOP identitas/nomor bersama, latihan backup/restore dan penerimaan operasional.
