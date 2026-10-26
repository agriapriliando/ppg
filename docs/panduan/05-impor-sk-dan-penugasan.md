# 05 — Impor SK dan penugasan operator

[Daftar panduan](README.md) · [Persiapan administrator](04-administrator-periode-referensi-pengguna.md)

## 1. Prasyarat impor

Proses ini hanya tersedia untuk administrator. Siapkan periode yang belum diarsipkan, daftar SK resmi, dan berkas Excel yang mengikuti template aplikasi. Jika kolom operator akan diisi, operator harus aktif, memiliki email yang benar, dan sudah terdaftar pada periode tujuan.

Impor terdiri dari **unggah → validasi → pratinjau → konfirmasi**. Unggah saja belum menambahkan peserta ke daftar resmi aplikasi.

## 2. Mengunduh dan mengisi template

1. Buka **Impor SK** (`/admin/impor`).
2. Unduh template resmi melalui tautan template (`/admin/template-sk`).
3. Buka berkas `.xlsx` dan gunakan sheet **Peserta_SK** untuk data.
4. Baca sheet **Panduan**.
5. Pertahankan nama sheet, header, dan urutan kolom.
6. Isi satu periode per berkas, maksimal **5.000 baris data** dan delapan kolom.
7. Simpan sebagai `.xlsx`, maksimal **5.120 KB**.

### Struktur kolom

| Kolom | Header persis | Wajib | Cara pengisian |
| --- | --- | --- | --- |
| A | `nomor_sk` | Ya | Nomor SK resmi, maksimal 150 karakter |
| B | `tanggal_sk` | Ya | Tanggal `YYYY-MM-DD`; satu nomor SK harus konsisten tanggalnya |
| C | `periode` | Ya | Kode periode tujuan, bukan nama tampilan |
| D | `nomor_urut_sk` | Ya | Bilangan bulat positif, maksimal 2.147.483.647 |
| E | `nama_sesuai_sk` | Ya | Nama resmi, maksimal 255 karakter |
| F | `id_peserta_sk` | Tidak | Simpan sebagai **teks** agar nol awal tetap utuh; maksimal 100 karakter |
| G | `operator_email` | Tidak | Email operator aktif yang terdaftar pada periode |
| H | `catatan_internal` | Tidak | Catatan administratif, maksimal 1.000 karakter |

Contoh latihan, bukan data resmi:

| nomor_sk | tanggal_sk | periode | nomor_urut_sk | nama_sesuai_sk | id_peserta_sk | operator_email | catatan_internal |
| --- | --- | --- | --- | --- | --- | --- | --- |
| SK-CONTOH/2026 | 2026-10-01 | PPG-2026-G1 | 1 | Peserta Contoh A | 000001 | | Data latihan |

Jangan memasukkan formula, macro, atau tautan eksternal. Salin **nilai** jika sumber berasal dari workbook berformula. Pengenal resmi yang terlihat memiliki nol awal tetapi disimpan sebagai angka dapat ditolak; ubah tipe sel menjadi teks sebelum mengisi pengenal dari sumber resmi.

## 3. Mengunggah dan memantau validasi

1. Pilih periode tujuan pada **Impor SK**.
2. Pilih berkas `.xlsx` yang sudah diperiksa.
3. Kirim unggahan melalui formulir.
4. Pastikan aplikasi membuka pratinjau dan memberitahukan berkas masuk antrean validasi.
5. Jika masih menunggu, klik **Perbarui Hasil** untuk membaca status terbaru.
6. Periksa ringkasan jumlah Baru, Identik, Perubahan, Konflik, dan Kesalahan.
7. Baca semua halaman rincian; tabel pratinjau menampilkan 50 baris per halaman.

Validasi berjalan melalui antrean pekerjaan. Jika status tidak bergerak, petugas teknis perlu memeriksa worker antrean sebelum Anda mengunggah berulang kali.

### Status proses impor

| Status | Makna dan tindakan |
| --- | --- |
| `uploaded` / `validating` | Menunggu atau sedang divalidasi; perbarui hasil |
| `ready` | Pratinjau siap, tetapi belum diterapkan ke peserta resmi |
| `invalid` | Ada kesalahan/konflik; perbaiki sumber dan unggah ulang |
| `failed` | Berkas tidak dapat divalidasi; baca ringkasan dan laporan |
| `committed` | Konfirmasi selesai dan hasil diterapkan |
| `expired` | Pratinjau telah kedaluwarsa/dibersihkan; buat validasi baru |

## 4. Membaca hasil per baris

| Hasil | Makna |
| --- | --- |
| Baru | Entri belum ada dan dapat ditambahkan setelah konfirmasi |
| Identik | Entri sama dengan yang tersimpan; tidak ditambahkan ulang |
| Perubahan | Ada perbedaan yang dapat diterapkan jika seluruh pemeriksaan lolos |
| Konflik | Perubahan menyentuh data terikat klaim atau penugasan yang memerlukan pemeriksaan khusus |
| Kesalahan | Nilai, format, hubungan, atau keunikan data tidak memenuhi aturan |

Kombinasi nomor SK dan nomor urut menjadi pembeda entri dalam periode. Nama bukan kunci unik. Pengenal resmi tidak boleh berulang dalam berkas atau sudah dipakai pada entri lain dalam periode itu.

Mengosongkan `operator_email` saat impor ulang mempertahankan penugasan yang sudah ada. Mengubah operator yang sudah ditetapkan bukan cara transfer biasa; gunakan fitur pemindahan penugasan. Perubahan pada peserta yang sudah terikat klaim perlu penanganan administratif.

