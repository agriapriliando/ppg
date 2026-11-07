# Rancangan penghapusan periode beserta seluruh data dan file tertaut

Tanggal: **6 November 2026**, zona waktu **Asia/Jakarta**. Status: **rancangan untuk implementasi; fitur belum diterapkan melalui pekerjaan ini**. Tujuan halaman: `/admin/periode`.

## 1. Keputusan utama yang direkomendasikan

Administrator dapat menghapus satu periode, termasuk seluruh data operasional dan file yang menjadi milik periode tersebut. Fitur berlaku pada periode draft, dipublikasikan, ditutup, maupun arsip. Pengajuan penghapusan memerlukan konfirmasi eksplisit, pengetikan kode periode, dan **kata sandi lokal akun administrator yang sedang masuk**.

Penghapusan dijalankan sebagai pekerjaan bertahap di belakang layar. Periode dibekukan terlebih dahulu, daftar file disimpan secara persisten, file fisik dihapus, lalu data database dihapus sesuai urutan relasi. Status **Selesai** baru diberikan setelah verifikasi database dan file berhasil. Kegagalan tidak boleh ditampilkan sebagai sukses.

Rekomendasi batas yang penting: akun pengguna, dataset referensi, dan pengaturan yang dipakai bersama tidak ikut dihapus. Hubungan serta akses khusus periode dihapus; data periode lain tetap utuh. Bukti audit penghapusan yang minimal dipertahankan. Bagian 3 menjelaskan batas ini agar istilah “seluruh data” tidak menyebabkan penghapusan data lintas periode.

## 2. Kondisi aplikasi yang sudah ada

Hasil pemeriksaan repositori saat penyusunan:

- `AdmissionPeriodPolicy::update()` juga mengizinkan operator. Izin ini **tidak cukup** untuk fitur hapus; perlu kemampuan `delete` khusus administrator.
- Data periode tersebar pada peserta SK, klaim, ruang kerja, formulir, riwayat pengiriman, koreksi kepemilikan, impor, konfigurasi, dan berkas.
- Klaim memakai soft delete. Penghapusan menyeluruh harus mencakup klaim yang sudah tidak tampil pada daftar normal.
- Berkas peserta dapat sudah dilepas dari draf, dipertahankan untuk riwayat pengiriman, atau berada pada ruang kerja pemilik lama setelah koreksi kepemilikan.
- `export_runs` mengaitkan periode melalui JSON `scope_snapshot`, bukan foreign key langsung. Ekspor tanpa filter periode dapat memuat beberapa periode.
- `document_deletion_tasks` mendukung jenis `applicant` dan `period`, tetapi belum menyimpan identitas periode secara langsung.
- Beberapa versi konfigurasi melarang penghapusan lewat event model. Pengecualian penghapusan menyeluruh harus dirancang secara khusus dan terbatas.
- `lock_version` periode tidak berubah pada setiap unggahan atau autosave. Nilai itu saja tidak cukup untuk memastikan pratinjau penghapusan masih mutakhir.
- Tersedia `pmb:reset-data` untuk reset global. Endpoint hapus satu periode harus memakai operasi tersendiri dengan scope periode yang eksplisit.

Dokumen ini merupakan perluasan rancangan. Daftar class, tabel, route, dan guard berikut adalah sasaran implementasi, bukan pernyataan bahwa semuanya sudah tersedia.

## 3. Cakupan data dan file

### 3.1 Data operasional yang dihapus

Inventaris ditentukan oleh ID periode serta turunan relasinya, bukan nama periode atau pencarian teks. Seluruh status, versi, dan kepemilikan lama dalam periode ikut dihitung.

