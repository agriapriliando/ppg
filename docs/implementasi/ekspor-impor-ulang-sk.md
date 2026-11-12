# Ekspor Excel untuk impor ulang SK

Pembaruan: **12 November 2026**, Asia/Jakarta. Implementasi lokal; deployment production mengikuti prosedur rilis aplikasi. Fitur tersedia melalui `/admin/ekspor`, menggunakan antrean dan riwayat ekspor yang sudah ada.

## Penggunaan

1. Buka **Admin → Ekspor**, pilih **Excel untuk Impor Ulang SK (.xlsx)**.
2. Pilih satu periode yang belum diarsipkan. Operator dan status pendaftaran dapat digunakan untuk membatasi peserta yang diekspor. Hasil mencakup seluruh peserta yang sesuai filter, bukan hanya satu halaman.
3. Klik **Siapkan Ekspor**, lalu **Unduh Excel** setelah status siap. Unduhan tetap privat, memiliki masa berlaku 24 jam dan diperiksa kembali ketika data cakupan berubah.
4. Edit data pada sheet **Peserta_SK**, pertahankan header dan format teks. Jangan mengubah PEG ID untuk memperbarui peserta yang sama. Semua sel data ditulis sebagai teks, termasuk ID, nomor HP dan kode wilayah, sehingga nol awal tidak hilang dan teks yang diawali tanda sama dengan tidak dijadikan formula.
5. Buka **Admin → Impor SK**, pilih periode sumber yang sama serta mode **Perbarui saja**. Unggah `.xlsx`, periksa nilai lama–baru, lalu konfirmasikan baris valid.

File menyertakan sheet **Panduan**, **Referensi_Wilayah** dari dataset aktif, dan **Periode_Sumber**. Sheet terakhir diperiksa saat impor untuk mencegah salah periode tujuan; jangan mengubah atau menghapusnya. File template biasa tanpa sheet tersebut tetap mengikuti alur impor yang sudah ada.

## Data dan batas

Kolom `Peserta_SK` mengikuti `ValidateSkImport::HEADERS` dan template yang sama:

```text
nomor_sk, tanggal_sk, nomor_urut_sk, nama_sesuai_sk, id_peserta_sk,
id_operator, catatan_internal, kode_kota_kabupaten_asal, nomor_hp
```

Ekspor menambahkan kolom J **Kota/Kab** dan K **Provinsi** agar nama wilayah langsung terbaca pada setiap baris. Nama diambil dari referensi aktif sesuai kode asal; bila kode lama tidak tersedia, nama tersimpan digunakan sebagai informasi. Kedua kolom diterima saat impor tetapi tidak digunakan untuk memperbarui wilayah. Pembaruan wilayah tetap mengikuti `kode_kota_kabupaten_asal`; mengubah tulisan Kota/Kab atau Provinsi saja tidak mengubah data asal. Template sembilan kolom sebelumnya tetap diterima.

Ekspor Excel memerlukan 1–5.000 peserta dan PEG ID terisi. Pilihan kosong, periode arsip atau peserta tanpa ID akan diberi pesan untuk diperbaiki sebelum ekspor. Gunakan filter operator/status jika jumlah peserta melebihi batas. Pemeriksaan diulang oleh job saat pembuatan file; ukuran file juga diperiksa terhadap batas unggahan impor.

`id_operator` adalah ID login operator aktif. Jika operator tidak aktif atau tidak tersedia, kolom ini dikosongkan; isian kosong pada impor mempertahankan penugasan lama. Kode wilayah mengikuti nilai yang tersimpan; referensi yang sudah tidak aktif dapat memerlukan perbaikan saat pratinjau.

File ini memperbarui data resmi peserta SK. Akun, biodata formulir, status pendaftaran, berkas unggahan dan kata sandi tidak menjadi kolom impor. Peserta terikat klaim, konflik nomor SK/urut, perubahan penugasan dan data yang berubah setelah pratinjau tetap diperiksa oleh perlindungan impor. File hasil ekspor tidak menjamin semua baris dapat diperbarui jika kondisi tersebut ditemukan.

Pilihan **Laporan administrasi (CSV)** tetap tersedia dan merupakan laporan; file CSV tersebut tidak dapat langsung digunakan di menu Impor SK. File ekspor lama tanpa penanda jenis tetap dikenali sebagai CSV.

## Implementasi dan verifikasi

- Pembuat workbook `SkImportWorkbook` dipakai bersama oleh template kosong dan ekspor, agar struktur dan panduan tidak berbeda.
- Jenis ekspor disimpan di `ExportRun.scope_snapshot`; tidak ada migrasi baru. `GenerateParticipantExportJob` menghasilkan CSV atau XLSX sesuai jenis yang dipilih, dengan validasi akses dan perubahan cakupan yang sudah ada.
- Uji fitur memeriksa tipe sel teks, nilai berawalan nol, teks mirip formula, filter periode/operator, ekspor ulang dari operator nonaktif, batas pilihan, unduhan privat dan kedaluwarsa.
- Uji alur lengkap menggunakan file hasil ekspor, mengedit nama/nomor HP, lalu menjalankan unggah, pratinjau dan konfirmasi impor. Identitas peserta tetap sama, jumlah peserta tidak bertambah. Salah periode dengan PEG ID yang sama ditolak.
- Jalankan `php artisan test --compact tests/Feature/Admissions/SkReimportExportTest.php tests/Feature/Admissions/ParticipantExportTest.php` serta pengujian impor terkait sebelum rilis.

Untuk production, deploy kode bersama, bangun cache view sesuai prosedur dan restart queue worker agar memuat pembuat ekspor baru. Verifikasi unduhan dan impor pada lingkungan target. Tidak ada perubahan skema database untuk fitur ini.