## 5. Menangani kesalahan dan konfirmasi

Jika ada kesalahan atau konflik:

1. Klik **Unduh Laporan Kesalahan Excel** pada proses `invalid` atau `failed`.
2. Baca nomor baris Excel dan catatan perbaikan.
3. Perbaiki berkas sumber, bukan sekadar laporan kesalahannya.
4. Cocokkan kembali dengan daftar resmi.
5. Unggah ulang untuk menghasilkan pratinjau baru.

Jika seluruh baris siap:

1. Periksa nama berkas, periode, kode periode, ringkasan, dan seluruh perubahan.
2. Centang bahwa sumber resmi serta seluruh perubahan telah diperiksa.
3. Klik **Konfirmasi Impor**.
4. Pastikan status menjadi **Impor telah selesai**.
5. Buka **Peserta SK**, pilih periode, dan periksa beberapa entri serta total hasil yang relevan.
6. Lanjutkan pembagian operator bila masih ada peserta belum ditugaskan.

Pratinjau mempunyai masa berlaku **satu hari** sejak validasi. Perubahan data periode di antara validasi dan konfirmasi dapat membuatnya tidak berlaku lebih awal. Bila ditolak karena data berubah atau pratinjau tidak valid, unggah ulang untuk pemeriksaan terbaru.

Berkas sumber dan rincian staging mempunyai retensi bawaan **30 hari**, dapat diubah pengelola teknis. Setelah pembersihan, metadata impor dan peserta hasil impor tetap ada, tetapi laporan baris tidak tersedia. Simpan berkas sumber resmi sesuai prosedur arsip kampus.

## 6. Mendaftarkan operator dan membagi peserta awal

Buka **Pembagian Operator** (`/admin/penugasan`).

1. Pilih periode yang belum diarsipkan.
2. Centang satu atau lebih operator aktif.
3. Klik **Tinjau Pembagian**.
4. Periksa jumlah peserta belum ditugaskan serta beban awal, tambahan, dan beban akhir tiap operator.
5. Centang konfirmasi pemeriksaan distribusi.
6. Klik **Simpan Operator & Jalankan Pembagian**.
7. Periksa pesan keberhasilan dan detail beberapa peserta.

Proses ini juga mendaftarkan operator terpilih pada periode; dapat digunakan sebelum impor ketika peserta belum ada. Sesudah impor, jalankan lagi untuk membagi peserta tanpa operator.

Pembagian awal menggunakan giliran bergantian pada peserta SK aktif yang belum ditugaskan, diurutkan menurut nomor SK, nomor urut, dan ID. Beban yang sudah ada dipertahankan. Pembagian tambahan yang merata belum tentu menghasilkan **total beban akhir** yang sama jika beban awal berbeda.

Mengubah pilihan operator pada pembagian awal tidak menghapus tugas yang sudah ada dan tidak otomatis menonaktifkan keanggotaan operator lain. Untuk meratakan ulang, gunakan fitur massal. Pratinjau pembagian awal berlaku satu jam dan dapat ditolak bila data periode berubah.

## 7. Memindahkan satu peserta

1. Buka **Peserta SK**, cari peserta, lalu buka detail.
2. Pada **Pindahkan penugasan**, pilih operator tujuan.
3. Pastikan operator tujuan aktif dan terdaftar pada periode.
4. Isi alasan perpindahan 10–1.000 karakter.
5. Klik **Konfirmasi Perpindahan**.
6. Periksa nama operator pada detail peserta.

Pilih operator yang berbeda dari operator saat ini. Pemindahan mengubah akses operator lama dan baru, sementara pemilik akun pendaftar tetap sama. Periode arsip tidak dapat diproses.

## 8. Transfer massal dan penyeimbangan beban

Dari **Pembagian Operator**, klik **Transfer Massal & Penyeimbangan Beban** (`/admin/penugasan-massal`).

### 8.1 Seimbangkan seluruh peserta aktif

1. Pilih periode dan metode **Seimbangkan seluruh peserta aktif**.
2. Pilih satu atau lebih operator tujuan yang aktif, sudah mengganti sandi awal, dan terdaftar pada periode.
3. Klik **Buat Pratinjau Perubahan**.
4. Periksa beban aktif awal dan akhir serta semua halaman rincian peserta yang akan berpindah.
5. Isi alasan 10–1.000 karakter.
6. Centang bahwa seluruh halaman dan perubahan akses telah ditinjau.
7. Klik **Konfirmasi Perubahan Penugasan**.
8. Periksa jumlah penugasan yang berubah.

Seluruh peserta aktif periode dapat masuk perhitungan, termasuk yang sebelumnya ditugaskan ke operator di luar daftar tujuan. Sistem mempertahankan sebanyak mungkin tugas lama dalam kuota distribusi tujuan. Jika distribusi sudah sesuai, tidak ada perubahan yang perlu dikonfirmasi.

### 8.2 Transfer seluruh tugas aktif satu operator

1. Pilih metode **Transfer seluruh tugas aktif satu operator**.
2. Pilih operator sumber.
3. Pilih **tepat satu** operator tujuan yang berbeda dari sumber.
4. Buat pratinjau, periksa semua rincian, tulis alasan, dan konfirmasikan seperti prosedur di atas.
5. Koordinasikan serah terima pekerjaan tertunda kepada operator tujuan.

Peserta yang ditarik dari kepesertaan dikecualikan dari proses massal. Pratinjau massal berlaku **15 menit**, terikat pada akun pembuat, dan dapat ditolak jika data berubah. Buat pratinjau baru bila kedaluwarsa; jangan memakai tab lama untuk memaksakan perubahan.
