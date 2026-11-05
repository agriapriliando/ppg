# Implementasi aplikasi PMB PPG

Tanggal pembaruan: **18 Oktober 2026**. Status: **alur inti MVP dibangun dan lulus pengujian otomatis lokal; belum dinyatakan siap produksi**. Acuan bisnis tetap [rancangan lengkap](../rancangan-aplikasi/README.md). Tampilan memakai **Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Blade, Livewire 4, dan CSS tema dari `docs/ppgui`**.

## Yang sudah dapat digunakan

Pembaruan formulir dan berkas: [autosave, pengiriman bersama, dan riwayat revisi](formulir-berkas-autosave.md). Catatan ini menggantikan keterangan lama tentang simpan manual dan lokasi pengiriman formulir di bawah.

| Modul | Perilaku yang tersedia |
| --- | --- |
| Portal publik | Beranda bertema ungu/emas, informasi periode dari database, alur/FAQ/panduan, kontak resmi dan pengumuman yang dapat diperbarui admin |
| Autentikasi lokal | ID login/email, normalisasi identitas, pembatasan percobaan, logout POST, akun nonaktif ditolak, wajib ganti sandi awal untuk pengelola baru |
| Google | Redirect/callback Socialite, identitas berdasarkan provider ID, email terverifikasi, akun publik selalu pendaftar, penolakan benturan identitas, pengaitan setelah pembuktian sandi lokal |
| Periode | Buat/ubah, tanggal WIB tersimpan UTC, periode publik tunggal, tutup/buka manual beralasan, deadline perbaikan, arsip dengan pemeriksaan pekerjaan terbuka, konflik versi dua editor |
| Pengelola | Membuat operator/admin, hash sandi, aktivasi/nonaktivasi, larangan menonaktifkan diri sendiri/admin terakhir, pencabutan sesi |
| Referensi | Publikasi dataset JSON yang diperiksa seluruhnya; pilihan biodata, hierarki wilayah, PT/prodi; versi lama dipertahankan; lookup berhalaman |
| Pernyataan | Naskah klaim dan pengiriman formulir yang diterbitkan admin, versi tidak ditimpa, bukti persetujuan tersimpan |
| SK Excel | Template `.xlsx` nyata, unggah privat, validasi queue, pratinjau per baris, konflik/duplikasi/formula ditolak, commit atomik, pratinjau kedaluwarsa/stale ditolak, reimpor identik tidak mengganda, laporan kesalahan XLSX dan retensi otomatis |
| Penugasan | Pendaftaran operator periode, pratinjau pembagian, round-robin SK belum ditugaskan, transfer tunggal/massal, penyeimbangan beban dengan pratinjau berhalaman dan perubahan minimum, audit serta scope operator pada daftar/detail/aksi |
| Klaim | Google maupun tanpa Gmail, respons publik aman, kandidat tunggal internal, triase/delegasi klaim ambigu, tanggapan pemohon dan pembatalan sendiri sebelum persetujuan, pending tidak mengunci SK, persetujuan menghasilkan satu aplikasi |
| WhatsApp manual | Ringkasan bukti balasan untuk nomor spesifik, nomor bersama menjadi kasus admin, perubahan nomor membatalkan bukti lama |
| Akses WhatsApp | Secret acak 20 karakter tampil sekali melalui respons `no-store`, hanya hash disimpan, catatan penyerahan, expiry, konsumsi sekali pakai, sesi aktivasi terbatas, sandi permanen wajib |
| Formulir | Lima tahap mengikuti prototipe; draf pribadi sebelum klaim; simpan server dengan versi; referensi induk-anak; KPS bersyarat; NIK/NISN teks dan terenkripsi; ringkasan sebelum kirim |
| Pemeriksaan | Antrean sesuai tugas, checklist revisi, perbaikan spesifik, kirim ulang, diterima lengkap dengan syarat WA/roster/review, buka kembali oleh admin beralasan |
| Kondisi administratif | Penahanan/penarikan SK; kasus nomor bersama; koreksi kepemilikan kedua jalur dengan kasus, pencabutan sesi dan pemisahan draf/consent/review; pengiriman/keputusan diblokir sesuai keadaan |
| Dashboard | Hitungan dari SK/aplikasi sebenarnya; draf pra-klaim tidak dihitung sebagai aplikasi; scope operator; ringkasan pribadi pendaftar |
| Bukti | Tersedia setelah kirim pertama, status/revisi/waktu cetak, snapshot periode, cetak/simpan PDF melalui browser; tanpa NIK/nama ibu |
| Ekspor | CSV privat seluruh hasil filter, admin saja, proteksi formula/nol awal, pemeriksaan ulang scope saat unduh, expiry 24 jam dan perintah cleanup |
| Akun/audit | Nama tampilan terpisah dari biodata/SK, penggantian sandi dengan sandi lama, riwayat operator sendiri, audit admin dengan aktor asli |

