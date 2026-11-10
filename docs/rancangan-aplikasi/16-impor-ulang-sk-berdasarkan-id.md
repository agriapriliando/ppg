# Rancangan impor ulang peserta SK berdasarkan ID peserta

Tanggal: **10 November 2026**, zona waktu **Asia/Jakarta**. Status: **acuan rancangan; implementasi lokal tersedia**. Perilaku aktual dan langkah rilis dijelaskan pada [catatan implementasi](../implementasi/impor-ulang-sk-id.md). Rancangan/matriks penerimaan di bawah tetap menjadi acuan, bukan klaim deployment production.

Halaman tetap `/admin/impor`, dengan alur unggah Excel, pratinjau, dan konfirmasi yang sudah ada. Tujuan: file Excel lama yang telah diperbarui dapat diunggah ulang untuk memperbarui peserta yang sama berdasarkan kolom **`id_peserta_sk`**, tanpa menggandakan peserta saat nomor SK atau nomor urut berubah.

## 1. Arti ID dan kunci pembaruan

`id_peserta_sk` adalah header Excel yang sudah ada, dipetakan ke **`sk_participants.official_participant_id`** (pengenal resmi/Peg ID). Ini **bukan** primary key internal `sk_participants.id`, ID akun, nomor urut, atau ID riwayat impor. Tidak perlu menambahkan kolom ID internal baru ke Excel untuk kebutuhan ini.

Kunci pencocokan adalah **`(admission_period_id, official_participant_id)`**. Periode tetap dipilih di halaman impor. Pengenal yang sama pada periode lain tidak boleh memperbarui peserta lintas periode atau memindahkan peserta ke periode tujuan.

Contoh sintetis:

| Data | Sebelum | Excel yang diunggah ulang |
| --- | --- | --- |
| Periode tujuan | PPG-UJI-2026 | PPG-UJI-2026 |
| `id_peserta_sk` | `000001` | `000001` |
| `nomor_sk` | SK-UJI-A | SK-UJI-B |
| `nomor_urut_sk` | 12 | 7 |
| `nama_sesuai_sk` | Peserta Uji Satu | Peserta Uji Satu, S.Pd. |

Jika peserta belum terikat klaim dan tidak ada benturan, hasilnya **satu peserta diperbarui**. Primary key internal tetap sama; tidak membuat peserta baru atau menghapus peserta lama. Perubahan ditampilkan dalam pratinjau sebelum disimpan.

## 2. Kondisi aplikasi saat telaah

- [`ValidateSkImport`](../../app/Actions/Imports/ValidateSkImport.php) sudah membaca `id_peserta_sk`, memeriksa teks/nol awal dan duplikat, tetapi mencari target berdasarkan periode + nomor SK + nomor urut.
- [`CommitSkImport`](../../app/Actions/Imports/CommitSkImport.php) memakai kunci nomor SK/urut yang sama untuk penulisan. Mengubah pencocokan pada pratinjau saja tidak cukup.
- Basis data sudah memiliki indeks unik `sk_official_identifier` untuk periode + pengenal resmi dan `sk_business_key` untuk periode + nomor SK + nomor urut. Keduanya tetap diperlukan; ID menjadi kunci pencocokan, sementara nomor SK/urut tetap harus tidak berbenturan.
- ID boleh kosong pada template/alur lama. Impor mempertahankan operator, nomor HP dan asal bila tidak diisi, tetapi catatan internal kosong saat ini dapat menimpa nilai lama.
- Data yang berubah setelah terikat klaim atau perubahan operator yang sudah ditugaskan saat ini ditandai konflik. Riwayat impor mendukung penyimpanan baris valid dan penundaan baris error/konflik.

Dokumen ini memperbarui kebijakan pencocokan serta isian kosong secara eksplisit. Tidak mengubah mekanisme klaim, kepemilikan akun, penugasan, atau data pengajuan melalui jalan pintas impor.

## 3. Pilihan perilaku pada menu impor yang sama

