# Rancangan pengelolaan berkas pendaftar

Tanggal: **2 November 2026**, zona waktu **Asia/Jakarta**. Status: **rancangan, belum diimplementasikan**.

## 1. Tujuan dan keputusan dasar

Menambahkan pengelolaan berkas di samping pengisian formulir pada `/pendaftar/pendaftaran`. Pendaftar dan pengelola yang berwenang dapat mengunggah beberapa berkas, melihat atau mengunduh, mengubah nama, dan menghapusnya secara permanen. Administrator mengatur jenis file yang diperbolehkan serta informasi daftar berkas yang perlu disiapkan.

Rancangan ini memperluas cakupan dokumen awal yang belum memasukkan unggahan pendaftar. Aturan dan layar berikut merupakan usulan implementasi, bukan gambaran fitur yang sudah tersedia.

| Kebutuhan | Keputusan rancangan |
| --- | --- |
| Pendaftar mengunggah berkas sebebasnya | Bisa menambah banyak berkas dan berkas tambahan di luar daftar panduan; tidak dibatasi satu file per kategori |
| Jenis file diatur admin | Kebebasan mengunggah tetap mengikuti format dan batas ukuran yang ditetapkan admin |
| Unggah bertahap | Tidak harus menyiapkan seluruh daftar atau mengunggah sekaligus |
| Ubah nama | Mengubah nama tampilan dan nama unduhan, tanpa mengubah isi maupun ekstensi file |
| Hapus permanen | Menghapus file fisik dan data berkas dari aplikasi; tidak ada tempat sampah atau fitur pulihkan |
| Pengelola membantu peserta | Operator yang ditugaskan dan administrator mendapat fitur unggah, ubah nama, dan hapus yang sama dengan pendaftar |
| Daftar berkas admin | Menjadi informasi/panduan kelengkapan. Bukan kewajiban baru untuk mengirim formulir atau mendapat keputusan PMB |
| Berkas tambahan | Selalu tersedia pilihan **Berkas lainnya**; kategori panduan boleh tidak dipilih |
| Perubahan berkas | Tidak otomatis mengubah status formulir, keputusan PMB, nomor PMB, maupun data Neo Feeder |

Tidak ada batas jumlah per kategori dalam perilaku awal. Batas ukuran per file dan kapasitas total per pendaftar/periode menjadi pengaturan kapasitas; kapasitas total dapat dinonaktifkan. Angka batas bukan persyaratan akademik. Jika batas diaktifkan, tampilkan nilainya dan sisa kapasitas sebelum pengguna mengunggah.

## 2. Hubungan dengan aplikasi saat ini

Komponen yang sudah ada:

- `ApplicantFormController::index()` dan `portal.application-periods` menampilkan periode serta tombol **Lengkapi Formulir**, **Status & Perbaikan**, dan **Bukti Pendaftaran**.
- `ApplicantWorkspace` menyimpan ruang kerja milik satu akun pada satu periode, termasuk sebelum klaim disetujui.
- `Application` menghubungkan pendaftaran resmi, peserta SK, dan ruang kerja setelah klaim disetujui.
- `ApplicationPolicy`, `SkParticipantPolicy`, dan scope peserta membatasi pengelola pada penugasan yang sah.
- Disk lokal saat ini berada di `storage/app/private` dan tidak dilayani sebagai folder publik.

Berkas ditautkan ke **workspace + pemilik + periode**, bukan hanya akun secara global. Berkas periode A tidak muncul pada periode B. Pendaftaran resmi memakai berkas dari workspace yang sedang terhubung dengannya.

## 3. Hak akses dan waktu pengubahan

Semua akun harus aktif. Setiap daftar, pratinjau, unduhan, unggahan, perubahan nama, dan penghapusan diperiksa di server.

