# 06 — Kasus administratif, pemulihan, audit, dan ekspor

[Daftar panduan](README.md) · [Panduan operator](03-panduan-operator.md)

## 1. Mencocokkan kandidat dan mendelegasikan klaim ambigu

Jika klaim tidak mempunyai kandidat atau kandidat perlu diperiksa ulang, administrator membuka **Klaim & WhatsApp**, lalu detail klaim.

Untuk menentukan kandidat:

1. Pada **Pencocokan administratif**, pilih entri resmi pada periode yang sama.
2. Cocokkan nama, nomor SK, nomor urut, dan pembeda identitas.
3. Isi **Dasar pencocokan** sepanjang 10–1.000 karakter.
4. Klik **Simpan Kandidat**.
5. Lanjutkan pemeriksaan dan persetujuan klaim sesuai [panduan operator](03-panduan-operator.md).

Daftar kandidat menampilkan maksimal 1.000 entri yang belum diklaim. Bila kandidat yang benar tidak tersedia, periksa apakah sudah dimiliki akun lain atau berada di luar daftar yang ditampilkan, lalu tindak lanjuti melalui pemeriksaan administratif. Penetapan kandidat tidak otomatis menyetujui atau mengunci entri SK.

Untuk menugaskan pemeriksaan awal klaim tanpa kandidat:

1. Pastikan klaim masih menunggu atau perlu informasi, belum memiliki kandidat, dan periode belum arsip.
2. Pada **Penanggung jawab klaim**, pilih operator pemeriksa.
3. Operator harus aktif, terdaftar pada periode, dan sudah menyelesaikan kewajiban ganti sandi.
4. Isi alasan penugasan 10–1.000 karakter.
5. Klik **Simpan Penanggung Jawab**.

Delegasi memberi tanggung jawab pemeriksaan klaim ambigu; penetapan kandidat tetap dilakukan administrator.

## 2. Mengelola kondisi administratif peserta

Buka **Peserta SK**, pilih peserta, lalu bagian **Kondisi administratif kepesertaan**.

1. Pilih **Daftar resmi**: Aktif atau Ditarik dari kepesertaan.
2. Pilih **Pemeriksaan administratif**: Tidak ditahan atau Tahan pengiriman dan keputusan lengkap.
3. Isi dasar resmi/alasan 20–1.000 karakter.
4. Klik **Simpan Kondisi Kepesertaan**.
5. Periksa hasil dan beritahukan tindak lanjut kepada operator terkait.

Penahanan dan penarikan mempertahankan riwayat pendaftaran. Keduanya dapat menghambat pengubahan/pengiriman pendaftar dan keputusan lengkap sesuai aturan proses. Bukti atau keputusan lama tidak menggantikan keputusan resmi mengenai kondisi kepesertaan saat ini. Untuk melepas penahanan, pilih Tidak ditahan dengan alasan hasil pemeriksaan yang jelas.

## 3. Menyelesaikan kasus administratif

Nomor WhatsApp yang dipakai pada beberapa permohonan/formulir dapat membuat kasus administratif. Buka **Kasus Administratif** (`/admin/kasus`).

1. Temukan kasus yang masih terbuka dan periksa kaitan klaim/pendaftarannya.
2. Telusuri data terkait sebelum mengambil keputusan, misalnya apakah penggunaan nomor bersama sah dan siapa pemilik sebenarnya.
3. Isi hasil penyelesaian sepanjang **20–2.000 karakter**.
4. Centang konfirmasi yang diminta lalu jalankan tindakan penyelesaian.
5. Pastikan kasus berubah menjadi selesai.
6. Kembali ke klaim atau formulir untuk meneruskan pemeriksaan yang sebelumnya terhambat.

Menyelesaikan kasus hanya mencatat hasil pemeriksaan. Tindakan itu tidak otomatis menyetujui klaim, memverifikasi formulir, melepas penahanan peserta, atau menetapkan Diterima lengkap. Jalankan setiap tindak lanjut yang memang diperlukan.

## 4. Memulihkan akses jika nomor tetap sama

Untuk pendaftar jalur WhatsApp yang lupa sandi tetapi masih dapat membalas dari nomor yang sama:

1. Operator memeriksa kembali identitas dan nomor aktif pada klaim yang disetujui.
2. Catat verifikasi nomor formulir saat ini jika diperlukan.
3. Terbitkan akses sekali tampil dengan alasan pemulihan.
4. Serahkan manual ke nomor terverifikasi dan **Catat Sudah Diserahkan**.
5. Pendaftar masuk dengan akses baru lalu membuat sandi pribadi.

Penerbitan baru mencabut sesi dan akses sementara sebelumnya. Akun yang dinonaktifkan memerlukan penanganan administrator; penerbitan ulang akses tidak mengaktifkan akun tersebut secara otomatis.

## 5. Memulihkan nomor lama yang hilang

Proses ini khusus administrator, untuk **pemilik akun yang sama** pada klaim jalur WhatsApp yang sudah disetujui. Jika masalahnya salah pemilik, gunakan koreksi kepemilikan pada bagian 6.