| Kelompok | Tabel/sumber | Cara membatasi scope |
| --- | --- | --- |
| Periode | `admission_periods` | ID periode target; dihapus paling akhir |
| Penugasan | `period_operators`, `assignment_events` | Periode target dan seluruh ID peserta SK target |
| Peserta SK | `sk_participants` | `admission_period_id` target, termasuk yang ditarik atau ditahan administratif |
| Klaim | `sk_claims`, `claim_responses` | Semua klaim periode, termasuk `withTrashed()`, klaim tanpa kandidat, dan tanggapannya |
| Akses/persetujuan | `credential_issuances`, `consent_records`, `whatsapp_verifications` | Klaim, aplikasi, dan ruang kerja yang menjadi milik periode; tidak berdasarkan `user_id` saja |
| Kasus administratif | `administrative_cases`, `application_ownership_transfers` | Aplikasi dan klaim target; pertahankan daftar ID sebelum relasi induk dihapus |
| Ruang kerja | `applicant_workspaces`, `applicant_biodata`, `origin_education` | Seluruh ruang kerja dengan periode target, termasuk draf tanpa aplikasi dan pemilik lama |
| Autosave/unggahan berjalan | `applicant_draft_operations`, `applicant_upload_batches`, registri alokasi file yang diusulkan | Seluruh ruang kerja/periode target; alokasi diselesaikan sebelum dibersihkan |
| Pendaftaran | `applications`, `application_reviews`, `application_submissions`, `application_submission_documents` | Seluruh aplikasi periode, revisi, ownership epoch, dan manifest pengiriman |
| Nomor pendaftaran | `admission_sequences` | Periode target |
| Konfigurasi periode | `admission_config_versions`, `document_policy_versions` | Versi milik periode setelah seluruh pemakainya dalam scope dibersihkan |
| Impor | `sk_imports`, `sk_import_rows` | Seluruh impor periode, termasuk gagal, selesai, kedaluwarsa, atau staging yang sudah dipangkas |
| Metadata dokumen | `applicant_documents`, `period_documents` | Periode target; semua status, termasuk `detached_at`, retained baseline, dan menunggu penghapusan |
| Tugas pembersihan file | `document_deletion_tasks` | Kepemilikan periode yang telah dibuktikan; koordinasikan dengan pekerja penghapusan biasa |
| Ekspor tersimpan | `export_runs` dan peta keanggotaan ekspor | Ekspor yang memuat atau dapat memuat data periode target; lihat bagian 3.3 |

### 3.2 File fisik yang dihapus

| Jenis | Sumber lokasi yang sah |
| --- | --- |
| Berkas pendaftar, termasuk riwayat | `applicant_documents.disk/object_key`; lazimnya `applicant-documents/{workspace_id}/...` |
| Berkas periode internal/peserta/publik | `period_documents.disk/object_key`; lazimnya `period-documents/{period_id}/...` |
| Sumber Excel impor | `sk_imports.private_path` pada disk `local` |
| Ekspor yang tersimpan di server | `export_runs.private_path` dan inventaris pekerjaan ekspor |
| File yang sudah kehilangan metadata aktif | Tugas penghapusan atau registri alokasi file yang masih memiliki bukti kepemilikan periode |

Jangan menghapus folder bersama seperti `sk-imports`, `exports`, `applicant-documents`, `livewire-tmp`, atau root disk secara rekursif. File impor dan ekspor dihapus satu per satu berdasarkan lokasi yang telah diinventarisasi. Enumerasi direktori hanya diperbolehkan pada prefix tepat yang terbukti milik satu periode/ruang kerja.

File yang sudah tidak ada dihitung sebagai **sudah tidak tersedia**, bukan kegagalan. Gangguan storage, kegagalan izin, atau hasil pemeriksaan yang tidak pasti tetap menjadi kegagalan yang harus ditangani.

### 3.3 Ekspor gabungan dan pekerjaan yang sedang berjalan

Ekspor gabungan dapat mengandung data periode yang dihapus bersama data periode lain. Rekomendasi: **cabut akses dan hapus artefak ekspor gabungan tersebut secara utuh**, lalu pengelola dapat membuat ekspor baru. Data sumber periode lain tidak dihapus. Ringkasan konfirmasi menyebutkan jumlah ekspor gabungan yang terdampak.

Tambahkan peta keanggotaan periode pada ekspor, misalnya `export_run_periods`. Ekspor baru mencatat periode yang benar-benar masuk ke file sebelum dinyatakan siap diunduh. Untuk ekspor lama tanpa peta tersebut:

- Filter periode eksplisit yang sama dengan target masuk scope penghapusan.
- Filter periode eksplisit lain tetap dipertahankan.
- Ekspor semua periode dianggap berpotensi memuat target dan dicabut secara konservatif. Jangan menebak kepemilikan melalui nama periode dalam CSV.

