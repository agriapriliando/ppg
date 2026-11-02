# 03 — Panduan operator

[Daftar panduan](README.md) · [Akses dan akun](01-pengenalan-akses-dan-akun.md)

## 1. Persiapan dan lingkup pekerjaan

1. Masuk menggunakan akun yang dibuat administrator.
2. Selesaikan kewajiban penggantian sandi awal.
3. Pastikan administrator mendaftarkan Anda pada periode yang benar dan membagikan peserta.
4. Buka **Peserta SK**, **Klaim & WhatsApp**, dan **Antrean Verifikasi** untuk melihat pekerjaan yang dapat diakses.
5. Jika daftar kosong padahal ada peserta yang seharusnya ditangani, konfirmasikan keanggotaan periode serta penugasan kepada administrator.

Operator memeriksa data dalam lingkup tugasnya. Administrator dapat memindahkan penugasan; sesudah itu akses operator lama terhadap peserta terkait dicabut. Klaim tanpa kandidat dapat didelegasikan administrator kepada satu operator periode untuk pemeriksaan awal.

## 2. Menemukan peserta SK

1. Pilih **Peserta SK** (`/peserta-sk`).
2. Pilih periode untuk mempersempit daftar.
3. Gunakan pencarian nama sesuai SK atau pengenal resmi.
4. Terapkan pencarian dan buka detail peserta yang tepat.
5. Periksa nomor SK, nomor urut, nama, periode, operator, serta kondisi kepesertaannya.

Nama bukan pembeda tunggal untuk peserta dengan nama sama. Cocokkan pengenal resmi, nomor SK dan nomor urut dengan bukti identitas yang sah. Jangan menyetujui hanya karena nama terlihat mirip.

## 3. Memeriksa klaim SK

1. Buka **Klaim & WhatsApp** (`/klaim`).
2. Pilih permohonan yang masih menunggu pemeriksaan atau membutuhkan informasi.
3. Pada detail, periksa nama pemohon, pengenal resmi, nomor WhatsApp, periode, jalur akses, kandidat SK, dan informasi tambahan pemohon.
4. Bandingkan identitas pemohon dengan satu entri SK yang benar.
5. Periksa apakah kandidat masih aktif, belum dimiliki pihak lain, dan tidak ditahan sementara.
6. Jika kandidat salah atau tidak ada, minta administrator melakukan pencocokan administratif. Penetapan kandidat merupakan kewenangan administrator.
7. Jika terdapat kasus administratif terbuka, eskalasikan agar administrator menyelesaikannya sebelum persetujuan.

### Meminta informasi atau mencatat hasil belum disetujui

Pada **Catatan tindak lanjut**, pilih **Perlu informasi tambahan** atau **Klaim belum dapat disetujui**. Tulis catatan 10–1.000 karakter, lalu klik **Simpan Catatan**.

Contoh catatan yang membantu:

> Nama yang sama muncul pada lebih dari satu entri. Mohon sebutkan pengenal peserta resmi serta nomor SK dan nomor urut agar kepesertaan dapat dicocokkan.

Pemohon yang sudah mempunyai akun dapat menjawab melalui halaman klaim sendiri. Untuk pengajuan publik tanpa akun, lanjutkan komunikasi melalui kanal resmi dengan menyebut referensi permohonan.

Status `declined` tidak dapat langsung disetujui melalui aturan persetujuan klaim saat ini. Gunakan hasil itu setelah pemeriksaan yang cukup; jika perlu diproses kembali, koordinasikan permohonan yang sesuai dengan administrator.

## 4. Nomor WhatsApp pada klaim

Detail klaim menampilkan nomor permohonan dan, setelah persetujuan, nomor formulir saat ini. Formulir bukti nomor WhatsApp aktif telah dihapus dari halaman ini. Persetujuan klaim dan penerbitan/penyerahan akses tidak memerlukan pencatatan bukti balasan. Pemeriksaan identitas, kepesertaan SK, kewenangan petugas, dan konflik administratif tetap berlaku.

## 5. Menyetujui klaim

1. Pastikan kandidat SK tepat, aktif, belum diklaim, dan tidak berkonflik.
2. Periksa nomor WhatsApp yang tercantum pada permohonan.
3. Pastikan kasus administratif yang menghambat sudah diselesaikan.
4. Centang **Identitas pemohon telah dicocokkan menggunakan pembeda yang sah**.
5. Centang **Kandidat SK tepat, tidak ambigu, dan belum dimiliki pihak lain**.
6. Klik **Setujui Klaim SK**.
7. Periksa hasil: status klaim disetujui, satu pendaftaran resmi dibuat, dan nomor PMB muncul.

Satu akun tidak boleh memiliki dua pendaftaran pada periode yang sama. Memilih kandidat saja belum mengunci kepesertaan; persetujuan memeriksa kembali apakah kandidat masih tersedia. Jika data berubah selama pemeriksaan, muat ulang dan periksa kembali sebelum melanjutkan.

Persetujuan klaim kedua jalur tidak mewajibkan bukti WhatsApp. Keputusan akhir **Diterima lengkap** tetap memerlukan verifikasi nomor formulir yang berlaku.

## 6. Menyerahkan akses lokal jalur WhatsApp

Bagian ini berlaku untuk klaim jalur WhatsApp yang sudah disetujui.

