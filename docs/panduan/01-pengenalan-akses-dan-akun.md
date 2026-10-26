# 01 — Pengenalan, akses, dan akun

[Daftar panduan](README.md) · [Berikutnya: pendaftar](02-panduan-pendaftar.md)

## 1. Persiapan sebelum masuk

1. Gunakan peramban yang mendukung JavaScript dan koneksi internet yang stabil.
2. Buka domain aplikasi yang diberikan kampus. Halaman awal berada di `/`.
3. Baca periode yang ditampilkan, jadwal WIB, pengumuman, dan kontak bantuan.
4. Pilih cara akses yang sesuai. Pendaftar dapat menggunakan Google atau memperoleh akses lokal dari operator setelah klaim WhatsApp disetujui. Akun operator dan administrator dibuat oleh administrator.
5. Pada ponsel, gunakan tombol buka menu di bagian atas untuk menampilkan navigasi ruang kerja.

Jangan menggunakan akun Google milik operator, kerabat, atau peserta lain untuk mendaftarkan diri. Satu akun pendaftar hanya dapat mempunyai satu pendaftaran resmi pada periode yang sama.

## 2. Peta menu dan kewenangan

| Menu/halaman | Pendaftar | Operator | Administrator |
| --- | --- | --- | --- |
| Portal publik, Panduan/Pusat Bantuan | Ya | Ya | Ya |
| Beranda ruang kerja | Data sendiri | Lingkup tugas | Lingkup pengelolaan |
| Kepesertaan SK | Klaim sendiri | Melalui Klaim & WhatsApp | Melalui Klaim & WhatsApp |
| Pendaftaran Saya | Milik sendiri | Tidak sebagai pendaftar | Tidak sebagai pendaftar |
| Peserta SK | Tidak | Sesuai penugasan | Seluruh lingkup administrator |
| Klaim & WhatsApp | Tidak | Sesuai penugasan/delegasi | Ya |
| Antrean Verifikasi | Tidak | Sesuai penugasan | Ya |
| Riwayat Aktivitas | Tidak | Aktivitas sendiri | Melalui Audit Aktivitas |
| Impor SK, Pembagian Operator, Periode & Gelombang | Tidak | Tidak | Ya |
| Referensi & Pernyataan, Kelola Pengguna | Tidak | Tidak | Ya |
| Riwayat Konfigurasi, Kasus Administratif | Tidak | Tidak | Ya |
| Audit Aktivitas, Ekspor Peserta, Pengaturan Aplikasi | Tidak | Tidak | Ya |
| Akun & Keamanan, Keluar Akun | Ya | Ya | Ya |

Menu yang tidak muncul biasanya berkaitan dengan peran akun. Akses operator mengikuti penugasan peserta dan keanggotaan periode. Sesudah transfer tugas, tautan lama dapat tidak bisa dibuka lagi.

## 3. Masuk dengan Google untuk pendaftar

1. Buka `/masuk`.
2. Pilih akses Google pada halaman masuk.
3. Pilih akun Google pribadi dengan alamat email terverifikasi.
4. Selesaikan persetujuan pada halaman Google.
5. Setelah kembali ke aplikasi, pastikan nama dan label peran **Pendaftar** sesuai.
6. Buka **Kepesertaan SK** dan ajukan pencocokan sebelum mengirim formulir resmi.

Pada penggunaan pertama, identitas Google yang belum terhubung dapat membuat akun pendaftar. Masuk dengan Google belum berarti klaim SK disetujui. Jika email bertabrakan dengan akun lokal yang sudah ada, ikuti proses pengaitan atau hubungi pengelola; jangan membuat akun kedua untuk memintas konflik.

Jika akses Google belum tersedia, pengelola perlu memeriksa konfigurasi layanan. Gunakan jalur lokal yang telah diberikan atau layanan pencocokan WhatsApp.

## 4. Masuk dengan ID login atau email

1. Buka `/masuk`.
2. Isi **ID login atau email** sesuai akses yang diberikan.
3. Isi kata sandi dengan benar; perhatikan huruf besar, huruf kecil, dan karakter khusus.
4. Kirim formulir masuk.
5. Ikuti halaman aktivasi atau penggantian sandi jika aplikasi mengarahkannya.
6. Setelah berhasil, ruang kerja ada di `/ruang-kerja`.