Job ekspor/impor/unggah yang sudah berjalan harus berhenti atau menyelesaikan pembatalannya sebelum inventaris file dinyatakan final. Job antrean yang belum berjalan diperiksa kembali terhadap penanda penghapusan ketika dieksekusi. Jangan menghapus antrean global atau pekerjaan milik periode lain.

Ekspor Excel yang langsung di-stream tidak memiliki file persisten yang dapat dibersihkan di server. Permintaan baru diblokir setelah periode dibekukan. Salinan yang sudah diunduh ke perangkat, respons unduhan yang telah berjalan, email, atau penyimpanan eksternal berada di luar jangkauan fitur ini. Backup mengikuti SOP retensi sistem dan tidak dihapus otomatis oleh endpoint aplikasi.

### 3.4 Data bersama yang dipertahankan

- Akun administrator/operator dan identitas pengguna global.
- Data, berkas, penugasan, dan akses pada periode lain.
- Dataset wilayah, pilihan biodata, perguruan tinggi, program studi, dan naskah persetujuan global. Snapshot konfigurasi milik periode tetap dihapus.
- Pengaturan portal, tautan cepat, aset aplikasi, logo, dan dokumen global yang tidak dimiliki periode target.
- Catatan audit minimal tentang operasi penghapusan.

Kredensial yang diterbitkan melalui klaim periode target dicabut/dihapus. Akun peserta yang masih mempunyai kepesertaan pada periode lain tetap aktif. Untuk akun peserta tanpa kepesertaan lain, rekomendasi awal adalah menonaktifkan akses dan mencabut sesi/remember token, sambil mempertahankan identitas internal yang masih diperlukan relasi global. Penghapusan identitas pengguna global merupakan operasi tersendiri dengan pemeriksaan dependensi lintas periode.

Audit lama yang terhubung dengan objek target ditinjau dalam inventaris: pertahankan aktor, waktu, jenis tindakan, dan pengenal teknis minimal; redaksi data pribadi spesifik periode dari payload audit bila ada. Jangan menghapus seluruh audit seorang aktor karena aktor tersebut juga bekerja pada periode lain. Ringkasan penghapusan tidak menyimpan NIK, nomor WhatsApp, kata sandi, biodata, atau nama asli file.

## 4. Antarmuka konfirmasi

Tambahkan tombol merah **Hapus Periode** pada kartu periode, hanya untuk administrator. Operator tetap memiliki menu pengelolaan periode yang sudah ada, tetapi tidak memperoleh tombol ataupun endpoint penghapusan.

Saat tombol ditekan, tampilkan dialog responsif dengan:

1. Nama periode, kode, tahun akademik, dan status saat ini.
2. Jumlah peserta SK, klaim aktif/terhapus, ruang kerja/draf, pendaftaran, revisi pengiriman, dokumen peserta, dokumen periode, impor, ekspor gabungan, jumlah file unik, serta perkiraan ukuran file yang diketahui.
3. Keterangan bahwa seluruh data operasional dan file milik periode akan dihapus, sedangkan akun/data bersama dan periode lain tetap dipertahankan.
4. Kolom **Ketik kode periode untuk mengonfirmasi**, harus sama persis dengan kode yang sedang ditampilkan.
5. Centang **Saya memahami bahwa seluruh data dan file periode ini akan dihapus permanen.**
6. Kolom **Kata sandi administrator Anda**, dengan tombol tampil/sembunyikan dan `autocomplete="current-password"`.
7. Tombol **Batal** dan **Hapus Permanen Periode**. Tombol penghapusan tidak boleh menjadi tombol fokus awal.

Gunakan pola dialog aplikasi: `ui-modal-backdrop`, `ui-modal`, focus trap, dukungan Escape sebelum pengiriman, dan pengembalian fokus ke tombol pemicu. Pada ponsel, ringkasan dapat digulir dan tombol disusun vertikal. Tetap tampilkan identitas periode di bagian atas dialog agar pengguna tidak salah sasaran.

Kata sandi wajib diperiksa di server. Administrator dengan akun yang belum memiliki kata sandi lokal harus mengatur kata sandinya melalui keamanan akun; tidak ada jalan pintas memakai identitas Google atau kata sandi administrator lain.

Setelah permintaan diterima, tutup formulir kata sandi dan buka halaman kemajuan. Gunakan istilah **Permintaan diterima**, **Menghapus file**, **Membersihkan data**, **Perlu penanganan**, atau **Selesai** sesuai keadaan sebenarnya.