| Aksi | Pendaftar | Operator | Administrator |
| --- | --- | --- | --- |
| Lihat/unduh | Workspace sendiri | Peserta yang menjadi penugasannya | Semua workspace dalam lingkup administrasi aplikasi |
| Unggah/ubah nama/hapus | Workspace sendiri | Peserta yang menjadi penugasannya | Semua workspace dalam lingkup administrasi aplikasi |
| Mengelola format dan batas unggah | Tidak | Tidak | Ya |
| Mengelola daftar panduan berkas | Tidak | Tidak | Ya |

Ketentuan waktu yang diusulkan:

1. Unggah mandiri tersedia pada periode berstatus published, termasuk sebelum klaim disetujui. Workspace dibuat saat unggahan pertama bila belum ada.
2. Berkas boleh dikelola terpisah dari kunci formulir: status terkirim, diverifikasi, atau diterima lengkap tidak otomatis menutup unggahan. Jadwal kirim formulir tidak menjadi batas waktu berkas.
3. Periode archived hanya dapat dibaca dan diunduh; tidak menerima unggah, ubah nama, atau hapus dari layar biasa, termasuk oleh pengelola.
4. Jika peserta ditahan secara administratif atau kepesertaannya tidak aktif, pendaftar hanya membaca berkasnya. Pengelola yang berwenang masih dapat membantu mengelola berkas pada periode yang belum archived; tindakan tetap tercatat.
5. Operator tidak dapat membuka workspace pra-klaim yang belum terhubung ke peserta dalam penugasannya. Administrator dapat melihatnya melalui daftar workspace pendaftar dengan identitas pemilik yang jelas.
6. Pergantian operator langsung mengubah hak akses berkas, termasuk ketika pengguna masih membuka halaman lama.

Koreksi kepemilikan pendaftaran mengikuti pemisahan workspace yang sudah diterapkan aplikasi. **Berkas pemilik lama tidak disalin atau diberikan kepada pemilik baru.** Pendaftaran setelah koreksi memakai berkas workspace pemilik baru. Akses pengelola ke workspace lama diperiksa kembali; administrator dapat memeriksa riwayat melalui jalur administrasi, bukan meneruskannya kepada pemilik baru.

## 4. Rancangan antarmuka

### 4.1 Pendaftar: Pendaftaran Saya

Pada kartu setiap periode di `/pendaftar/pendaftaran`, tambahkan tombol **Berkas Saya** di samping **Lengkapi Formulir**. Tampilkan jumlah file yang tersedia pada periode tersebut. Gunakan agregasi jumlah agar halaman tidak melakukan satu query tambahan untuk setiap kartu.

Halaman **Berkas Saya** berisi:

1. Identitas periode dan status kepesertaan.
2. **Daftar berkas yang disarankan**, berisi judul, petunjuk, dan contoh dari konfigurasi admin. Untuk kategori yang sudah berisi file, tampilkan **Sudah diunggah: N berkas**; kategori lain menampilkan **Belum ada unggahan**.
3. Satu kolom unggah multiple, pilihan kategori opsional dengan nilai awal **Berkas lainnya**, dan tombol **Unggah Berkas**.
4. Keterangan format, ukuran maksimum per file, serta kapasitas yang berlaku.
5. Daftar berkas dengan kolom nama, kategori, jenis/ukuran, waktu unggah, pengunggah, dan aksi.

Kategori berlaku untuk batch unggahan yang sedang dipilih. Pengguna dapat mengunggah batch berikutnya dengan kategori lain. Nama awal mengikuti nama file dari perangkat. Daftar tetap menampilkan siapa yang mengunggah, misalnya **Anda** atau **Operator: Nama Petugas**.

Aksi setiap berkas:

- **Lihat** untuk format yang mendukung pratinjau; format lain menyediakan **Unduh**.
- **Ubah Nama** membuka dialog satu input nama, tombol simpan, dan batal. Ekstensi ditampilkan terpisah dan tidak dapat diedit.
- **Hapus Permanen** membuka konfirmasi yang menyebut nama file dan teks: **“Berkas akan dihapus permanen dan tidak dapat dipulihkan melalui aplikasi.”** Konfirmasi berlaku untuk file itu saja, bukan seluruh batch.