Tidak ada pemilih peran demo, login palsu, penyimpanan biodata di localStorage, atau pengiriman Neo simulasi pada aplikasi produksi. Prototipe asal tetap berada di `docs/ppgui`.

## Menjalankan di lingkungan ini

Konfigurasi lokal terbaru memakai koneksi **MySQL ke `db_ppg`** sesuai instruksi pengguna. Seluruh migrasi sudah diterapkan; database SQLite awal tetap dipertahankan. Aset Bootstrap sudah dibuild. Atas permintaan pengguna, akun pemeriksaan lokal untuk ketiga peran sudah disiapkan melalui seeder, tanpa menghapus data operasional.

`SESSION_DRIVER=database` dipakai agar sesi tersimpan pada tabel `sessions` dan pencabutan akses lintas perangkat bekerja sesuai alur keamanan aplikasi. Nilai `cache` bukan nama driver sesi yang didukung dan menyebabkan halaman web gagal; konfigurasi lokal tersebut sudah diperbaiki. Empat halaman publik kembali memberikan HTTP 200 setelah perbaikan.

1. Buka `/masuk` dan gunakan akun yang sudah tersedia: `admin`, `operator`, atau `pendaftar`, dengan password awal `PpgDemo2026!`. Rincian ada pada [akun pemeriksaan lokal](akun-demo.md). Tidak perlu menjalankan perintah pembuatan akun untuk memeriksa aplikasi. Akun demo hanya untuk lingkungan lokal/testing; bootstrap administrator produksi tetap memakai prosedur kredensial pribadi.

2. Jalankan proses development:

   ```powershell
   composer dev
   ```

   Ini menjalankan server Laravel, queue listener, dan Vite. Server pemeriksaan selama pekerjaan ini menggunakan `http://127.0.0.1:8018`; jika prosesnya sudah berhenti, gunakan alamat yang dicetak oleh `artisan serve`/`composer dev`.

3. Worker harus aktif untuk validasi Excel dan ekspor. Bila menjalankan server terpisah:

   ```powershell
   php artisan serve
   php artisan queue:work --tries=1 --timeout=60
   ```

   Jalankan kedua perintah pada terminal berbeda. `retry_after` queue database bawaan adalah 90 detik, lebih panjang dari timeout job 60 detik. Pastikan worker produksi diawasi sebagai service.

4. Untuk scheduler lokal yang terus berjalan:

   ```powershell
   php artisan schedule:work
   ```

   Produksi menjalankan scheduler sesuai [runbook operasional](../rancangan-aplikasi/09-operasional-dan-keputusan.md). Cleanup `pmb:expire-exports` dijadwalkan setiap jam.

## Instalasi dari checkout baru

Prasyarat: PHP 8.3 beserta `pdo_sqlite` atau `pdo_mysql`, `mbstring`, XML, GD, dan ZIP; Composer; Node 22 yang kompatibel dengan Vite; npm.

```powershell
composer install
npm ci
```

Buat `.env` dari `.env.example` **hanya jika belum ada**. Jalankan `php artisan key:generate` sekali saat instalasi baru. Jangan mengganti APP_KEY pada instalasi yang sudah menyimpan biodata terenkripsi.

```powershell
php artisan migrate
npm run build
php artisan pmb:create-admin admin
composer dev
```

Untuk MySQL, buat database PMB khusus dan isi `DB_CONNECTION`, host, database, user, serta sandi pada `.env` melalui pengelola lingkungan. Jangan memakai database Feederdash atau database produksi untuk menjalankan tes. Document root Laragon harus menunjuk `public`.

Vite Plus starter diganti dengan **Vite standar** karena paket awal tidak cocok dengan Node lokal dan membawa temuan audit. `package-lock.json` dan `composer.lock` disertakan untuk instalasi yang konsisten. Konfigurasi Tailwind starter dihapus; pagination Laravel/Livewire memakai Bootstrap.

## Menyiapkan penerimaan