## 5. Izin, verifikasi kata sandi, dan perlindungan permintaan

- Tambahkan `AdmissionPeriodPolicy::delete()` dengan syarat `User::isAdministrator()`; administrator harus aktif dan tidak sedang diwajibkan mengganti kata sandi.
- Terapkan pemeriksaan di tampilan, route, action, dan awal pekerjaan latar. Jangan mengandalkan tombol tersembunyi atau izin `update` periode.
- Gunakan POST khusus pengajuan operasi, CSRF, pembatasan percobaan, serta verifikasi `current_password:web` terhadap pengguna yang sedang masuk. Rekomendasi awal pembatasan: 5 percobaan per menit per akun dan IP, dapat dikonfigurasi.
- Kirim kata sandi melalui form POST biasa ke Form Request. Jangan membuat properti publik Livewire untuk kata sandi, menyimpannya dalam snapshot komponen, query string, job queue, atau tabel permintaan.
- Gunakan nama field `current_password` dan pastikan field tersebut tidak di-flash sebagai old input serta disensor pada logger, APM, perekam request, dan penanganan exception.
- Pratinjau memiliki token acak sekali pakai yang disimpan sebagai hash, terikat pada administrator, ID periode, revisi data, ringkasan inventaris, dan waktu kedaluwarsa. Rekomendasi masa berlaku token: 10 menit sampai pengajuan diterima.
- Verifikasi kode periode, centang persetujuan, kata sandi, token, dan revisi dilakukan ulang ketika POST diterima. Validasi browser merupakan bantuan antarmuka, bukan batas keamanan.
- Kata sandi salah/kode tidak cocok/token kedaluwarsa tidak boleh membekukan periode, menjadwalkan job, atau menghapus satu file pun.
- Klik ganda dan retry jaringan memakai kunci idempotensi yang sama. Satu periode hanya memiliki satu permintaan penghapusan aktif. Permintaan identik mengembalikan status operasi yang sama.
- Job hanya menerima ID permintaan yang sudah diotorisasi, bukan kata sandi maupun daftar path dari browser. Pekerja memverifikasi status permintaan dan kecocokan periode sebelum bertindak.

## 6. Pembekuan dan perlindungan dari perubahan bersamaan

Usulkan penanda `deletion_request_id` dan `deletion_requested_at` pada periode, terpisah dari enum `PeriodState`. Tambahkan revisi data khusus, misalnya `data_revision`, yang berubah pada setiap mutasi data operasional periode. `lock_version` konfigurasi yang ada tetap menjalankan tugas semula.

Semua jalur tulis harus menggunakan guard periode yang sama dan memeriksanya di dalam transaksi setelah memperoleh `lockForUpdate()`. Cakup tambah/edit/hapus peserta, impor dan konfirmasinya, penugasan, klaim/persetujuan/reset akun/tanggapan, koreksi kepemilikan, formulir/autosave/pengiriman, pemeriksaan, aturan berkas, unggah/rename/delete/publikasi file, dan persiapan ekspor.

Ketika permintaan diterima:

1. Kunci baris periode; periksa ulang izin, token, kata sandi, dan `data_revision` pratinjau.
2. Jika data berubah, batalkan pengajuan dan minta pengguna memuat ringkasan terbaru. Jangan meneruskan diam-diam memakai ringkasan lama.
3. Buat permintaan dan tandai periode sedang dihapus dalam transaksi yang sama.
4. Pembacaan normal dan seluruh perubahan periode diblokir; hanya halaman kemajuan administrator tetap tersedia.
5. Homepage, pilihan pendaftaran akun, Informasi SK, file publik/peserta, pratinjau impor, dan endpoint unduh langsung memeriksa penanda tersebut pada setiap permintaan. Periode lain tetap berjalan.

Tanggal buka/tutup, `is_public`, dan status asli tidak perlu ditimpa untuk membekukan periode. Guard penghapusan mengesampingkan akses operasional sehingga pembatalan sebelum tahap permanen dapat melepas penanda tanpa menebak nilai semula.

### Penulisan file yang masih berlangsung

Tidak cukup hanya menolak penyimpanan metadata setelah periode dibekukan. Beberapa alur yang ada menulis file sebelum transaksi database; file terlambat dapat muncul setelah inventaris diambil.