Nama yang sama diperbolehkan karena setiap file memiliki identitas penyimpanan sendiri. Daftar membedakan file melalui ukuran, waktu, kategori, serta pengunggah. Tidak ada penimpaan otomatis ketika nama sama.

Unggah multiple menampilkan progres dan hasil per file. File valid tetap berhasil walaupun file lain gagal; pesan kegagalan menyebut file dan alasannya. Retry hanya mengirim file gagal, bukan menggandakan yang telah berhasil. Setiap file memiliki kunci permintaan agar retry jaringan bersifat idempoten.

### 4.2 Pengelola: berkas milik pendaftar

Tambahkan tab/bagian **Berkas Pendaftar** pada detail pemeriksaan `/pendaftaran/{application}` dan pintasan dari detail peserta SK yang sudah terhubung ke akun. Administrator juga mempunyai daftar workspace untuk membantu pendaftar pra-klaim.

Bagian ini memakai komponen daftar dan unggah yang sama dengan pendaftar, dengan tambahan identitas **pemilik workspace**, periode, nomor PMB bila sudah ada, serta nama petugas yang sedang bertindak.

Petugas dapat mengunggah, melihat, mengunduh, mengubah nama, dan menghapus permanen seluruh berkas workspace yang menjadi lingkupnya, termasuk file yang diunggah peserta atau petugas lain. Pemilik file tetap pendaftar; `uploaded_by` mencatat petugas sebenarnya. File unggahan petugas langsung terlihat oleh pendaftar, tanpa persetujuan tambahan dan tanpa berpura-pura login sebagai pendaftar.

Jika penugasan berubah sebelum tindakan disimpan, server menolak tindakan tersebut. Antarmuka meminta pengguna memuat ulang halaman.

### 4.3 Administrator: aturan dan panduan berkas

Tambahkan menu **Pengaturan Berkas** dengan pemilihan periode. Simpan konfigurasi sebagai versi baru agar perubahan aturan dapat ditelusuri.

Administrator juga dapat membuka **Daftar Unggahan** lintas pendaftar dengan filter periode, nama/nomor PMB, kategori, jenis file, serta pengunggah. Tabel menampilkan pemilik, nama file, ukuran, kategori, waktu, dan petugas/peserta pengunggah; aksinya membuka workspace untuk melihat, mengunggah, mengubah nama, atau menghapus. Gunakan pagination dan pencarian server. Daftar ini menampilkan data unggahan nyata, sedangkan daftar panduan menentukan informasi yang harus ditampilkan kepada pendaftar.

**Aturan unggah**:

| Pengaturan | Perilaku |
| --- | --- |
| Ekstensi yang diizinkan | Pilihan dari format yang didukung aplikasi, misalnya PDF, JPG/JPEG, PNG, DOCX, XLSX; label ini contoh konfigurasi, bukan daftar final kampus |
| Ukuran maksimum per file | Nilai positif, berlaku sama bagi pendaftar dan pengelola |
| Kapasitas total workspace/periode | Opsional; bila kosong tidak ada kuota total aplikasi, tetap mengikuti kapasitas penyimpanan tersedia |
| Petunjuk umum | Informasi yang tampil pada layar unggah kedua peran |

Pemetaan ekstensi ke MIME dikelola oleh aplikasi, bukan input MIME bebas. Administrator tidak dapat menjadikan file executable atau halaman aktif sebagai format unduhan/pratinjau yang dieksekusi server. Validasi memeriksa isi file dan ekstensi; mengubah nama `.exe` menjadi `.pdf` tidak membuatnya sah.

**Daftar panduan berkas**:

- Tambah kategori dengan kode stabil, judul, deskripsi, urutan, dan penanda aktif.
- Ubah judul/petunjuk atau nonaktifkan kategori tanpa menghapus file yang sudah diunggah.
- Contoh isi panduan: ijazah, transkrip, pasfoto, surat tugas. Ini contoh, tidak otomatis menjadi persyaratan resmi.
- Tampilkan daftar aktif dengan urutan yang sama pada layar pendaftar dan pengelola.
- Kategori nonaktif tidak tersedia untuk unggahan baru. File lama tetap dapat dibaca, diubah nama, dan dihapus sesuai hak akses.
- Tidak ada batas satu file per kategori dan tidak ada kewajiban menyelesaikan seluruh daftar sebelum formulir dapat dikirim.

