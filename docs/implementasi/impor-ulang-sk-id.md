# Impor ulang peserta SK berdasarkan ID

Pembaruan **10 November 2026**. Implementasi tersedia dan diuji di lokal; catatan ini bukan bukti deployment production. Acuan: [rancangan 16](../rancangan-aplikasi/16-impor-ulang-sk-berdasarkan-id.md).

## Penggunaan

1. Buka `/admin/impor` sebagai administrator/operator. Pilih periode tujuan yang benar.
2. Untuk file yang diperbarui, gunakan **Perbarui saja** (pilihan awal antarmuka). ID yang tidak ditemukan akan ditandai error, bukan ditambahkan. Untuk impor awal/file campuran, pilih **Tambah dan perbarui**.
3. Gunakan template versi 6. Kolom A–E wajib: nomor SK, tanggal, nomor urut, nama, dan `id_peserta_sk`. Simpan ID sebagai teks agar nol awal utuh. Template lama tetap dibaca bila header/data ID tersedia, tetapi ID kosong tidak lagi boleh dicocokkan memakai nomor SK/urut.
4. Unggah file dan periksa pratinjau. Kunci pembaruan adalah **periode + id_peserta_sk**; ID Excel ini merupakan Peg ID resmi, bukan nomor primary key internal database.
5. Periksa nama/ID serta nilai lama → baru pada setiap baris perubahan. Nomor SK/urut dapat diperbarui pada peserta yang sama selama tidak berbenturan dengan peserta lain.
6. Konfirmasikan baris valid. Baris error/konflik tetap ditunda, dapat difilter dan diunduh untuk perbaikan. Unggah ulang file yang diperbaiki pada periode yang sama.

Permintaan lama yang tidak mengirim parameter jenis impor menggunakan Tambah dan perbarui untuk kompatibilitas; antarmuka baru selalu mengirim pilihan secara eksplisit. Jenis impor tersimpan pada riwayat dan menjadi bagian hash pratinjau.

## Perlindungan data

- Semua kemunculan ID duplikat dan kunci nomor SK/urut duplikat ditunda; tidak hanya baris kedua. Kesetaraan ID mengikuti collation database, tanpa mengubah nilai resmi/nol awal.
- Sel opsional kosong mempertahankan nomor HP, operator, asal dan catatan internal. Penghapusan nilai dilakukan lewat editor yang berwenang.
- Perubahan pada peserta terikat klaim tetap menjadi konflik administratif. Transfer operator yang sudah ditugaskan dilakukan melalui menu penugasan. Impor tidak menimpa akun, biodata, berkas atau snapshot pengajuan.
- ID sama pada periode lain tidak diperbarui. Peserta yang tidak ada dalam file terbaru tidak dihapus. ID salah/tidak ditemukan tidak memakai nomor SK/urut atau nama sebagai pencocokan cadangan.
- Peserta identik tidak diubah; konfirmasi import yang sama idempoten. Pembaruan mempertahankan primary key peserta dan menaikkan versi target.
- Perubahan target setelah pratinjau diperiksa menggunakan ID internal, versi dan digest data. Jika berubah/dihapus/terikat klaim, transaksi dibatalkan dan pengelola diminta mengunggah ulang. Tidak berpindah ke target baru atau menyimpan sebagian perubahan dalam transaksi yang gagal.
- Nomor SK/urut tujuan tidak boleh berbenturan. Pertukaran urutan antar dua peserta ditunda untuk pemeriksaan administratif. Tanggal dalam satu nomor SK harus tetap konsisten, termasuk peserta yang tidak ikut diubah.

## Deployment

Jalankan migrasi `2026_11_10_164549_add_identity_matching_to_sk_imports.php` sebelum menjalankan kode baru, lalu restart queue worker sesuai prosedur deployment. Migrasi menambahkan metadata mode/algoritme impor dan target/diff staging, tanpa mengganti primary key atau indeks unik peserta.

Kolom pencocokan staging mengikuti collation kolom peserta pada database target. Riwayat impor lama tetap dapat dibaca. Pratinjau lama yang belum committed tidak dapat dikonfirmasi menggunakan algoritme baru; unggah ulang untuk pemeriksaan terbaru. Rollback migrasi menghapus metadata/diff baru, sehingga gunakan forward fix setelah ada impor baru.

## Verifikasi

Tes fitur mencakup request unggah/pratinjau/commit untuk administrator dan operator, mode tambah/update, perubahan nomor SK/urut, ID nol awal dan `0`, semua baris duplikat, partial commit, isolasi periode, no-op peserta terikat, stale target, rollback, serta tanggal SK dan konflik penugasan.

Pemeriksaan langsung pada MySQL lokal juga menguji pencocokan ID yang berbeda kapitalisasi, pembaruan record yang sama, pemeliharaan penulisan ID resmi dan penundaan seluruh kelompok ID duplikat menurut collation. Data pemeriksaan dibuat dalam transaksi yang di-rollback dan file sementaranya dibersihkan.

Pengujian otomatis tidak menggantikan pemeriksaan konfigurasi queue, backup dan penerimaan pengelola di production.