Sebelum fitur diaktifkan, integrasikan pencatatan alokasi file yang persisten: periode, disk, key tujuan, jenis sumber, dan status penulisan dicatat sebelum I/O. Penempatan file final dan pendaftaran metadata memakai guard serta lock periode. Penulisan staging yang panjang, seperti ekspor, harus mendukung pembatalan dan tidak boleh dipromosikan menjadi file siap unduh setelah pembekuan. Pekerja penghapusan menunggu penulis aktif berhenti dan memasukkan seluruh alokasi yang belum selesai ke inventaris pembersihan.

Jika proses penulis belum dapat dipastikan berhenti, status tetap menunggu/perlu penanganan. Kedaluwarsa lease saja tidak boleh dianggap sebagai bukti bahwa tidak ada lagi proses yang bisa menulis file. File sementara Livewire global tidak dibersihkan dengan menghapus folder bersama; gunakan data kepemilikan yang sah atau pembersihan temporer normal.

## 7. Tahapan eksekusi dan keadaan operasi

| Tahap | Perilaku |
| --- | --- |
| `queued` | Permintaan telah diotorisasi; periode dibekukan; belum ada file dihapus |
| `inventorying` | Hentikan/selesaikan penulis aktif, simpan daftar ID dan file unik beserta scope dan revisi |
| `deleting_files` | Hapus objek fisik bertahap; catat hasil setiap file dan retry yang diperlukan |
| `purging_data` | Sesudah seluruh file terselesaikan, hapus data database dalam transaksi terkontrol |
| `verifying` | Pastikan seluruh relasi target, file manifest, dan pekerjaan tertaut telah bersih |
| `completed` | Tidak ada pekerjaan/file/data target tersisa; periode telah dihapus |
| `needs_attention` | Kegagalan storage, job belum berhenti, scope tidak aman, atau kegagalan database; periode tetap dibekukan |
| `cancelled` | Dapat digunakan hanya sebelum penghapusan file/data permanen dimulai |

Gunakan `dispatch()->afterCommit()` dengan mekanisme pengiriman ulang dari catatan `queued` yang persisten jika antrean sedang bermasalah. Operasi tidak bergantung pada browser tetap terbuka. Pekerja memakai lease/lock eksekusi dan memproses per kelompok agar tidak menahan satu transaksi panjang selama I/O storage.

Periksa bahwa administrator pengaju masih berwenang sebelum tahap permanen dimulai. Jika akses pengaju dicabut atau akunnya tidak tersedia, jeda operasi. Administrator aktif lain dapat mengambil alih melalui konfirmasi kata sandinya sendiri, dengan audit pengambilalihan. Password tidak perlu disimpan untuk menjalankan retry otomatis yang masih berada dalam operasi yang sah.

Pembatalan diperbolehkan sebelum tahap permanen. Setelah tahap `deleting_files` dimulai, kelanjutan/retry menjadi jalur pemulihan; jangan membuka kembali periode yang mungkin sudah kehilangan sebagian file. Tampilan harus menjelaskan keadaan ini tanpa menawarkan pembatalan yang menyesatkan.

## 8. Inventaris file, scope, dan pekerjaan penghapusan yang sudah ada

Simpan manifest sebelum menghapus metadata. Deduplicasi berdasarkan pasangan disk dan key; satu file yang dipakai beberapa revisi hanya dihapus satu kali. Daftar ID peserta, aplikasi, klaim, ruang kerja, revisi, impor, ekspor, dan dokumen tetap tersedia sampai verifikasi selesai.

Untuk storage lokal, periksa bahwa path relatif, tidak mengandung traversal/null byte, dan resolusi akhirnya berada pada root disk serta prefix sumber yang diizinkan. Jangan menerima path dari request pengguna, mengeksekusi perintah shell untuk menghapus file, atau mengikuti symlink keluar dari lokasi yang dikelola. Untuk disk lain, gunakan API adapter dan pencatatan objek yang ekuivalen.

`ProcessDocumentDeletion` yang ada juga menghapus metadata dokumen. Proses penghapusan periode membutuhkan mode file-only: manifest revisi masih dapat memiliki foreign key ke `applicant_documents`. Jangan memakai pekerja lama tanpa penyesuaian hingga metadata dokumen dihapus mendahului manifestnya.

