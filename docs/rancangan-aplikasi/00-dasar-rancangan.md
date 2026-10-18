# 00 — Dasar rancangan dan hasil telaah

## 1. Sumber yang diperiksa

Path berikut relatif terhadap akar repositori `C:\laragon\www\ppg`.

| Sumber | Informasi yang digunakan |
| --- | --- |
| `docs/ppgui/README.md` | Ruang lingkup demo, langkah mencoba, batas localStorage dan layanan eksternal |
| `docs/ppgui/index.php` | Beranda, cek SK, metode masuk, alur, jadwal, FAQ, kontak |
| `docs/ppgui/login.php` | Google, login lokal, login pengelola, aktivasi sandi, pemulihan |
| `docs/ppgui/klaim.php`, `panduan.php` | Pengajuan publik WhatsApp dan panduan peran |
| `docs/ppgui/portal.php`, `includes/demo.php` | Kerangka ruang kerja, daftar menu, pilihan peran demo |
| `docs/ppgui/includes/views/pendaftar.php` | Dashboard, kepesertaan SK, lima tahap formulir, status, bukti, bantuan |
| `docs/ppgui/includes/views/pengelola.php` | Antrean, detail, klaim, impor, penugasan, pengguna, periode, referensi, Neo, audit, pengaturan |
| `docs/ppgui/includes/views/modals.php` | Syarat konfirmasi aksi dan bukti yang diminta |
| `docs/ppgui/includes/views/akun.php`, `tabel-peserta.php` | Profil/keamanan dan filter/paginasi tabel |
| `docs/ppgui/includes/header.php`, `navbar.php`, `footer.php` | Identitas visual, navigasi, modal klaim publik, bantuan |
| `docs/ppgui/assets/js/app.js`, `portal.js` | Data simulasi, perilaku status, klaim, WhatsApp, impor, ekspor, dan Neo |
| `docs/ppgui/assets/css/style.css`, `portal.css` | Warna, tipografi, ornamen Dayak, portal responsif dan cetak |
| `docs/rancangan-aplikasi-pmb-ppg-iakn-palangka-raya.md` | Keputusan MVP dan pemetaan Neo historis |
| `composer.json`, `composer.lock`, `package.json` | Stack, dependensi, perintah validasi/build |
| `routes/web.php`, `app/Models/User.php`, `database/migrations/*` | Fondasi aplikasi yang benar-benar ada |
| `resources/css/app.css`, `resources/views/welcome.blade.php`, `tests/*`, `.github/workflows/tests.yml` | Fondasi frontend, tes contoh, CI |

Library vendor prototipe merupakan bahan runtime demo, bukan spesifikasi bisnis. Tidak ada penggunaan identitas asli, isi `.env`, database produksi, atau sistem kampus eksternal untuk menyusun rancangan.

## 2. Kondisi awal yang teramati

| Area | Kondisi pada telaah | Konsekuensi |
| --- | --- | --- |
| Backend | Laravel terpasang 13.37.0; deklarasi `^13.17`; PHP proyek `^8.3` | Gunakan Laravel 13; cocokkan patch dengan lock saat implementasi |
| Reaktivitas | Livewire 4.4.7 dan Blaze 1.0.19 terpasang | Gunakan Livewire 4; belum ada komponen PMB |
| Frontend | Tailwind 4 dideklarasikan pada proyek; Vite/Vite Plus tersedia | Target produksi mengikuti build proyek; UI prototipe diterjemahkan ke Blade |
| Demo | PHP biasa + Bootstrap + Alpine lokal; state `ppg-ui-demo-v1` | Bukan backend Laravel atau penyimpanan yang dapat dibagikan antarpengguna |
| Route | `/` hanya menuju `welcome` | Semua halaman dan aksi PMB masih perlu dibangun |
| Data | Tabel dasar users, sessions, reset tokens, cache, jobs | Belum ada skema SK, klaim, aplikasi, verifikasi, atau Neo |
| User | Email dan password saat ini wajib pada migrasi dasar | Perlu migrasi tambahan untuk akun tanpa email dan Google tanpa sandi lokal |
| Database lokal | Dilaporkan SQLite oleh Boost | Tidak cukup sebagai bukti perilaku lock produksi |
| Dependensi | Socialite dan pembaca `.xlsx` belum terdaftar | Perlu ditambahkan setelah pemeriksaan kompatibilitas |
| Tes | Pest dengan pengujian contoh | Belum ada bukti uji alur PMB |