1. Masuk sebagai admin. Buat periode **Draf**, kode periode stabil, nama, tahun akademik, gelombang dan jadwal WIB.
2. Buat akun operator. Serahkan sandi awal melalui kanal resmi yang aman; pengguna wajib menggantinya pada masuk pertama.
3. Di **Pembagian Operator**, pilih periode dan operator. Konfirmasikan meskipun jumlah peserta masih nol untuk mendaftarkan operator periode sebelum impor yang memakai `operator_email`.
4. Di **Referensi & Pernyataan**, terbitkan dataset dari sumber sah. Gunakan [panduan file terpisah](referensi-contoh/README.md) untuk mengunggah pilihan biodata, wilayah (termasuk format GetWilayah), perguruan tinggi, dan program studi. Satu kolom unggah menerima satu atau beberapa file sejak impor pertama, tanpa isian versi/sumber. Jenis dikenali otomatis dan dapat dilengkapi bertahap. Format satu file gabungan juga diterima; lihat [template JSON](referensi.template.json). Template berisi placeholder, bukan referensi resmi yang boleh langsung diaktifkan.
5. Terbitkan naskah untuk **pemeriksaan klaim** dan **pengiriman formulir** setelah disahkan pengelola. Tanpa naskah klaim, form publik belum dibuka; tanpa naskah submit, formulir belum dapat dikirim.
6. Unduh template Excel dari **Impor SK**. Isi sheet `Peserta_SK`; `periode` harus menggunakan kode periode tujuan. Nomor/pengenal resmi disimpan sebagai teks, tanggal SK `YYYY-MM-DD`; tidak memakai formula.
7. Unggah berkas, tunggu validasi worker, tinjau seluruh kesalahan/perubahan, kemudian konfirmasi. Pada hasil invalid/gagal, gunakan **Unduh Laporan Kesalahan Excel** untuk memperbaiki nomor baris yang disebutkan lalu unggah ulang. Tidak ada penyimpanan roster sebagian ketika ada konflik/error. Laporan memakai sel teks eksplisit untuk menjaga nol awal dan mencegah isi sel diperlakukan sebagai formula.
8. Bagi peserta yang belum ditugaskan. Pratinjau menunjukkan beban awal/tambahan/akhir. Beban lama tidak diam-diam diseimbangkan ulang.
9. Periksa kontak/jam layanan/pengumuman, sumber SK, seluruh tugas, referensi, naskah, dan jadwal. Publikasikan periode dan pilih sebagai periode publik sesudah siap.

Untuk perubahan tugas yang sudah ada, buka **Transfer Massal & Penyeimbangan Beban** dari halaman Pembagian Operator. Pilih transfer satu sumber ke satu tujuan atau penyeimbangan seluruh peserta aktif ke operator terpilih. Tinjau tabel beban dan seluruh halaman rincian, isi alasan lalu konfirmasikan. Operator tujuan harus aktif dan terdaftar pada periode. Pratinjau berlaku 15 menit; perubahan data atau operator setelah pratinjau mengharuskan peninjauan ulang. Penyeimbangan mempertahankan tugas lama sebanyak kuota memungkinkan. Peserta ditarik tidak dipindahkan.

Pada klaim yang belum mempunyai kandidat, admin dapat menentukan **Penanggung jawab klaim** untuk triase. Operator yang didelegasikan dapat memeriksa dan meminta informasi; pencocokan kandidat tetap dilakukan admin. Pendaftar yang sudah masuk dapat menjawab permintaan informasi dari **Kepesertaan SK**, atau membatalkan permohonan yang belum disetujui. Tanggapan disimpan sebagai riwayat dan tidak mengganti identitas SK. Pemohon publik WhatsApp yang belum mempunyai akun tetap ditindaklanjuti melalui kanal layanan resmi.

## Retensi berkas impor

`PMB_IMPORT_RETENTION_DAYS=30` menerapkan usulan awal retensi berkas sumber dan baris staging. Masa dihitung dari perubahan terakhir run; antrean dan validasi yang masih berjalan tidak dibersihkan. Perintah `php artisan pmb:prune-imports` dijadwalkan setiap hari pukul 02.00 WIB melalui scheduler Laravel. Scheduler harus dijalankan di server agar pembersihan otomatis berlangsung. Metadata impor, hash sumber, ringkasan hasil, audit dan peserta yang sudah diimpor tetap tersimpan; rincian dan laporan lama tidak lagi tersedia. Simpan sumber SK resmi sesuai kebijakan kampus dan sahkan masa retensi sebelum produksi. Angka minimum efektif konfigurasi adalah satu hari.