### Prasyarat

- Periode belum diarsipkan.
- Identitas pemilik dan kaitan ke akun/SK sudah diperiksa.
- Kepesertaan aktif, pemilik konsisten, dan tidak ditahan sementara.
- Akun tidak dinonaktifkan.
- Kasus administratif terkait yang terbuka sudah diselesaikan.
- Nomor baru berbeda dari nomor formulir saat ini dan tidak berkonflik dengan data pihak lain.
- Balasan dari nomor baru sudah diterima melalui kanal resmi.

### Langkah pemulihan

1. Buka detail klaim yang sudah disetujui.
2. Klik **Pemulihan Saat Nomor Lama Hilang**.
3. Periksa nomor PMB, ID login pemilik, nomor formulir saat ini, dan identitas SK.
4. Isi **Nomor WhatsApp baru** dan **Ulangi nomor baru** dengan penulisan yang sama.
5. Isi alasan pemulihan serta dasar pemeriksaan/bukti balasan; masing-masing 20–1.000 karakter.
6. Centang tiga konfirmasi: identitas pemilik sama, balasan nomor baru sudah diperiksa, dan persetujuan pencabutan akses lama.
7. Klik **Simpan Pemulihan & Cabut Akses Lama**.
8. Kembali ke klaim, terbitkan akses sementara baru, serahkan ke nomor baru, dan catat penyerahan.
9. Minta pendaftar aktivasi ulang, meninjau nomor baru, lalu mengirim ulang formulir jika sebelumnya sudah pernah dikirim.
10. Operator memeriksa kembali revisi baru sebelum keputusan lengkap.

Proses ini mengganti nomor formulir dan mencatat verifikasi nomor baru berdasarkan pemeriksaan administrator. Verifikasi lama, sandi lokal lama, akses sementara, dan sesi dicabut. Akun menunggu aktivasi. Formulir yang pernah dikirim kembali ke Perlu perbaikan dan keputusan lengkap sebelumnya dibuka kembali. Tanggal daftar pertama tetap dipertahankan; nomor pada klaim awal tetap menjadi catatan permohonan awal.

Jika pengiriman ulang terhambat karena jadwal, administrator perlu meninjau jendela perbaikan sesuai kebijakan resmi.

## 6. Mengoreksi kepemilikan pendaftaran yang salah akun

Ini memindahkan kaitan pendaftaran ke pemilik sah yang berbeda. Prosedurnya menggunakan klaim terbuka dari pihak tujuan, bukan mengubah nama akun lama.

### Persiapan

1. Periksa identitas dan bukti kedua pihak melalui prosedur resmi.
2. Pastikan periode belum diarsipkan dan peserta SK masih aktif.
3. Pada detail peserta SK, **tahan sementara kepesertaan** dengan alasan pemeriksaan.
4. Pastikan ada klaim tujuan pada periode yang sama dengan status menunggu/perlu informasi. Kandidatnya harus SK yang sama atau belum ditentukan.
5. Untuk akun tujuan yang sudah ada, pastikan pendaftar aktif, sudah menyelesaikan ganti sandi, dan dapat membuktikan akses akun.
6. Untuk tujuan jalur WhatsApp tanpa akun, catat bukti balasan nomor klaim sebelum koreksi.
7. Pastikan persetujuan pemeriksaan klaim tujuan tersedia dan akun tujuan belum mempunyai pendaftaran pada periode tersebut.
8. Selesaikan kasus administratif lain yang masih terbuka pada pendaftaran atau klaim terkait.

### Langkah koreksi

1. Buka detail pendaftaran melalui Antrean Verifikasi.
2. Klik **Koreksi Kepemilikan Akun**.
3. Periksa akun terikat saat ini, SK, status pendaftaran, dan kondisi tahan sementara.
4. Pada daftar **Klaim akun tujuan**, klik **Tinjau Akun Tujuan** yang tepat.
5. Periksa nama, ID/email, nomor WhatsApp, referensi klaim, dan keterangan draf tujuan.
6. Isi **Alasan koreksi** dan **Rujukan bukti dan hasil pemeriksaan kedua pihak**, masing-masing 20–1.000 karakter.
7. Centang empat konfirmasi mengenai akun lama, pemilik sah, akses/persetujuan tujuan, serta pemahaman perlindungan data dan masuk ulang.
8. Klik **Konfirmasi Koreksi Kepemilikan**.
9. Periksa riwayat koreksi, akun pemilik baru, dan status pendaftaran.
10. Untuk akun WhatsApp baru yang menunggu aktivasi, terbitkan dan serahkan akses dari klaim tujuan.
11. Minta pemilik sah masuk ulang, melengkapi formulir miliknya sendiri, dan mengirim sebelum pemeriksaan berikutnya.

### Hasil yang perlu diperiksa