Tambahkan pilihan **Jenis impor** pada formulir yang sudah ada:

| Pilihan | ID ditemukan pada periode tujuan | ID belum ditemukan |
| --- | --- | --- |
| **Tambah dan perbarui** | Bandingkan, lalu perbarui bila aman | Tambah peserta baru bila seluruh data valid dan nomor SK/urut tidak berbenturan |
| **Perbarui saja** | Bandingkan, lalu perbarui bila aman | Tandai error; jangan menambah peserta |

Untuk tujuan mengunggah ulang file lama, rekomendasikan **Perbarui saja** agar salah ketik ID tidak membuat peserta baru. Untuk impor awal, gunakan Tambah dan perbarui. Pilihan tersimpan pada import run dan tampil di pratinjau; jangan menebak mode dari nama file, hash, atau kemiripan isi. Mode tidak boleh berubah setelah pratinjau tanpa validasi ulang.

`id_peserta_sk` wajib terisi pada kedua mode baru. Header template tetap sama dan petunjuk diperbarui. File lama dengan ID terisi dapat digunakan langsung. Baris dengan ID kosong ditandai error dan tidak memperbarui data berdasarkan nama atau nomor SK/urut sebagai fallback. Baris lain yang valid tetap dapat dikonfirmasi.

Peserta lama yang ID-nya masih kosong harus dipasangkan melalui pengubahan administratif yang sudah berwenang, lalu file diimpor ulang. Jangan mengisi ID peserta lama secara otomatis hanya karena nomor SK/urut cocok. Pembetulan ID salah mengikuti pemeriksaan administratif dengan audit; impor ini tidak mengganti nilai yang dipakai sebagai kunci pencarian.

## 4. Aturan pembacaan ID

- Kolom disimpan sebagai **teks Excel**, maksimum 100 karakter. Pertahankan nol awal dan isi pengenal, tanpa konversi menjadi angka, scientific notation, atau penambahan nol berdasarkan tebakan.
- Trim spasi luar; jangan menghapus tanda baca, spasi dalam, atau mengubah ID demi mencocokkan nama peserta. Formula tetap ditolak. Nilai numerik dari Excel ditandai error dengan petunjuk menggunakan format teks.
- Gunakan aturan kesetaraan ID yang konsisten dengan kolom dan indeks unik database. Sebelum implementasi, periksa collation pada database target dan jalankan deteksi benturan, termasuk perbedaan huruf besar/kecil/spasi akhir. Jika hasilnya ambigu, tandai konflik; jangan memilih peserta pertama.
- Jangan memperkenalkan normalisasi ID baru yang menggabungkan pengenal berbeda tanpa audit data/migrasi yang disepakati. Penyimpanan nilai resmi tetap utuh; duplicate detection dan lookup harus menggunakan aturan kesetaraan yang sama.
- Header yang tidak didukung/struktur workbook tidak valid tetap menggagalkan pembacaan file. Error nilai pada satu baris tetap dicatat per baris.

## 5. Algoritme validasi dan pencocokan

1. Validasi workbook, periode, jenis impor dan header; simpan file privat, hash serta versi algoritme pencocokan.
2. Baca seluruh baris, normalisasi sesuai aturan yang sudah ada, lalu kelompokkan ID dan kunci nomor SK/urut sebelum menetapkan baris layak. **Semua kemunculan ID duplikat dalam file ditandai error**, bukan membiarkan baris pertama memperbarui peserta dan hanya menolak baris berikutnya.
3. Ambil peserta periode tujuan berdasarkan kumpulan ID. Jangan menggunakan nama, nomor HP, operator atau nomor baris Excel untuk menemukan target.
4. Bila ID tidak ditemukan, terapkan mode: baris baru hanya untuk Tambah dan perbarui; Perbarui saja menghasilkan error. ID yang hanya ada di periode lain tetap dianggap tidak ditemukan pada periode tujuan.
5. Bila ditemukan, simpan target primary key internal, versi target dan snapshot/digest nilai dasar untuk pemeriksaan ulang saat commit. Bandingkan seluruh field yang boleh diperbarui, termasuk **nomor SK dan nomor urut**.
6. Periksa benturan nomor SK/urut dengan peserta lain pada periode yang sama, duplikat antarbaris serta konsistensi tanggal untuk nomor SK yang sama, termasuk peserta tersimpan yang tidak ada dalam file.
7. Terapkan perlindungan data terikat klaim dan penugasan. Hasil identik tetap no-op, termasuk untuk peserta yang sudah diklaim.
8. Bentuk hasil per baris: `new`, `change`, `identical`, `error`, atau `conflict`, dengan diff field, alasan dan target yang jelas. Perubahan sebagian field dalam satu baris tidak disimpan bila baris itu error/konflik.