Referensi tidak dibuat dari nilai contoh prototipe. Seluruh data sintetis pengujian berada pada test/factory terisolasi.

## Menguji satu alur operasional

**Google:** isi konfigurasi OAuth, masuk, simpan draf bila perlu, ajukan pencocokan SK, operator menyetujui, lengkapi formulir, kirim, perbaiki sesuai catatan, kirim ulang, operator memverifikasi data/WA lalu menetapkan diterima lengkap.

**Tanpa Gmail:** ajukan nama/pengenal/WA pada halaman publik, operator mencatat bukti balasan, menyetujui SK, menerbitkan akses sekali tampil, menyerahkannya manual, dan mencatat penyerahan. Pendaftar masuk memakai sandi sementara, menggantinya dalam sesi aktivasi, lalu memakai formulir yang sama.

**Perbaikan:** pendaftar hanya menulis pada draf/perbaikan. `submitted_at` dan tanggal WIB pertama tetap; revisi serta waktu kirim ulang dicatat. Nomor WhatsApp yang berubah memerlukan bukti baru sebelum diterima lengkap.

**Nomor bersama:** sistem membuat kasus administratif. Admin memeriksa bukti dan mencatat resolusi; resolusi kasus tidak otomatis menyetujui klaim/formulir.

**Pemulihan akses WhatsApp dengan nomor yang sama:** setelah nomor saat ini terverifikasi, operator dapat menerbitkan ulang dari klaim yang sudah disetujui. Penerbitan baru mencabut issuance/sesi lama dan mengulang aktivasi.

**Nomor lama hilang:** admin membuka klaim WhatsApp yang telah disetujui, lalu memilih **Pemulihan Saat Nomor Lama Hilang**. Cocokkan ID login, pemilik akun dan SK melalui SOP resmi, periksa balasan nomor baru, masukkan nomor baru dua kali, serta catat alasan dan rujukan bukti tanpa sandi/kode/NIK lengkap. Seluruh checklist wajib. Nomor yang sudah dipakai akun lain, kasus terbuka, akun nonaktif, SK ditahan/ditarik, atau periode arsip diblokir. Penggantian kontak hanya untuk pemilik yang sama; bukan pemindahan kepemilikan.

Setelah disimpan, kasus pemulihan dan bukti nomor baru dicatat, bukti nomor lama dibatalkan, seluruh sesi dan kredensial lama dicabut, serta akun menunggu aktivasi ulang. Nomor asli pada pengajuan klaim tetap sebagai riwayat. Operator kemudian **menerbitkan akses baru**, menyerahkannya ke nomor formulir terbaru, mencatat penyerahan, dan pendaftar mengaktifkan kembali dengan sandi pribadi. Formulir yang pernah dikirim kembali ke perbaikan; keputusan lama tetap histori, sedangkan tanggal/snapshot pertama tidak berubah. Kirim ulang dan pemeriksaan harus mengikuti jadwal perbaikan yang berlaku. SOP pembuktian identitas tetap perlu disahkan kampus sebelum penggunaan produksi.

**Riwayat ketentuan:** halaman admin **Riwayat Konfigurasi** menampilkan konfigurasi efektif yang dipakai saat kirim. Detail pemeriksaan pendaftaran menampilkan rujukan konfigurasi/naskah per revisi. Versi identik digunakan ulang; periode/jadwal, dataset atau naskah yang berubah menghasilkan versi baru. Rekaman versi tidak diedit atau dihapus melalui aplikasi. Pencatatan otomatis tidak mengisi kolom pengesahan institusi. Snapshot lama sebelum fitur ini tetap dipertahankan dengan rujukan versi kosong; riwayat tidak direka ulang dari konfigurasi sekarang.

**Koreksi akun pemilik:** admin membuka detail pemeriksaan dan memilih **Koreksi Kepemilikan Akun**. SK wajib ditahan sementara dan pemilik tujuan mengajukan klaim sendiri. Akun aktif dibuktikan aksesnya; pemohon tanpa akun wajib mempunyai bukti balasan WhatsApp. Pratinjau, pemeriksaan kedua pihak, alasan dan checklist wajib sebelum konfirmasi. Biodata lama tidak dipindahkan; akun tujuan memakai drafnya sendiri dan wajib mengirim formulir sebelum bukti tersedia. Nomor/tanggal pertama tetap, keputusan accepted diperiksa ulang, sesi/akses sementara dicabut dan seluruh perpindahan dicatat. Lihat [SOP koreksi kepemilikan](koreksi-kepemilikan.md). Penyatuan dua pendaftaran pada periode yang sama tetap ditolak.