Perubahan format atau batas berlaku pada unggahan berikutnya, termasuk unggahan yang baru difinalisasi setelah konfigurasi berubah. File yang sebelumnya diterima tidak otomatis terhapus atau kehilangan hak unduh. Penurunan kuota tidak menghapus file; unggahan baru ditolak bila tidak ada sisa kapasitas, sedangkan hapus tetap diperbolehkan.

Periode baru menggunakan konfigurasi bawaan aplikasi yang ditampilkan kepada admin dan dapat disalin dari periode lain. Nilai awal yang diusulkan: PDF/JPG/JPEG/PNG, maksimum 10 MB per file, kuota total tidak dibatasi aplikasi. Nilai tersebut merupakan default teknis usulan dan tetap dapat diubah admin. Kebijakan format yang lebih luas diaktifkan melalui layar admin.

## 5. Perilaku unggah dan perubahan nama

### Unggah

1. Periksa aktor, workspace, periode, penugasan, dan aturan aktif.
2. Validasi setiap file: unggahan lengkap, ekstensi, MIME dari isi, ukuran, kategori, dan kunci permintaan.
3. Simpan sementara pada disk privat dengan nama acak yang dibuat server. Abaikan folder atau path dari nama asli.
4. Sebelum finalisasi, kunci workspace, baca ulang aturan aktif, hitung kuota, dan ulangi pemeriksaan otorisasi. Dengan demikian dua unggahan paralel tidak dapat melewati kuota.
5. Pindahkan file ke lokasi permanen dan simpan metadata untuk file tersebut. Status siap baru ditampilkan setelah file dapat dibaca.
6. Jika penyimpanan atau transaksi gagal, bersihkan file yang belum terikat. Pekerjaan pembersihan berkala menghapus unggahan sementara kedaluwarsa dan file yatim yang teridentifikasi aman.
7. Catat hasil setiap file dan kembalikan daftar terbaru. Kegagalan satu file tidak membatalkan file batch lain yang sudah selesai.

Tidak ada konversi isi, kompresi otomatis, OCR, atau penggantian file pada rilis awal. Untuk mengganti isi, pengguna mengunggah file baru dan dapat menghapus file lama secara eksplisit.

### Ubah nama

Pengguna memasukkan nama dasar, maksimal 180 karakter, setelah pemangkasan spasi. Tolak nama kosong, karakter kontrol, separator path, `.` dan `..`. Ekstensi mengikuti ekstensi yang sudah divalidasi saat unggah dan tidak dapat diubah lewat dialog.

Nama asli dipertahankan sebagai metadata asal; nama tampilan terbaru dipakai pada daftar dan header unduhan yang disanitasi. Lokasi fisik dan isi file tetap sama. Periksa versi baris agar dua pengguna tidak diam-diam saling menimpa perubahan.

## 6. Hapus permanen

Penghapusan mempunyai dua tahap teknis karena transaksi database tidak bisa membatalkan operasi filesystem:

1. Setelah konfirmasi, otorisasi ulang, kunci baris berkas, cocokkan versi, dan tandai `deleting` sambil mencatat tugas penghapusan. Sejak commit ini, unduh/pratinjau ditolak untuk semua peran.
2. Hapus objek fisik dan semua turunan yang dibuat aplikasi, bila ada. Jika berhasil, hapus metadata file dan selesaikan tugas. Audit menyimpan kejadian penghapusan, bukan isi berkas.