Pertukaran nomor urut dua peserta yang saling menduduki kunci tujuan ditandai konflik pada rilis awal; jangan mengandalkan urutan penulisan untuk melewati indeks unik. Selesaikan melalui pemeriksaan administratif. Tidak menghapus peserta lain untuk mengosongkan kunci tujuan.

## 6. Field yang boleh diperbarui dan sel kosong

| Field Excel | Perilaku pembaruan |
| --- | --- |
| `id_peserta_sk` | Kunci pencarian; tidak mengganti ID target melalui impor ulang |
| `nomor_sk`, `tanggal_sk`, `nomor_urut_sk`, `nama_sesuai_sk` | Wajib valid; perbarui pada peserta yang sama bila belum terikat klaim dan bebas benturan |
| `nomor_hp` | Jika terisi, normalisasi impor ke pola 628 sesuai helper yang berlaku; kosong/header tidak ada mempertahankan nomor lama |
| `id_operator`/`operator_email` pada template lama | Kosong mempertahankan penugasan; operator sama no-op; operator berbeda pada peserta yang sudah ditugaskan menjadi konflik, gunakan menu penugasan |
| `kode_kota_kabupaten_asal` | Terisi harus valid menurut referensi; kosong mempertahankan asal lama |
| `catatan_internal` | Terisi memperbarui; kosong mempertahankan catatan lama, agar reupload tidak menghapus catatan tanpa sengaja |

Sel kosong bukan perintah hapus. Penghapusan nilai opsional dilakukan lewat editor yang berwenang; jangan menambah token seperti `NULL`, `-`, atau `HAPUS` yang dapat tertukar dengan nilai resmi. Pada peserta baru, nilai opsional kosong menjadi null/default sesuai model.

Perubahan apa pun pada peserta yang sudah terikat klaim tetap ditandai **konflik administratif** pada rilis awal; tidak menimpa akun, biodata, kontak tersinkron, aplikasi, status pemeriksaan, berkas, ataupun snapshot pengajuan. Jika nanti impor diizinkan mengubah kontak peserta terikat, itu memerlukan perluasan eksplisit melalui layanan sinkronisasi kontak yang sudah ada dan pengujian tersendiri.

Peserta yang tidak tercantum dalam file terbaru tidak dihapus, ditarik, atau diubah statusnya. Nama sama dengan ID berbeda tidak digabung. Jika ID salah tetapi kebetulan milik orang lain pada periode tujuan, sistem tidak dapat membuktikan identitas hanya dari ID: diff nama/perubahan resmi harus terlihat jelas dan tetap memerlukan konfirmasi pengelola; peserta terikat tetap konflik.

## 7. Pratinjau, pesan, dan konfirmasi

Gunakan halaman pratinjau yang sudah ada. Tampilkan periode, jenis impor, sumber pencocokan **ID peserta SK**, jumlah baris baru/berubah/identik/error/konflik, nomor baris Excel dan ID peserta. Pada baris perubahan, tampilkan field **lama → baru**, terutama nama, nomor SK, nomor urut, nomor HP dan penugasan.

Contoh pesan:

- `Baris 8: ID peserta SK wajib diisi untuk mencocokkan peserta. Tidak ada data yang diperbarui pada baris ini.`
- `Baris 12: ID 000001 tidak ditemukan pada periode tujuan. Mode Perbarui saja tidak menambahkan peserta baru.`
- `ID 000001 berulang pada baris 4 dan 9. Kedua baris ditunda; perbaiki duplikat lalu unggah ulang.`
- `ID 000001 cocok. Nomor SK berubah SK-UJI-A → SK-UJI-B dan nomor urut 12 → 7.`
- `Nomor SK dan nomor urut tujuan sudah digunakan peserta lain. Baris ini tidak disimpan.`
- `Peserta sudah terhubung ke akun melalui klaim. Gunakan pemeriksaan administratif untuk perubahan ini.`

Tombol konfirmasi menyebut **Simpan baris valid** dengan hitungan yang akan ditambahkan/diperbarui serta yang ditunda. File dengan sebagian error tetap dapat diproses untuk baris valid, sesuai perilaku aplikasi yang sudah ada. Baris error/konflik tetap ditandai dan dapat difilter/diunduh sebagai laporan untuk diperbaiki kemudian. Bila tidak ada baris valid, tombol commit tidak aktif.

Laporan perbaikan menyertakan header `id_peserta_sk`, nomor baris sumber, alasan dan data yang diperlukan untuk perbaikan; ekspor memakai tipe sel teks eksplisit serta mencegah formula injection. Jangan memakai nomor baris sebagai identitas saat file diunggah ulang.

## 8. Commit, perubahan bersamaan, dan audit

Pratinjau harus menyimpan **target ID internal + versi/digest target**, jenis impor, versi algoritme, periode, versi dataset dan payload/diff. Preview hash mencakup kontrak tersebut. Riwayat yang dipratinjau oleh algoritme lama tidak boleh langsung dikonfirmasi memakai algoritme baru: minta unggah/validasi ulang. Riwayat yang sudah committed tetap dapat dibaca tanpa diterapkan ulang.

Saat konfirmasi, lock periode/import/target mengikuti urutan konsisten dan periksa ulang izin, arsip, masa berlaku preview, versi, status klaim, operator, identitas target, benturan kunci dan referensi wilayah. **Jangan memakai `firstOrNew` dengan nomor SK/urut untuk baris update**. Target harus tetap sama dengan hasil pencocokan ID pada pratinjau. Jika peserta dihapus, ID berubah, klaim terikat atau data diedit setelah preview, tolak commit sebagai stale dan minta validasi ulang; jangan berpindah target atau memasukkannya sebagai peserta baru.

Baris error/konflik yang sudah diketahui saat pratinjau tidak masuk daftar penulisan. Seluruh baris valid yang disetujui ditulis dalam satu transaksi. Jika muncul konflik baru saat commit, rollback transaksi tersebut, pertahankan hasil staging dan minta pemeriksaan ulang; jangan melakukan sebagian penulisan tanpa ringkasan yang sesuai.

Update mempertahankan primary key internal, menaikkan `lock_version`, memperbarui `last_import_id` dan versi periode sesuai mekanisme yang ada. Baris identik tidak mengubah kontak/penugasan/status atau versi peserta. Konfirmasi ulang import yang sama bersifat idempoten; reupload isi identik menjadi no-op setelah pencocokan.

Audit mencatat aktor, periode, jenis impor, import run, target internal, ID resmi, field yang berubah, hasil dan waktu. Data sensitif tetap privat; jangan mencatat seluruh workbook/nomor HP di log umum. Penugasan awal mengikuti audit assignment yang sudah ada; transfer operator tidak diselundupkan lewat impor.

## 9. Usulan perubahan kode dan data