## Google OAuth

Isi hanya pada secret lingkungan server:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8018/auth/google/callback
```

Gunakan URI yang sesuai server sebenarnya dan daftarkan persis di konfigurasi Google. Kosongkan jika belum disiapkan; tombol Google tidak berpura-pura berfungsi tanpa konfigurasi. Untuk staff lokal, pengaitan Google dilakukan dari **Akun & Keamanan** setelah pembuktian sandi lokal dan kepemilikan Google. Email yang sama tidak menggabungkan akun otomatis.

Pengujian otomatis Google memakai fake provider. Callback OAuth nyata dengan kredensial kampus belum diuji dalam pekerjaan ini.

## Bukti pengujian lokal

| Pemeriksaan | Hasil terakhir |
| --- | --- |
| Suite SQLite, Pint dan Larastan level 7 | Lulus: **139 tes / 997 assertion**, termasuk koreksi pemilik, perlindungan histori dan regresi impor berkelompok |
| Suite Unit/Feature MariaDB 11.8.2 | Lulus: **139 tes / 997 assertion**, database uji terpisah |
| Pengukuran F12 400/4.000 peserta | Validasi 4.000 turun 34,36 → 9,26 detik; 300 permintaan kernel berhasil setelah optimasi, 50 sesi logis dengan maksimal 8 worker; [metode dan batas hasil](pengujian-performa.md) |
| Suite konkurensi MariaDB 11.8.2 | Lulus: **6 tes / 49 assertion**, termasuk dua admin mengoreksi pemilik bersamaan |
| `npm run build` | Lulus; CSS Bootstrap, ikon lokal, dan JavaScript dibundel |
| `npm audit` | Tidak ditemukan kerentanan pada dependency tree yang terpasang |
| `composer audit` | Tidak ditemukan advisory pada dependensi yang terpasang |
| `php artisan migrate` | Seluruh 14 migrasi terpasang pada `db_ppg`; migrasi koreksi pemilik tidak menghapus akun/data yang sudah ada |
| Kompilasi Blade | Berhasil; halaman manajemen yang diimplementasikan juga diuji melalui HTTP dengan data tersimpan |
| Pemindaian aset runtime | Tidak ada Tailwind, localStorage biodata, atau fungsi simulasi prototipe pada `app/config/resources/routes` aktif |

Skenario mencakup impor nyata/identik/invalid, laporan XLSX/sel teks/retensi, pembagian 400/4 dan 403/4, transfer tunggal/massal dan penyeimbangan 403/4, stale preview/expiry/operator nonaktif/IDOR, delegasi dan pencabutan scope, tanggapan/pembatalan klaim, klaim ganda, draf pra-klaim, ciphertext NIK, perubahan nomor, consent/KPS, pergantian hari WIB, perbaikan/accepted/reopen, credential sekali pakai/expiry/aktivasi, nomor bersama, ekspor/filter/expiry/formula, profil dan pengaitan Google.

## Gate yang masih harus dipenuhi

- Review visual desktop/ponsel, keyboard/modal, pencarian referensi di browser, dan cetak A4 belum dijalankan: tidak ada browser yang tersambung pada sesi pemeriksaan. Tes render HTTP tidak menggantikan pengujian interaksi browser.
- CI telah disiapkan untuk SQLite dan MySQL 8.4, termasuk enam skenario konkurensi. Suite dan dua proses bersamaan sudah lulus di MariaDB lokal; eksekusi MySQL 8.4 remote serta 50 sesi HTTP serentak staging belum dibuktikan. Pengukuran kernel lokal tersedia dalam [laporan performa](pengujian-performa.md); lihat juga [laporan database dan konkurensi](pengujian-database-dan-konkurensi.md).
- UAT staf, pengesahan kebijakan/data resmi, backup/restore dan pengukuran RPO/RTO, konfigurasi produksi, serta go-live belum dilakukan.
- Neo R2 belum diaktifkan atau dikirim. Memerlukan instance uji, kontrak/readback, NIM dan mapping yang disahkan; tidak ada keberhasilan sinkron palsu.
- Rincian fungsi yang belum selesai dan penyederhanaan skema dicatat pada [kemajuan dan pekerjaan berikutnya](kemajuan.md). Jangan menandai seluruh 89 tiket rancangan sebagai selesai.