Rekomendasi integrasi:

- Tambahkan `admission_period_id` dan pengenal operasi penghapusan pada tugas file. Informasi periode harus tetap tersedia walaupun metadata dokumen/ruang kerja sudah dihapus.
- Tugas biasa yang telah diambil alih operasi periode tidak diproses bersamaan oleh pekerja lama. Gunakan kepemilikan tugas dan lock file yang konsisten.
- Pekerja operasi periode menghapus objek fisik lalu memperbarui status manifest; penghapusan metadata dilakukan pada tahap database.
- Retry memakai backoff dan pesan kesalahan yang disensor. Setelah ambang retry tercapai, tandai perlu penanganan; jangan melewati file gagal.

Tugas lama tanpa periode perlu direkonsiliasi sebelum peluncuran: isi dari metadata dokumen yang masih ada, lalu selesaikan tugas yatim yang kepemilikannya sudah tidak dapat dipulihkan melalui prosedur pemeliharaan. Jangan menebak periode dari nama orang, rentang waktu, atau kemiripan folder. Jika kepemilikan file yang diduga terkait belum dapat dibuktikan, pratinjau harus menyatakan hambatan dan operasi belum dapat dinyatakan siap.

## 9. Urutan penghapusan database

Seluruh query dibatasi oleh ID yang diinventarisasi. Jangan menonaktifkan foreign key, melakukan `TRUNCATE`, atau memanggil reset data global. Urutan berikut menjadi kontrak implementasi dan harus diuji terhadap schema aktual:

1. Selesaikan pembatalan job target dan pencabutan artefak ekspor; hapus metadata ekspor yang masuk scope beserta peta keanggotaannya; koordinasikan tugas file yang masih aktif.
2. Hapus `application_ownership_transfers`, lalu `application_reviews`, `application_submission_documents`, dan `application_submissions`.
3. Hapus `consent_records`, `whatsapp_verifications`, `credential_issuances`, `claim_responses`, dan `administrative_cases` yang masuk scope setelah pemakai silang dihapus.
4. Hapus semua `sk_claims` target secara permanen, termasuk soft-deleted, serta `applications` target.
5. Hapus metadata `applicant_documents`, `period_documents`, `applicant_draft_operations`, `applicant_upload_batches`, `applicant_biodata`, dan `origin_education`, lalu `applicant_workspaces`.
6. Hapus `assignment_events` dan `sk_participants`; setelah peserta tidak lagi memakai `last_import_id`, hapus `sk_import_rows` dan `sk_imports`.
7. Hapus `period_operators`, `admission_sequences`, `admission_config_versions`, dan `document_policy_versions` target setelah seluruh referensinya selesai.
8. Pastikan tugas file target dan registri alokasi yang sudah diselesaikan telah dibersihkan, rapikan hubungan akses akun khusus periode, dan lakukan redaksi audit spesifik periode sesuai scope.
9. Hapus `admission_periods` target paling akhir. Catat bahwa purge selesai dan lanjutkan verifikasi sesudah commit. Status keseluruhan baru menjadi `completed` setelah verifier berhasil; ringkasan operasi/audit tidak bergantung pada foreign key ke periode yang dihapus.

Purge database dilakukan secara atomik selama ukurannya masih sesuai batas operasional; gunakan query/chunk yang efisien di dalam transaksi tersebut. Ukur durasi dan lock pada data representatif sebelum rilis. I/O file tetap berada di luar transaksi purge.

Preflight harus mendeteksi referensi keluar scope, misalnya workspace berbeda periode yang dipakai aplikasi target, impor target yang dirujuk peserta periode lain, atau versi konfigurasi target yang dipakai aplikasi lain. Hentikan sebelum menghapus file bila ada ketidakkonsistenan. Jangan memperluas scope penghapusan secara otomatis untuk mengejar foreign key.

Pengecualian atas model konfigurasi immutable dilaksanakan hanya di service penghapusan terotorisasi dengan query scoped dan audit. Jangan mematikan event model secara global. Tambahkan pemeriksaan kontrak schema agar tabel baru yang terhubung ke periode wajib masuk inventaris sebelum fitur tetap diizinkan berjalan.

## 10. Struktur yang diusulkan