Untuk disk lokal, jalankan penghapusan langsung setelah transaksi penandaan agar pengguna biasanya menerima hasil saat itu juga. Jika gagal, tampilkan **“Penghapusan belum selesai; sistem akan mencoba kembali.”** Jangan mengklaim berhasil sebelum objek fisik dihapus. Tugas yang sama dapat dicoba ulang secara idempoten; objek yang sudah tidak ada dianggap selesai. Proses terjadwal menangani kegagalan sementara atau proses yang terhenti.

Status `deleting` bukan tempat sampah: file tidak bisa dipulihkan oleh pengguna atau admin. Setelah penghapusan fisik selesai, metadata aktif dihapus. Audit hanya menyisakan ID referensi, aktor, waktu, dan jenis tindakan; tidak menyimpan salinan file, path publik, atau konten sensitif.

Permintaan hapus ulang tidak membuat tugas ganda. Unggah/pratinjau yang sedang berlangsung memeriksa status terakhir sebelum dipublikasikan atau mulai dikirim. Unduhan yang sudah terkirim ke perangkat tidak dapat ditarik kembali.

**Batas penghapusan permanen:** file dihapus dari penyimpanan aktif aplikasi dan turunannya, tanpa fitur restore. Salinan pada perangkat pengguna atau backup infrastruktur yang sudah dibuat berada di luar operasi tombol ini. SOP backup harus mencatat penghapusan dan memastikan file terhapus tidak muncul kembali saat restore; dokumentasikan masa kedaluwarsa backup dan penghapusan versi objek jika penyimpanan beralih ke layanan berversi. Jangan menjanjikan penghapusan seketika dari semua backup.

## 7. Model data yang diusulkan

Tidak mengubah tabel biodata menjadi tempat menyimpan konten file.

| Tabel | Kolom utama dan tanggung jawab |
| --- | --- |
| `document_policy_versions` | `id`, `admission_period_id`, nomor versi per periode, konfigurasi format/ukuran/kuota/petunjuk, daftar kategori dalam JSON dengan kode stabil, `created_by`, `created_at`; versi immutable |
| `applicant_documents` | UUID `id`, `workspace_id`, `owner_user_id`, `policy_version_id`, `category_key` opsional, label kategori saat unggah, nama asli, nama tampilan, ekstensi, MIME hasil pemeriksaan, ukuran byte, disk, object key acak, `uploaded_by`, `status`, `lock_version`, `request_key`, timestamps |
| `document_deletion_tasks` | ID tugas, UUID berkas, disk/object key yang akan dihapus, status percobaan, jumlah percobaan, waktu percobaan berikutnya, aktor peminta; tidak menyimpan isi file dan dihapus setelah tugas selesai |

`admission_period_id` diperoleh dari workspace; bila disalin sebagai kolom untuk indeks, harus dijaga konsistensinya dalam action. Pemilik berkas wajib sama dengan pemilik workspace; pengunggah dapat pendaftar atau petugas. Hubungan ke pendaftaran resmi diperoleh melalui workspace yang saat ini terhubung, bukan hanya `application_id` lama.

Constraint dan indeks:

- Unik `(admission_period_id, version)` pada konfigurasi dan satu referensi versi aktif per periode di pengaturan yang dikunci saat publikasi.
- Unik `(workspace_id, uploaded_by, request_key)` untuk idempotensi unggahan.
- Indeks daftar `(workspace_id, status, created_at)` serta indeks tugas penghapusan menurut status dan waktu percobaan.
- Object key unik. Jangan memakai nama asli sebagai path penyimpanan.
- Foreign key konfigurasi memakai restrict agar aturan yang pernah digunakan tidak hilang.
- Penghapusan workspace/periode tidak boleh diam-diam meninggalkan file fisik melalui cascade database. Gunakan alur penghapusan eksplisit atau restrict.
- Tidak memakai soft delete untuk berkas. Status `deleting` hanya untuk menjamin penyelesaian penghapusan fisik.

## 8. Penyimpanan dan kontrak akses

Gunakan disk privat aplikasi dengan pola lokasi internal `applicant-documents/{workspace_id}/{document_uuid}.{extension}` atau padanannya. Folder tidak berada di `public` dan tidak memerlukan `storage:link`.