Versi berasal dari inspeksi lokal dan `application-info`, bukan jaminan versi deployment mendatang. Database target yang **diusulkan** adalah MySQL dengan InnoDB; samakan staging/CI integrasi dengan versi produksi yang dipilih.

## 3. Celah prototipe dan penyelesaiannya

| Temuan | Keputusan rancangan produksi | Rujukan |
| --- | --- | --- |
| `?role=` dan pemilih peran membuka semua ruang kerja | Peran berasal dari sesi dan database; hilangkan pemilih peran publik | 01, 05 |
| Pencarian `includes()` mengembalikan satu nama pertama, menampilkan nomor peserta/operator, dan tidak menggunakan `searchNopes` | Pencocokan server memakai nama + pengenal bila tersedia; hasil publik generik; nama kembar masuk pemeriksaan | 02, 03, 06 |
| Draf demo bisa diisi sebelum klaim, tetapi rancangan awal membuat aplikasi setelah klaim disetujui | Tambahkan `applicant_workspaces` per akun/periode untuk draf pra-klaim; aplikasi resmi dibuat saat persetujuan | 02, 04 |
| State boolean WhatsApp tidak terikat bukti dan nomor tertentu | Rekam verifikasi atas nomor ternormalisasi tertentu; perubahan nomor mencabut bukti aktif | 02, 04 |
| Hanya satu formulir dan sejumlah baris contoh | Setiap pengguna/aplikasi mempunyai data sendiri di server dan batas akses per baris | 04, 05 |
| Metrik 400/100 tetap, tabel hanya sampel | Hitung query berdasarkan periode, scope operator, dan definisi metrik | 01, 03 |
| Impor memilih file, tetapi pratinjau tetap 400 baris | Parser nyata, validasi seluruh file, staging, hash, pratinjau yang terikat versi, transaksi commit | 06 |
| `periodOpen` terpisah dari tanggal buka/tutup | Satu perhitungan kelayakan berbasis waktu server dan status periode | 02 |
| Penyerahan kredensial hanya simulasi | Hash sandi sementara, tampil sekali, catatan penyerahan, sesi aktivasi terbatas, wajib ganti sandi | 02, 05 |
| Pendidikan/wilayah hanya pilihan contoh | Referensi terversi; pasangan induk-anak diverifikasi server | 04, 06 |
| Neo dapat disimulasikan walau NIM/config belum ada | Produksi mensyaratkan kesiapan data, NIM, kontrak, otorisasi admin, dan baca balik | 06 |
| Riwayat demo berupa daftar event global | Pisahkan riwayat pendaftar, pemeriksaan operator, dan audit internal | 04, 05 |
| Nomor, jadwal, kontak, sekolah contoh tampil tetap | Seluruh konten produksi dari konfigurasi disahkan; sekolah tempat mengajar belum masuk scope formulir | 03, 09 |

Angka 400 peserta dan empat operator adalah **skenario penerimaan**, bukan kapasitas maksimum atau pembatasan tetap. Label “belum mendaftar” mewakili SK tanpa aplikasi; tidak perlu membuat aplikasi kosong untuk memenuhi dashboard.

## 4. Batas bukti eksternal

Kode Feederdash dan koleksi Postman historis yang disebut rancangan lama **tidak ditemukan dalam inventaris proyek ini**. Paket ini tidak mengklaim kontrak Neo, whitelist historis, ID PT/prodi, atau kredensialnya sudah diverifikasi. Kontrak tersebut menjadi prasyarat tahap integrasi, bukan prasyarat menulis modul inti PMB.

Autentikasi Google direncanakan melalui Socialite yang menyediakan provider Google dan alur redirect/callback. Pengikatan identitas dan pemberian peran tetap aturan aplikasi. Acuan: [Laravel Socialite 13](https://laravel.com/framework/docs/13.x/socialite).

Setiap aksi server harus memeriksa hak akses, termasuk pemanggilan langsung aksi Livewire. Acuan: [Laravel Authorization 13](https://laravel.com/framework/docs/13.x/authorization) dan [Livewire 4 Security](https://livewire.laravel.com/docs/4.x/security). Rancangan policy, status, dan transaksi dalam paket ini adalah desain proyek sendiri.