| Sasaran | Rancangan perubahan |
| --- | --- |
| `ValidateSkImport` | Lookup periode + ID, duplikat seluruh kelompok, diff SK/urut, mode update-only dan payload target/version |
| `CommitSkImport` | Update target eksplisit berdasarkan ID hasil preview, validasi ulang dan perlindungan stale; insert hanya pada mode yang mengizinkan |
| `SkImport` | Simpan jenis impor dan versi algoritme untuk membedakan preview lama/baru |
| `SkImportRow` | Simpan target internal/version/digest serta diff terstruktur; nullable untuk baris baru/error dan riwayat lama |
| Controller, form dan pratinjau impor | Pilihan jenis impor, label kunci ID, diff, ringkasan baris valid/ditunda dan laporan perbaikan |
| Template Excel/Panduan | Tegaskan ID wajib teks untuk mode baru; sertakan contoh `000001` dan aturan sel kosong |
| Database | Pertahankan kedua indeks unik yang ada; migrasi metadata impor/staging jika dibutuhkan, tanpa mengganti primary key atau memindahkan relasi peserta |

Jangan memakai `upsert` massal yang melewati pemeriksaan klaim, audit, versi dan konflik penugasan. Untuk skala impor, gunakan lookup terkelompok agar tidak menambah query per field/baris; commit tetap memenuhi kontrol konkurensi yang sama.

## 10. Tahap pengerjaan dan pengujian

1. Audit data ID kosong/benturan dan kesetaraan collation; tetapkan kontrak preview/mode serta migrasi metadata yang kompatibel riwayat.
2. Ubah validasi, pencocokan dan diff; sesuaikan template, form, pratinjau serta laporan.
3. Ubah commit untuk target eksplisit dengan lock/version; uji perlindungan relasi dan penugasan.
4. UAT file awal → ubah file → upload ulang pada menu yang sama; panduan pengelola diperbarui sebelum rilis.

Matriks penerimaan minimum:

- ID sama, nama/nomor HP/SK/urut berubah pada peserta belum diklaim: update satu record, primary key tetap, tidak menggandakan peserta.
- ID sama pada periode lain tidak disentuh; ID tidak ditemukan ditolak pada Perbarui saja, dan ditambah hanya pada Tambah dan perbarui bila bebas benturan.
- File identik, termasuk peserta terikat klaim: no-op; konfirmasi berulang tidak menerapkan perubahan dua kali.
- ID kosong/numerik/duplikat dan benturan nomor SK/urut menghasilkan pesan per baris; semua kemunculan duplikat ditunda.
- ID nol awal tetap utuh; perbedaan huruf/spasi sesuai aturan database diuji tanpa memilih target ambigu.
- Nomor SK/urut cocok tetapi ID berbeda/tidak ditemukan tidak menyebabkan peserta lama ditimpa. Pertukaran urutan dan tanggal SK yang tidak konsisten ditolak.
- Sel kosong mempertahankan nilai opsional; template lama dengan nomor HP tidak ada tidak menghapus nomor; formula/template rusak tetap ditolak.
- Perubahan data terikat klaim dan transfer operator tetap konflik; biodata, akun, kontak, berkas dan snapshot lama tetap utuh.
- Peserta tidak ada dalam file terbaru tidak dihapus. Nama sama dengan ID berbeda tidak digabung otomatis.
- Campuran valid/error/konflik: hanya baris valid tersimpan, hitungan dan laporan benar; setelah diperbaiki dan diupload ulang target tidak berganda.
- Edit/hapus/klaim/penugasan bersamaan sesudah preview, impor paralel dan periode diarsipkan: pemeriksaan ulang menolak keadaan stale tanpa penulisan parsial yang tidak dilaporkan.
- Preview algoritme lama ditolak untuk commit baru; riwayat committed lama tetap terbaca. Audit dan versi target/period tetap akurat.

Gate selesai: pembaruan memakai periode + ID resmi secara konsisten pada preview dan commit, tidak ada duplikasi/salah sasaran, perlindungan data terikat tetap berlaku, serta tes dan UAT di atas lulus. Seluruh pengujian ini masih **rencana**, bukan hasil implementasi.