Unduh dan pratinjau melewati controller dengan otorisasi terkini. UUID bukan pengganti pemeriksaan hak akses. Jangan membagikan URL publik permanen atau URL yang tetap berlaku setelah operator kehilangan penugasan.

PDF dan gambar raster yang didukung dapat ditampilkan melalui respons privat terkontrol. Format Office diunduh. File HTML, SVG, skrip, atau executable tidak dirender sebagai konten aktif. Respons menggunakan MIME hasil pemeriksaan, nama unduhan yang disanitasi, `nosniff`, dan cache privat/no-store. Preview tidak dibuat dengan menjalankan aplikasi Office atau kode dari file pengguna.

Rute usulan, nama akhirnya mengikuti konvensi repositori:

| Tujuan | Kontrak |
| --- | --- |
| Daftar pendaftar | `GET /pendaftar/berkas/{period}` |
| Unggah pendaftar | `POST /pendaftar/berkas/{period}` |
| Ubah nama pendaftar | `PATCH /pendaftar/berkas/{period}/{document}` |
| Hapus permanen pendaftar | `DELETE /pendaftar/berkas/{period}/{document}` |
| Lihat/unduh pendaftar | `GET /pendaftar/berkas/{period}/{document}/lihat` dan `/unduh` |
| Daftar/unggah pengelola | `GET/POST /pendaftaran/{application}/berkas` |
| Ubah nama/hapus pengelola | `PATCH/DELETE /pendaftaran/{application}/berkas/{document}` |
| Lihat/unduh pengelola | `GET /pendaftaran/{application}/berkas/{document}/lihat` dan `/unduh` |
| Bantuan pra-klaim oleh admin | Rute workspace khusus di bawah `/admin`, dengan pemeriksaan role administrator |
| Pengaturan admin | `GET /admin/aturan-berkas`, publikasi versi melalui `POST` |

Policy berkas memeriksa pemilik/penugasan, kecocokan parent pada URL, status periode, kondisi kepesertaan, dan status berkas. ID dokumen dari peserta lain atau periode lain ditolak dengan respons yang tidak membuka informasi keberadaannya. Semua perubahan memakai CSRF, validasi server, serta pembatasan frekuensi unggah yang proporsional. Batas server/proxy/PHP harus selaras dengan pengaturan ukuran file dan ukuran batch.

## 9. Pembagian implementasi

Ikuti Blade + Bootstrap serta pola controller/action/policy yang sudah digunakan. Komponen Blade untuk uploader dan daftar dipakai ulang pada layar pendaftar dan pengelola. Progres unggah dapat ditambahkan dengan JavaScript yang sudah digunakan proyek; fitur tidak mengharuskan migrasi seluruh halaman ke Livewire.

Action usulan: `UploadApplicantDocument`, `RenameApplicantDocument`, `DeleteApplicantDocument`, dan `PublishDocumentPolicy`. Controller menetapkan workspace dari aktor atau pendaftaran yang diotorisasi; jangan mempercayai `owner_user_id`, path, MIME, atau kuota yang dikirim klien. Job penghapusan dan pembersihan memakai service penyimpanan yang sama dengan action.

Audit mencatat aktor sebenarnya, pemilik/workspace, ID berkas, tindakan, dan hasil. Aktivitas minimal: unggah, ubah nama, hapus permanen, serta publikasi aturan. Jangan merekam isi file, request body biner, atau URL akses pada log. Riwayat audit tetap terpisah dari daftar berkas aktif dan tidak menyediakan tombol pemulihan.

## 10. Tahapan dan kriteria penerimaan

1. **Fondasi:** migrasi metadata/konfigurasi/tugas, disk privat, policy, aturan bawaan, serta pengaturan admin per periode.
2. **Pendaftar:** tombol Berkas Saya, unggah bertahap/multiple, daftar, pratinjau/unduh, ubah nama, dan hapus permanen.
3. **Pengelola:** tab berkas pada pemeriksaan dan peserta, kemampuan identik, serta dukungan admin untuk workspace pra-klaim.
4. **Keandalan:** kuota paralel, retry per file, pembersihan file sementara/yatim, penyelesaian penghapusan, serta audit.
5. **Verifikasi:** feature test, pemeriksaan antarmuka desktop/ponsel, keyboard, dan pengujian filesystem sebenarnya pada lingkungan uji.