ID login dan email dinormalisasi menjadi huruf kecil saat masuk. Kata sandi tetap harus cocok persis. Bila percobaan masuk dibatasi, tunggu dan ikuti pesan yang tampil sebelum mencoba kembali.

## 5. Aktivasi akun jalur WhatsApp

Operator menyerahkan alamat masuk, ID login, sandi sementara, dan batas berlakunya melalui nomor yang sudah diverifikasi.

1. Pastikan pesan berasal dari petugas resmi.
2. Masuk menggunakan ID login dan sandi sementara tersebut.
3. Aplikasi membuka `/aktivasi-sandi` untuk membuat sandi pribadi.
4. Isi sandi baru minimal **12 karakter**, lalu ulangi pada konfirmasi.
5. Selesaikan aktivasi sampai muncul ruang kerja dan informasi akun aktif.
6. Untuk masuk berikutnya, gunakan sandi baru.

Sandi sementara hanya dapat dipakai jika petugas sudah mencatat penyerahannya, belum kedaluwarsa, belum dicabut, dan belum digunakan. Nilai bawaan masa berlaku adalah **1.440 menit/24 jam**, tetapi pengelola dapat mengubahnya; ikuti waktu kedaluwarsa yang diserahkan petugas. Sesi aktivasi memiliki batas bawaan **10 menit**. Jika sesi aktivasi berakhir, hubungi operator untuk penyerahan akses baru.

## 6. Login pertama akun pengelola

1. Administrator membuat akun operator/administrator dengan sandi awal minimal 12 karakter.
2. Pengguna masuk dengan ID login atau email akun itu.
3. Jika diwajibkan mengganti sandi, isi sandi saat ini, sandi baru, dan konfirmasi.
4. Gunakan sandi baru yang berbeda, minimal 12 karakter.
5. Setelah tersimpan, lanjutkan pekerjaan sesuai peran.

Operator perlu menyelesaikan kewajiban ganti sandi sebelum dapat dipilih pada beberapa proses, termasuk delegasi klaim ambigu dan penugasan massal.

## 7. Mengubah nama tampilan dan kata sandi

Buka **Akun & Keamanan** (`/akun`). Perbarui nama tampilan dan simpan. Perubahan ini hanya memperbarui nama akun; nama pada SK dan nama lengkap biodata dikelola terpisah.

Untuk mengganti sandi:

1. Buka `/akun/keamanan` melalui menu akun.
2. Masukkan sandi saat ini.
3. Isi sandi baru yang berbeda, minimal 12 karakter, maksimal 255 karakter.
4. Ulangi sandi baru pada konfirmasi dan simpan.
5. Bila perangkat lain diminta masuk kembali, gunakan sandi baru; proses ini mencabut sesi yang tersimpan di basis data.

Akun Google tanpa sandi lokal tidak dapat mengganti sandi dengan formulir yang mensyaratkan sandi saat ini. Hubungi layanan resmi jika memerlukan penanganan akses.

## 8. Mengaitkan Google ke akun lokal yang sudah ada

1. Masuk dengan akun lokal yang benar.
2. Buka **Akun & Keamanan**.
3. Pada bagian pengaitan Google, konfirmasikan sandi lokal saat ini.
4. Lanjutkan ke Google dan pilih identitas pribadi yang akan dikaitkan.
5. Selesaikan proses dalam batas waktu pengaitan **5 menit**.
6. Pastikan aplikasi menampilkan bahwa Google berhasil dikaitkan.

Pengaitan mempertahankan pendaftaran dan penugasan pada akun yang sama. Identitas Google yang sudah terikat pada akun lain atau berbenturan dengan email pengguna lain akan ditolak. Mulai pengaitan dari halaman akun, bukan langsung dari alamat callback Google.

## 9. Keluar dan meminta bantuan

Gunakan **Keluar Akun** pada bagian bawah menu sesudah selesai, terutama di komputer bersama. Jika akun dinonaktifkan, hubungi administrator melalui kontak resmi yang tampil pada portal.

Untuk bantuan, siapkan nomor PMB atau referensi klaim, nama periode, pesan kesalahan, dan waktu kejadian. Jangan menyerahkan sandi pribadi, sandi sementara, atau NIK lengkap pada catatan bantuan umum.
