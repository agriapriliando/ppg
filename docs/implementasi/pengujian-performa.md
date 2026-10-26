# Pengukuran performa F12

Pengukuran lokal pada 18 Oktober 2026 memakai 400 dan 4.000 peserta sintetis. Validasi XLSX 4.000 peserta turun dari **34,36 menjadi 9,26 detik** (sekitar 73% lebih singkat); query turun dari **20.005 menjadi 8.053** (sekitar 60%). Ini kemajuan F12.3 dan T33, sementara penerimaan NF-02 pada staging masih terbuka.

## Lingkungan dan metode

- Windows 11, Intel Core i7-10510U 1,80 GHz, 8 logical processor, RAM 16 GB; RAM tersedia sekitar 3,7 GB sebelum pengujian.
- PHP CLI 8.3.30, Laravel 13, MariaDB 11.8.2 terisolasi pada loopback; buffer pool 64 MiB. Engine ini berbeda dari MySQL 8.4 yang direncanakan untuk CI.
- Database khusus `pmb_isolated_performance` dibangun ulang untuk setiap ukuran dataset. Database aplikasi `db_ppg`, akun demo, dan storage privat operasional tidak dipakai untuk fixture beban.
- Workbook nyata memiliki delapan kolom template, ID teks dengan nol awal, dan empat operator aktif yang bergantian. Validasi, commit, lalu validasi ulang identik dijalankan melalui action aplikasi. Jumlah roster dan hasil identik diperiksa.
- Fixture baca berisi satu aplikasi/workspace per peserta, setengah draf dan setengah terkirim, dengan biodata nama sintetis. Fixture ini bukan contoh formulir lengkap atau data yang telah melalui seluruh approval.
- Ada 50 akun/sesi logis per dataset: satu administrator, empat operator, dan 45 pendaftar. Maksimal **delapan worker** berjalan bersamaan. Setiap akun menjalankan tiga permintaan; total 150 permintaan per dataset, 300 per pengukuran penuh.
- Staff membuka dashboard, daftar peserta dengan filter periode, dan antrean terkirim. Pendaftar membuka dashboard, formulir, dan menyimpan draf. Status HTTP, identitas sesi, redirect dan ketiadaan error formulir diperiksa; sesi database dipertahankan melalui cookie antarpemintaan. Pada kedua dataset tercatat 50 sesi tersimpan.
- Permintaan melewati Laravel HTTP kernel dan render Blade di CLI. Waktu mencakup bootstrap aplikasi tetapi autoload/class yang sudah dimuat dapat dipakai ulang dalam worker. Tidak melewati web server, jaringan, TLS atau browser. Lingkungan `testing` melewati pemeriksaan CSRF; benchmark ini tidak membuktikan CSRF atau proses login melalui UI.
- Cache view dipisahkan per worker. Percobaan awal memakai cache bersama gagal karena benturan rename berkas kompilasi Blade pada Windows; percobaan itu tidak masuk laporan lulus. Kondisi cache dan beban mesin dapat berbeda antar-run; perbandingan waktu halaman tidak dianggap bukti perbaikan UI. Jalankan pengujian staging dengan cache dan filesystem deployment sebenarnya.
- Satu run awal dan satu run setelah optimasi, tanpa pengulangan statistik panjang. p95 memakai nearest rank. Sampel staf hanya lima per halaman, sehingga p95 staf sama dengan sampel paling lambat.

## Impor dan ekspor

| Dataset | Operasi | Awal (detik / query) | Setelah optimasi (detik / query) |
| --- | --- | --- | --- |
| 400 | Validasi XLSX | 1,469 / 2.005 | 0,758 / 817 |
| 400 | Konfirmasi impor | 0,868 / 2.001 | 0,843 / 2.001 |
| 400 | Validasi ulang identik | 1,376 / 2.005 | 0,717 / 817 |
| 4.000 | Validasi XLSX | 34,356 / 20.005 | 9,258 / 8.053 |
| 4.000 | Konfirmasi impor | 11,270 / 20.001 | 11,889 / 20.001 |
| 4.000 | Validasi ulang identik | 36,164 / 20.005 | 9,609 / 8.053 |

Ekspor privat setelah optimasi: 400 peserta dalam 0,248 detik / 22 query; 4.000 peserta dalam 1,842 detik / 121 query. Jumlah baris hasil harus sama dengan roster. Job ekspor dipanggil langsung; angka ini tidak mencakup waktu tunggu queue, pengiriman unduhan, atau pekerjaan bersamaan.

Peak heap proses pengukuran pada 4.000 peserta: validasi 86 MiB, validasi ulang 90 MiB, ekspor 146 MiB. Angka proses induk mencakup fixture dan objek yang masih berada di memori harness; bukan tambahan memori murni job atau RSS keseluruhan server. Jangan menggunakannya sendirian untuk menentukan batas memori worker produksi.