- Nomor PMB dan tanggal pengiriman pertama dipertahankan.
- Kepesertaan terhubung ke akun tujuan; penahanan dilepas setelah pemeriksaan koreksi berhasil.
- Klaim tujuan disetujui, sedangkan klaim lama yang terkait dibatalkan.
- Draf, biodata, persetujuan, dan kiriman lama tetap melekat pada riwayat penulisnya; tidak disalin menjadi biodata pemilik baru.
- Pendaftaran memakai draf akun tujuan, atau ruang isian baru jika belum ada.
- Status menjadi Draf bila belum pernah dikirim, atau Perlu perbaikan bila sudah pernah dikirim. Keputusan lengkap sebelumnya dicabut.
- Sesi serta sandi sementara kedua akun dicabut; sandi pribadi masing-masing akun tetap miliknya.
- Pemilik saat ini wajib mengirim formulir sebelum diperiksa dan sebelum dapat memperoleh bukti baru.

Jika salah satu draf berubah saat pratinjau dibuka, muat ulang dan tinjau lagi. Koreksi tidak menggabungkan dua pendaftaran yang sudah ada.

## 7. Membuka kembali status Diterima lengkap

1. Administrator membuka detail pendaftaran berstatus Diterima lengkap.
2. Pilih minimal satu kelompok data yang perlu diperbaiki.
3. Isi catatan/alasan spesifik 10–1.000 karakter.
4. Klik **Buka Kembali dengan Alasan di Atas**.
5. Pastikan status menjadi Perlu perbaikan.
6. Pastikan jadwal memungkinkan kirim ulang dan sampaikan tindak lanjut kepada pendaftar/operator.

Keputusan lengkap harus ditetapkan kembali setelah pengiriman dan pemeriksaan revisi baru. Periode yang sudah diarsipkan tidak menerima tindakan ini.

## 8. Melihat audit dan konfigurasi efektif

### Audit Aktivitas

Buka **Audit Aktivitas** (`/admin/audit`). Gunakan pencarian berdasarkan teks tindakan, lalu periksa waktu, pelaku, objek, dan alasan yang tersedia. Administrator melihat lingkup audit pengelolaan; operator melalui Riwayat Aktivitas hanya melihat aktivitas sendiri.

Gunakan audit saat menelusuri perubahan periode, penerbitan akses, penyerahan, transfer tugas, keputusan, pengunduhan ekspor, dan koreksi khusus. Catatan audit merupakan riwayat tindakan; baca juga catatan pemeriksaan pada objek terkait untuk memahami konteksnya.

### Riwayat Konfigurasi

1. Buka **Riwayat Konfigurasi** (`/admin/riwayat-konfigurasi`).
2. Pilih periode lalu klik **Tampilkan Riwayat**.
3. Periksa versi, waktu pencatatan, jumlah snapshot awal, jumlah pengiriman, dataset, dan versi pernyataan.
4. Buka **Jadwal dan rincian konfigurasi tersimpan** untuk membaca jadwal, zona waktu, versi formulir, dan naskah pada versi tersebut.

Konfigurasi efektif dicatat saat formulir dikirim; belum ada riwayat bukan berarti periode belum dibuat. Snapshot awal dipertahankan, sedangkan kirim ulang mencatat konfigurasi yang dipakai saat itu. Rincian jadwal pada bagian tersebut diberi label **UTC**; perhatikan konversi ke WIB saat membandingkan. Pencatatan otomatis bukan pengesahan kebijakan kampus.

## 9. Menyiapkan dan mengunduh ekspor peserta

1. Buka **Ekspor Peserta** (`/admin/ekspor`).
2. Atur filter periode, operator, dan/atau status pendaftaran. Filter kosong memperluas hasil.
3. Ajukan ekspor melalui formulir dan tunggu proses antrean.
4. Muat ulang untuk memeriksa status hasil.
5. Setelah siap, gunakan tautan unduh.
6. Periksa jumlah baris dan filter sebelum meneruskan berkas kepada pihak yang berwenang.

Ekspor mengambil **seluruh hasil filter**, termasuk yang tidak tampak pada halaman tabel saat ini. Formatnya CSV dengan kolom:

| Kolom | Isi |
| --- | --- |
| Nomor PMB | Nomor pendaftaran bila sudah ada |
| Pengenal SK | Pengenal resmi peserta |
| Nama sesuai SK | Nama dari daftar resmi |
| Periode | Nama periode |
| Status pendaftaran | Status saat ekspor, atau Belum mendaftar |
| Operator | Nama operator yang ditugaskan |
| Status WhatsApp | Terverifikasi atau Belum diperiksa |

Tanpa filter status, peserta yang belum mempunyai pendaftaran dapat ikut hasil. Ekspor ini bukan salinan semua kolom biodata. Jika dibuka di aplikasi spreadsheet, impor pengenal dan nomor sebagai teks agar nol awal tetap utuh.

Berkas siap berlaku **satu hari**. Unduhan dapat ditolak bila berkas belum siap, kedaluwarsa, hilang, atau cakupan/penugasan telah berubah; buat ekspor baru. Status gagal perlu pemeriksaan petugas teknis atau pengajuan baru setelah penyebabnya diselesaikan. Unduhan dicatat dalam audit dan berkas kedaluwarsa dibersihkan oleh tugas terjadwal.