| Komponen | Tanggung jawab |
| --- | --- |
| `AdmissionPeriodPolicy::delete` | Izin khusus administrator aktif |
| `PeriodDeletionPreview` | Menghitung scope, ukuran, dependensi, revisi, dan hambatan |
| `RequestPeriodDeletionRequest` | Validasi kode, centang, kata sandi, token, dan idempotensi; password tidak di-flash |
| `RequestPeriodDeletion` | Mengunci periode, mengesahkan permintaan, membekukan periode, menyiapkan dispatch |
| `DeleteAdmissionPeriodJob` | Menjalankan tahapan berdasarkan ID operasi; retry/idempotensi |
| `BuildPeriodDeletionManifest` | Inventaris baris dan file, termasuk riwayat dan alokasi yang belum selesai |
| `DeletePeriodFiles` | Penghapusan fisik file-only beserta hasil per objek |
| `PurgePeriodData` | Penghapusan relasi database yang terbatas dan pengecualian immutable yang terkontrol |
| `VerifyPeriodDeletion` | Pemeriksaan sisa dan penentuan status akhir |
| Halaman status administrator | Progres, kegagalan yang aman ditampilkan, dan melanjutkan pekerjaan dengan autentikasi ulang |

Route yang disarankan:

- `GET /admin/periode/{period}/pratinjau-hapus`: ringkasan dan token konfirmasi, administrator saja.
- `POST /admin/periode/{period}/permintaan-hapus`: menerima konfirmasi; hasil sukses berupa operasi berstatus diterima/antre, bukan pesan “seluruh data telah dihapus”.
- `GET /admin/penghapusan-periode/{request}`: progres tetap dapat dibuka setelah periode hilang.
- `POST /admin/penghapusan-periode/{request}/lanjutkan`: konfirmasi ulang kata sandi administrator untuk pemulihan/pengambilalihan manual.
- `POST /admin/penghapusan-periode/{request}/batalkan`: hanya sebelum tahap permanen, administrator terotorisasi.

Tabel tambahan yang disarankan:

- `period_deletion_requests`: UUID operasi, kunci idempotensi, ID/kode/nama periode sebagai snapshot minimal, pengaju, waktu konfirmasi sandi, revisi pratinjau/freeze, tahap, hitungan, lease, waktu mulai/selesai, dan ringkasan kegagalan. ID periode pada catatan audit operasi tidak memakai foreign key wajib ke baris yang akan dihapus.
- `period_deletion_files`: ID operasi, jenis sumber/ID sumber, disk, key, ukuran bila diketahui, status objek, jumlah percobaan, jadwal retry, dan kode kegagalan. Path detail dapat dibersihkan setelah operasi selesai; ringkasan agregat tetap ada.
- Registri alokasi file dan peta periode ekspor untuk menjaga kepemilikan file sebelum metadata final tersedia dan setelah metadata operasional dihapus.

Usulkan satu executor penghapusan periode pada rilis awal agar artefak gabungan tidak diproses bersamaan. Operasi lain di periode berbeda tetap dapat berjalan. Reset global dan pekerjaan destruktif pemeliharaan harus memiliki interlock agar tidak berjalan bersamaan dengan penghapusan periode.

## 11. Keadaan gagal dan pesan pengguna

| Keadaan | Respons yang diharapkan |
| --- | --- |
| Operator/pendaftar memanggil endpoint | 403; tidak ada perubahan |
| Sesi habis/CSRF tidak sah | Minta masuk atau muat ulang; tidak ada perubahan |
| Kata sandi/kode/centang tidak valid | Kesalahan field yang jelas; password kosong kembali |
| Pratinjau berubah/kedaluwarsa | Minta ringkasan baru; tidak menjadwalkan penghapusan |
| Permintaan sama dikirim dua kali | Tampilkan operasi yang sama, tanpa job/manifest ganda |
| Antrean belum tersedia | Status menunggu pengiriman yang persisten dan dapat dipulihkan; jangan menyatakan selesai |
| Sebagian file gagal | Tetap dibekukan; tampilkan jumlah tertunda dan akses melanjutkan |
| Database gagal sesudah file terhapus | `needs_attention`; metadata yang belum dipurge tetap dilindungi, lalu retry tahap database |
| Hak administrator pengaju dicabut | Jeda sebelum kelanjutan; administrator sah dapat mengambil alih dengan kata sandinya sendiri |
| Scope/file tidak dapat dibuktikan | Hentikan dan tampilkan kebutuhan rekonsiliasi; tidak menebak atau menghapus periode lain |