1. Buka detail klaim.
2. Pastikan nomor tujuan sesuai nomor formulir saat ini.
3. Pada **Penyerahan akses manual**, isi alasan penerbitan/pemulihan akses 10–1.000 karakter.
4. Klik **Generate Akun**.
5. Pada halaman akses akun, periksa nama, ID login, kata sandi yang tersedia, nomor tujuan, dan alamat masuk. Tombol menampilkan akses sebelumnya; akses awal hanya dibuat jika belum tersedia.
6. Serahkan akses secara manual melalui percakapan resmi pada **nomor tujuan yang ditampilkan**.
7. Setelah benar-benar diserahkan, centang konfirmasi penyerahan.
8. Klik **Catat Sudah Diserahkan**.
9. Minta pendaftar masuk dengan sandi awal; akun langsung aktif tanpa kewajiban membuat sandi baru.

Username akun pendaftar baru dibuat dari nama, misalnya `budi.santoso`. Jika sudah dipakai, sistem menambahkan nomor berurutan: `budi.santoso2`, `budi.santoso3`, dan seterusnya.

Membuka kembali **Generate Akun** tidak mengganti kata sandi, mencabut akses, atau menghapus sesi. Sandi awal yang dibuat setelah pembaruan fitur disimpan terenkripsi dan dapat ditampilkan kembali kepada petugas yang berwenang. Sandi lama yang hanya tersimpan sebagai hash, atau sandi yang sudah diubah pemilik, tidak dapat ditampilkan kembali. Halaman tetap menampilkan informasi akun tanpa mereset sandinya. Sandi awal belum dapat digunakan untuk login pertama bila penyerahannya belum dicatat atau batas masuk pertamanya sudah berakhir. Untuk mengganti password akun secara sengaja, klik **Generate Ulang Password** pada detail klaim. Tindakan ini mencabut password dan sesi lama, menampilkan sandi awal baru, dan perlu dicatat penyerahannya. Login dengan sandi baru langsung mengaktifkan akun tanpa kewajiban mengganti sandi lagi. Username tetap sama. Pemulihan nomor WhatsApp tetap melalui proses pemulihan nomor yang terpisah.

Jangan menaruh sandi sementara pada catatan klaim, audit, atau dokumen bersama. Penerbitan akses bukan pengiriman WhatsApp otomatis.

## 7. Memproses antrean formulir

1. Buka **Antrean Verifikasi** (`/antrean`).
2. Pilih periode dan status yang ingin dikerjakan.
3. Cari menggunakan nomor PMB atau nama sesuai SK jika diperlukan.
4. Buka detail pendaftaran.
5. Perhatikan nomor revisi, status, kondisi kepesertaan, biodata, pendidikan asal, dan status WhatsApp.
6. Baca riwayat pengiriman dan pemeriksaan agar keputusan didasarkan pada revisi terbaru.

### 7.1 Menandai Diverifikasi

Hanya tersedia dari status **Terkirim**.

1. Periksa identitas peserta terhadap dokumen yang sah.
2. Pastikan SK dan kepesertaan tepat serta tidak berkonflik.
3. Periksa seluruh biodata dan pendidikan asal.
4. Centang tiga pemeriksaan: identitas, kepesertaan SK, serta konsistensi biodata dan pendidikan.
5. Klik **Tandai Diverifikasi**.
6. Pastikan status menjadi **Diverifikasi**.

Aplikasi memeriksa kembali kelengkapan kolom dan validitas referensi. Hasil yang tersimpan pada revisi lama tidak cukup untuk memutus revisi baru.

### 7.2 Meminta perbaikan

Tersedia dari status **Terkirim** atau **Diverifikasi**.

1. Pilih minimal satu kelompok: **Identitas**, **Alamat**, **Tambahan**, **Pendidikan asal**, atau **Kontak WhatsApp**.
2. Isi catatan spesifik 10–1.000 karakter.
3. Klik **Minta Perbaikan**.
4. Pastikan status menjadi **Perlu perbaikan**.
5. Beri tahu pendaftar agar memperbaiki dan **mengirim ulang**, bukan hanya menyimpan draf.

Contoh catatan:

> Pilih ulang program studi sesuai perguruan tinggi pada ijazah. Periksa kembali hasil pilihan pada tahap Pendidikan, lalu tinjau dan kirim ulang formulir.

Periksa batas waktu perbaikan sebelum mengarahkan kirim ulang. Operator tidak mengubah jadwal; eskalasikan kebutuhan perubahan waktu kepada administrator.

### 7.3 Menetapkan Diterima lengkap

Hanya tersedia dari status **Diverifikasi**. Pastikan:

- Akun pemilik saat ini sudah mengirim formulir.
- Pemeriksaan verifikasi sesuai revisi terbaru sudah tersimpan.
- Data tetap lengkap dan sah terhadap referensi yang diperiksa sistem.
- Nomor WhatsApp formulir saat ini terverifikasi.
- Peserta SK aktif, dimiliki akun yang tepat, dan tidak ditahan sementara.
- Tidak ada kasus administratif pendaftaran yang masih terbuka.

Klik **Tetapkan Diterima Lengkap** dan periksa status serta riwayat keputusan. Jika tombol belum aktif karena WhatsApp, gunakan **Periksa Klaim & Bukti WhatsApp** untuk memverifikasi nomor saat ini.

Operator tidak dapat membuka kembali status Diterima lengkap. Administrator dapat membukanya dengan alasan dan kelompok data yang perlu diperbaiki.

## 8. Riwayat dan serah terima pekerjaan

Gunakan **Riwayat Aktivitas** (`/riwayat`) untuk meninjau aktivitas akun sendiri. Cari kata tindakan jika diperlukan. Riwayat pemeriksaan per pendaftaran berada pada detail pendaftaran.

Saat menyerahkan pekerjaan, sampaikan periode, nomor PMB/referensi klaim, status terakhir, pekerjaan yang tertunda, dan alasan eskalasi. Jika operator diganti, administrator harus memindahkan penugasannya dalam aplikasi agar akses sesuai tanggung jawab baru.