## Permintaan setelah optimasi

| Halaman/aksi | Sampel per dataset | p95 400 (ms) | p95 4.000 (ms) | Query maksimum 4.000 |
| --- | --- | --- | --- | --- |
| Dashboard pendaftar | 45 | 1.078,71 | 1.386,21 | 13 |
| Buka formulir | 45 | 194,85 | 236,39 | 15 |
| Simpan draf | 45 | 210,07 | 244,26 | 16 |
| Dashboard staf | 5 | 576,22 | 900,78 | 13 |
| Daftar peserta | 5 | 80,96 | 143,08 | 9 |
| Antrean terkirim | 5 | 82,32 | 174,17 | 10 |

Seluruh 300 permintaan pada run setelah optimasi memenuhi pemeriksaan respons/sesi; error teramati 0/300. Peak heap worker permintaan 32–38 MiB. Angka p95 berada di bawah dua detik pada metode terbatas ini, tetapi **NF-02 belum dinyatakan lulus**: 50 sesi logis dengan delapan worker tidak membuktikan 50 sesi HTTP serentak pada staging.

## Perubahan dan perlindungan regresi

`ValidateSkImport` menghitung batas baris sekali, menyimpan hasil lookup operator dan keanggotaan periode selama satu pemanggilan, serta memasukkan staging dalam kelompok 100 baris di transaksi yang sama. Hasil operator tidak disimpan lintas job. Lookup konflik SK/pengenal tetap memakai query database agar perilaku collation tidak berubah. Commit tetap memeriksa kelayakan operator terbaru.

JSON staging tetap kompatibel dengan cast model, nomor baris/error/hash pratinjau tetap dipertahankan, dan tidak ada penyimpanan roster ketika workbook invalid. Model staging tidak memiliki observer atau timestamp yang dilewati oleh insert berkelompok.

Dua tes regresi tambahan memeriksa 205 baris melewati batas kelompok, batas query, ID dengan nol awal, assignment/catatan/error, operator tanpa keanggotaan atau tidak ditemukan, dan validasi ulang setelah keanggotaan berubah. Pengujian memakai workbook nyata dan database, tanpa assertion durasi yang bergantung kecepatan mesin.

## Menjalankan ulang dan bukti

Harness ada di `tests/Performance/run.php` dan `request-worker.php`. Siapkan database SQL khusus bernama persis `pmb_isolated_performance` dan kredensial uji melalui lingkungan proses. **Seluruh isi database tersebut dihapus oleh harness.** Jangan memakai database berisi data operasional.

Dalam terminal pengujian terpisah, atur `APP_ENV=testing`, `DB_CONNECTION=mysql`, host/port/kredensial SQL uji, `DB_DATABASE=pmb_isolated_performance`, `DB_URL` kosong, `BCRYPT_ROUNDS=4`, `CACHE_STORE=array`, `SESSION_DRIVER=database`, dan `QUEUE_CONNECTION=sync`, kemudian jalankan:

```powershell
php tests/Performance/run.php nama-pengukuran
```

Guard menolak lingkungan/driver/nama database lain, DB_URL, serta nama database aktual yang berbeda sebelum membangun ulang skema. Pemanggilan memakai konfigurasi aplikasi lokal telah diuji menolak dengan exit 1. Artefak workbook/CSV/payload/cache disimpan pada `storage/framework/testing/performance`, diabaikan Git; storage aplikasi tidak dipakai untuk berkas sintetis.

Bukti agregat tanpa biodata, cookie, atau kredensial: [pengukuran awal](performa-baseline.json) dan [pengukuran setelah optimasi](performa-optimized.json). Angka waktu bukan jaminan performa pada perangkat lain.

## Langkah F12 berikutnya

1. Jalankan HTTP nyata dengan 50 sesi serentak di staging MySQL 8.4, cache deployment, dan jaringan yang dicatat. Tambahkan login, CSRF, halaman pagination/filter lanjutan, pencarian, serta skenario formulir yang lebih lengkap.
2. Ukur durasi beberapa menit, variasi run, throughput, error, CPU/RSS server dan database, serta waktu tunggu queue saat impor/ekspor bersamaan dengan aktivitas pengguna.
3. Tinjau query konfirmasi impor 4.000 peserta yang masih 20.001 dan sekitar 12 detik. Optimasi berikutnya harus menjaga pemeriksaan operator, transaksi, audit assignment dan konsistensi perubahan bersamaan.
4. Periksa benturan cache Blade pada filesystem deployment sebenarnya, lalu lanjutkan QA browser, UAT dan latihan restore sesuai gate rancangan.