## 12. Pengujian dan kriteria penerimaan

Wajib ada feature test, pengujian job/storage failure, dan pengujian antarmuka untuk:

- Administrator aktif dapat menyelesaikan operasi; operator, pendaftar, tamu, administrator nonaktif, dan akun yang harus mengganti sandi ditolak, termasuk melalui request langsung.
- Password pengguna lain, password lama, password kosong, CSRF salah, kode salah, token yang dipakai akun/periode lain, serta token kedaluwarsa tidak menyebabkan mutasi.
- Password tidak muncul pada HTML respons, old input, session flash, log, snapshot Livewire, payload job, atau tabel operasi.
- Semua kelompok data pada bagian 3 diisi dalam fixture, termasuk draf tanpa aplikasi, klaim soft-deleted, kepemilikan lama, berkas retained/detached, impor yang sudah dipangkas, file tidak ada, serta tugas penghapusan yang tertunda.
- Dua periode berbagi pengguna, dataset, pengelola, dan naskah persetujuan: penghapusan target menjaga data dan akses periode lainnya. Akun administrator pengaju tidak ikut terhapus.
- Ekspor periode target dan ekspor gabungan dicabut; ekspor eksplisit periode lain tetap tersedia.
- File salah prefix, traversal, disk tidak dikenal, symlink keluar root, atau relasi silang terdeteksi sebelum tahap permanen.
- Submit klaim, autosave, unggah, impor, publikasi file, koreksi kepemilikan, serta ekspor yang berlomba dengan freeze tidak menghasilkan data/file baru yang lolos inventaris.
- Penghapusan mencakup file metadata biasa, file riwayat, file periode publik/peserta/internal, sumber impor, ekspor, dan alokasi yang belum selesai.
- Kegagalan storage pada file tengah, putusnya worker, pengiriman job ganda, queue down, kegagalan database, serta pergantian administrator dapat dipulihkan tanpa menghapus objek periode lain.
- Sebelum tahap permanen, pembatalan mengembalikan akses sesuai status semula. Sesudah tahap tersebut, UI hanya menawarkan kelanjutan yang aman.
- Halaman publik, Informasi SK, dan URL file lama menolak akses sejak freeze dan setelah selesai.
- Setelah selesai, seluruh hitungan scope database adalah nol, seluruh file manifest berstatus terhapus/tidak ada, tidak ada job yang dapat membuat ulang data target, dan periode tidak ditemukan lagi.
- Dialog pada ponsel dan desktop mendukung keyboard/focus, nama periode panjang, loading, pengembalian fokus, dan password yang dihapus kembali setelah kegagalan.

Uji performa memakai ukuran periode representatif beserta banyak revisi dan file. Ukur waktu pratinjau, RAM manifest, throughput storage, durasi lock, waktu purge, serta pemulihan setelah gangguan. Jangan menjalankan uji penghapusan pertama pada data produksi.

## 13. Tahapan implementasi

1. **Inventaris dan guard:** validasi seluruh relasi, perbaiki kepemilikan tugas/file lama, tambahkan revisi data, registry alokasi, peta ekspor, dan guard freeze pada seluruh mutator/reader/job.
2. **Izin dan konfirmasi:** policy khusus, preview, token, Form Request password, redaksi log, rate limit, dan dialog responsif.
3. **Pekerja persisten:** manifest, tahapan file-only, dispatch/retry, lease, dan halaman kemajuan.
4. **Purge dan verifikasi:** urutan foreign key, perlindungan data bersama, audit minimal, serta pemeriksaan akhir yang dapat dibuktikan.
5. **Regresi dan operasi:** fixture lengkap dua periode, skenario konkurensi/gagal, QA browser, backup/restore teruji pada lingkungan uji, dan dokumentasi SOP melanjutkan operasi.

Fitur siap digunakan setelah seluruh jalur tulis mematuhi freeze, seluruh sumber file dapat dibuktikan kepemilikannya, kata sandi administrator terverifikasi tanpa tersimpan, dan pengujian penghapusan serta isolasi lintas periode lulus. Rancangan ini tidak menjalankan migrasi, menghapus data, atau mengaktifkan tombol penghapusan.