Skenario penerimaan wajib:

- Pendaftar mengunggah beberapa file, menambah file berikutnya, memakai kategori panduan atau Berkas lainnya, serta tidak dibatasi satu file per kategori.
- Satu file invalid dalam batch tidak menggandakan atau menghilangkan file valid; retry memakai kunci permintaan yang sama.
- Dua file bernama sama tetap tersimpan terpisah; ubah nama tidak mengubah isi, ekstensi, atau path fisik.
- Hapus berhasil menghilangkan metadata aktif dan objek fisik. File tidak bisa dibuka melalui URL lama dan tidak ada restore.
- Kegagalan storage saat hapus tidak ditampilkan sebagai sukses; file tidak dapat diunduh saat `deleting`, dan retry akhirnya menyelesaikan tugas.
- Operator dapat mengunggah, mengganti nama, dan menghapus file peserta yang ditugaskan. Peserta melihat hasilnya dan petugas tercatat sebagai pengunggah/aktor.
- Operator tanpa penugasan, operator yang baru dicabut penugasannya, akun nonaktif, dan pendaftar lain ditolak pada setiap endpoint, termasuk unduh/pratinjau langsung.
- Pemilik baru setelah koreksi kepemilikan tidak memperoleh file pemilik lama. Berkas workspace target tetap tersedia bagi pemiliknya.
- Dua unggahan paralel tidak melewati kuota; pemalsuan ekstensi/MIME, nama path, file kosong, dan ukuran berlebih ditolak.
- Perubahan format/kuota/kategori tidak menghapus file lama. Penurunan kuota hanya menahan unggahan baru sampai cukup ruang.
- Admin dapat menyusun daftar panduan dan aturan per periode; operator tidak dapat mempublikasikan aturan.
- Unggah/hapus berkas tidak mengubah status formulir atau keputusan PMB. Daftar panduan tidak menjadi syarat submit tanpa perubahan produk yang terpisah.
- Periode archived dapat dibaca tetapi tidak diubah melalui endpoint biasa, termasuk dengan memalsukan request.

## 11. Batas rilis awal

Rilis awal belum mencakup penilaian valid/tidak valid per berkas, tanda tangan digital, OCR, sinkronisasi Neo Feeder, unggah folder, ZIP massal, penggantian isi di tempat, versioning isi, atau tempat sampah. Daftar dokumen resmi kampus dan konfigurasi operasionalnya diisi admin; rancangan tidak mengarang persyaratan wajib akademik.

Hasil akhir implementasi harus menyediakan empat hal yang dapat diperiksa: **pengaturan admin**, **halaman berkas pendaftar**, **halaman berkas pengelola**, dan **penghapusan permanen yang benar-benar menyelesaikan penghapusan fisik**.

## 12. Unggah pengelola tanpa pembatasan status formulir

Administrator dan operator aktif yang ditugaskan dapat menambah berkas dari halaman pemeriksaan pendaftaran pada status Draf, Terkirim, Diverifikasi, Perlu perbaikan, maupun Diterima lengkap. Izin unggah diperiksa secara terpisah dari izin mengubah nama atau menghapus berkas. Status dan keputusan pendaftaran serta manifest pengiriman sebelumnya tetap dipertahankan; berkas tambahan muncul pada daftar berkas ruang kerja dengan pengunggah dan audit tercatat.

Aturan format, ukuran, kuota, akses akun, penugasan operator, serta pembatasan periode arsip tetap berlaku. Pendaftar tetap mengikuti pembatasan status formulir. Pengujian mencakup unggah Livewire oleh kedua peran pada kelima status serta penolakan pendaftar terkunci, operator tanpa penugasan, dan periode arsip.
