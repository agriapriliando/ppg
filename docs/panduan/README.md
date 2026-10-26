# Panduan penggunaan PMB PPG IAKN Palangka Raya

Panduan ini menjelaskan penggunaan aplikasi penerimaan PPG berdasarkan fitur pada kode aplikasi di `D:\project\ppg`, ditinjau pada **26 Oktober 2026**. Sasaran pembacanya adalah pendaftar, operator, administrator, dan petugas bantuan kampus.

Alamat seperti `/masuk` adalah bagian alamat setelah domain aplikasi. Contoh: jika pengelola menetapkan `http://ppg.test`, halaman masuknya adalah `http://ppg.test/masuk`. Gunakan domain resmi yang diberikan kampus. Seluruh jadwal layanan aplikasi menggunakan **WIB (Asia/Jakarta, UTC+7)**.

## Daftar panduan

| Dokumen | Isi dan pembaca |
| --- | --- |
| [01 — Pengenalan, akses, dan akun](01-pengenalan-akses-dan-akun.md) | Semua pengguna: hak akses, masuk, aktivasi, keamanan, profil, pengaitan Google |
| [02 — Panduan pendaftar](02-panduan-pendaftar.md) | Klaim SK, seluruh kolom formulir, penyimpanan draf, pengiriman, perbaikan, dan bukti |
| [03 — Panduan operator](03-panduan-operator.md) | Pemeriksaan klaim, WhatsApp, penyerahan akses, verifikasi dan keputusan formulir |
| [04 — Persiapan administrator](04-administrator-periode-referensi-pengguna.md) | Periode, jadwal, referensi, pernyataan, akun pengelola, dan informasi publik |
| [05 — Impor SK dan penugasan](05-impor-sk-dan-penugasan.md) | Format Excel, validasi, konfirmasi, pembagian awal, transfer dan penyeimbangan |
| [06 — Kasus, pemulihan, audit, dan ekspor](06-kasus-pemulihan-audit-dan-ekspor.md) | Penanganan kasus khusus, koreksi pemilik, penggantian nomor, CSV dan riwayat |
| [07 — Pemecahan masalah dan SOP](07-pemecahan-masalah-dan-sop.md) | Pesan kegagalan, langkah penanganan, daftar pemeriksaan harian, dan skenario latihan |

## Mulai sesuai peran

- **Pendaftar:** baca panduan 01 dan 02. Siapkan identitas sesuai SK, nomor WhatsApp aktif, biodata, alamat, serta perguruan tinggi dan program studi asal.
- **Operator:** baca panduan 01 dan 03. Pastikan administrator sudah mendaftarkan Anda pada periode dan menugaskan peserta.
- **Administrator:** mulai dari panduan 04, lanjutkan 05, kemudian 06. Gunakan panduan 07 sebagai daftar pemeriksaan operasional.

## Alur layanan dari awal sampai selesai

1. Administrator menyiapkan periode, akun operator, referensi formulir, pernyataan, dan kontak bantuan.
2. Administrator mengimpor daftar peserta SK resmi dan membagi penugasan operator.
3. Pendaftar masuk dengan Google atau mengajukan pencocokan melalui jalur WhatsApp tanpa akun.
4. Petugas mencocokkan identitas dengan satu entri SK dan menangani konflik jika ada.
5. Persetujuan klaim menghasilkan satu pendaftaran resmi dan nomor PMB. Untuk jalur WhatsApp, petugas menerbitkan dan menyerahkan akses sementara secara manual.
6. Pendaftar melengkapi formulir, memeriksa ringkasan, menyetujui pernyataan, lalu mengirim.
7. Operator memeriksa revisi terbaru. Jika perlu, pendaftar memperbaiki dan mengirim ulang dalam waktu yang diizinkan.
8. Operator menetapkan **Diterima lengkap** setelah seluruh syarat terpenuhi. Pendaftar dapat mencetak bukti pengiriman sesudah formulir dikirim.
9. Administrator meninjau kasus, audit, ekspor, dan kesiapan pengarsipan periode.

## Istilah yang perlu dibedakan

| Istilah | Arti dalam aplikasi |
| --- | --- |
| Peserta SK | Entri peserta dari daftar resmi kampus; dapat ada sebelum memiliki akun |
| Klaim/pencocokan SK | Permohonan agar identitas pemohon dikaitkan dengan entri SK yang tepat |
| Nomor referensi klaim | Referensi permohonan; disimpan saat pengajuan, berbeda dari nomor PMB |
| Draf pribadi | Isian yang disimpan pada akun dan periode; dapat dibuat sebelum klaim disetujui |
| Pendaftaran resmi | Catatan yang dibuat saat klaim disetujui; mempunyai nomor `PMB-<kode-periode>-<urutan>` |
| Diterima lengkap | Keputusan kelengkapan administrasi di aplikasi ini |
| Nomor PMB | Nomor pendaftaran; berbeda dari NIM |
| Revisi pengiriman | Nomor urutan pengiriman dan kirim ulang formulir |
| Kasus administratif | Catatan konflik yang memerlukan keputusan administrator |
| Tahan sementara | Kondisi kepesertaan yang membatasi pemrosesan sampai pemeriksaan selesai |

## Batas cakupan panduan

Nama menu, tombol utama, kolom, dan aturan di sini berasal dari rute, tampilan, pengendali, serta aturan bisnis aplikasi. Ini adalah panduan berdasarkan pemeriksaan kode, bukan laporan uji penggunaan langsung pada server produksi. Kontak, jadwal, pilihan referensi, dan isi pernyataan mengikuti konfigurasi kampus saat digunakan. Contoh dalam panduan bukan data resmi dan bukan akun yang dapat dipakai masuk.

Penyerahan akses WhatsApp dilakukan manual oleh petugas melalui percakapan resmi. Status PMB terpisah dari integrasi Neo Feeder; konfigurasi bawaan proyek menonaktifkan integrasi tersebut. Panduan ini tidak mengasumsikan adanya pengiriman WhatsApp otomatis, penerbitan NIM, atau fasilitas unggah berkas persyaratan pada formulir yang tersedia saat ditinjau.

## Sumber pemeliharaan dokumen

Saat fitur berubah, cocokkan panduan dengan `routes/web.php`, `resources/views`, `app/Actions/Admissions`, `app/Actions/Assignments`, `app/Actions/Imports`, `app/Support/ApplicantFields.php`, dan `config/pmb.php`. Rancangan lama dan prototipe di folder dokumentasi bukan acuan tunggal untuk perilaku aplikasi berjalan.
