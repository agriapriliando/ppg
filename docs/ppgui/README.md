# Prototipe tampilan PMB PPG IAKN Palangka Raya

Tampilan PHP, Bootstrap, dan Alpine.js dengan desain mengacu pada `index.php` dan alur pada `rancangan-aplikasi-pmb-ppg-iakn-palangka-raya.md`.

## Menjalankan

Buka proyek melalui Laragon, misalnya `http://localhost/ppg/`. Alternatif dari folder ini:

```powershell
php -S 127.0.0.1:8765
```

Lalu buka `http://127.0.0.1:8765/`.

## Halaman dan navigasi

- `index.php`: beranda, pencarian contoh SK, jadwal, FAQ, dan modal klaim WhatsApp.
- `login.php`: pilihan Google demo, kredensial lokal, portal pengelola, ganti sandi pertama, dan panduan pemulihan.
- `klaim.php`: pengajuan klaim publik tanpa Gmail dan konfirmasi permohonan.
- `panduan.php`: alur tiga peran dan tautan masuk tiap ruang kerja.
- `portal.php?role=pendaftar`: dashboard, kepesertaan SK, formulir lima tahap, status/perbaikan, bukti cetak, akun, bantuan.
- `portal.php?role=operator`: dashboard, antrean, detail pemeriksaan, klaim/WhatsApp, peserta penugasan, aktivitas, akun.
- `portal.php?role=admin`: ringkasan, daftar SK, impor/pratinjau, pembagian/perpindahan operator, verifikasi, pengguna, periode, referensi, Neo, audit/ekspor, pengaturan.

Gunakan pilihan **Lihat sebagai** di bagian atas ruang kerja untuk berganti peran. Tombol panah melingkar membuka konfirmasi untuk mengulang data contoh.

## Mencoba satu alur lengkap

1. Sebagai pendaftar, buka **Kepesertaan SK** dan ajukan pencocokan.
2. Buka **Formulir Pendaftaran**, tekan **Isi data contoh**, dan simpan draf.
3. Beralih ke operator, buka **Klaim & WhatsApp**, periksa dan setujui klaim Yohanes.
4. Kembali ke pendaftar, tinjau lima tahap formulir, centang pernyataan, dan kirim.
5. Di antrean operator, periksa Yohanes. Coba permintaan perbaikan beserta catatan.
6. Sebagai pendaftar, lakukan perbaikan dan kirim ulang. Tanggal pengiriman pertama tetap.
7. Operator mencentang pemeriksaan, menandai diverifikasi, mencatat bukti WhatsApp, lalu menetapkan diterima lengkap.
8. Pendaftar dapat mencetak bukti; administrator dapat mencoba pratinjau Neo dan simulasi sinkronisasi terpisah.

Alur WhatsApp juga memiliki simulasi pencatatan balasan, persetujuan SK, dan penyerahan kredensial manual. Pada halaman impor, gunakan **Gunakan berkas contoh** untuk meninjau simulasi tanpa menyediakan Excel.

## Batas prototipe

Seluruh data dan perubahan simulasi disimpan di `localStorage` browser dengan kunci `ppg-ui-demo-v1`. Tidak ada autentikasi, database, pembacaan isi Excel, pesan WhatsApp, atau koneksi Google/Neo yang aktif. Pemilih peran tersedia untuk peninjauan tampilan dan bukan mekanisme otorisasi. Jangan gunakan identitas asli.

Pemilihan `.xlsx` hanya memperagakan alur unggah; pratinjau memakai 400 baris contoh tetap. Ekspor CSV berisi sampel tabel yang sedang difilter. Bukti cetak bertanda dokumen contoh. Jadwal, nomor SK, referensi, dan kontak merupakan contoh dan perlu ditetapkan pengelola sebelum implementasi produksi.

Bootstrap, Alpine, dan ikon disediakan lokal dalam `assets/vendor`. Font Google bersifat tambahan dan memiliki fallback sistem.

## Pemeriksaan

Validasi sintaks PHP: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`.

Validasi JavaScript: `node --check assets/js/app.js` dan `node --check assets/js/portal.js`.
