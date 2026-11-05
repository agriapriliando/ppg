# Rancangan formulir, unggah berkas, dan penyimpanan draf otomatis

Tanggal rancangan: **5 November 2026**, zona waktu **Asia/Jakarta**. Status: **baseline rancangan; implementasi tersedia**. Lihat [perilaku aktual, penyesuaian teknis, dan batas verifikasi](../implementasi/formulir-berkas-autosave.md).

## 1. Rekomendasi utama

Jadikan pengisian biodata dan unggah berkas sebagai **satu alur pendaftaran bertahap**, dengan dua halaman yang saling terhubung. Formulir menyimpan draf otomatis; halaman berkas menjadi tahap terakhir dan satu-satunya tempat tombol **Kirim Pendaftaran**. Pengiriman mencatat biodata, riwayat pendidikan, pernyataan, dan daftar berkas dalam satu revisi pendaftaran.

Tidak perlu menyatukan semua isian dan unggahan ke dalam satu halaman panjang. Dua halaman lebih ringan di ponsel, memisahkan proses unggah dari autosave, dan mempertahankan alamat halaman yang sudah digunakan peserta.

| Bagian | Rekomendasi |
| --- | --- |
| Formulir | Simpan otomatis setelah pengguna berhenti mengetik selama **700 ms** |
| Pilihan, checkbox, tanggal | Simpan segera setelah nilainya berubah |
| Berpindah bagian/halaman | Selesaikan penyimpanan terakhir dahulu; jangan membuang antrean debounce |
| Unggah | Progres dan hasil per file; selesai unggah tidak berarti pendaftaran terkirim |
| Pengiriman | Hanya di halaman berkas, setelah ringkasan dan persetujuan |
| Draf belum lengkap | Tetap dapat disimpan, termasuk angka/tahun yang belum selesai diketik |
| Gagal koneksi | Status gagal terlihat, isian tetap di layar, tersedia **Coba simpan lagi** |
| Data berubah di tab lain | Hentikan penimpaan otomatis dan tampilkan konflik |
| Setelah terkirim | Biodata dan daftar berkas yang dikirim menjadi satu revisi yang dapat diperiksa |

Makna **disatukan** adalah satu perjalanan pengguna, satu status, dan satu tindakan pengiriman; bukan satu request yang mengangkut biodata beserta seluruh file sekaligus.

## 2. Dasar dari kode yang tersedia

| Sumber | Kondisi saat ditelaah | Implikasi |
| --- | --- | --- |
| [Formulir](../../resources/views/portal/application-form.blade.php) | Blade dan Alpine, tombol simpan draf, ringkasan, persetujuan, dan kirim di halaman formulir | Formulir perlu memakai Livewire untuk autosave; persetujuan dan kirim dipindahkan |
| [Controller formulir](../../app/Http/Controllers/ApplicantFormController.php) | GET menyiapkan data dan POST menerima `draft` atau `submit` | Route lama harus dimigrasikan tanpa menyediakan jalur pengiriman lama yang melewati pemeriksaan berkas |
| [SaveApplicantForm](../../app/Actions/Admissions/SaveApplicantForm.php) | Menyimpan biodata dan pendidikan; menangani validasi final, revisi, consent, snapshot, nomor versi | Jangan panggil langsung dengan satu kolom dari autosave: loop penyimpanan memberi `null` untuk banyak kolom yang tidak ada dalam payload |
| [ApplicantFields](../../app/Support/ApplicantFields.php) | Validasi biodata serta pendidikan S1/S2/S3; mode draf masih memvalidasi format field | Belum cocok untuk semua nilai yang masih diketik, misalnya NIK `123` atau tahun `20` |
| [Halaman berkas](../../resources/views/portal/applicant-documents.blade.php) | Terpisah dan memakai `documents.document-manager` | Komponen unggah dapat dipertahankan sebagai komponen anak |
| [DocumentManager](../../app/Livewire/Documents/DocumentManager.php) | Livewire, unggah sementara, proses simpan file, progres dan operasi berkas | Pisahkan selesai transfer sementara dari keberhasilan penyimpanan permanen |
| [Kebijakan akses berkas](../../app/Policies/ApplicantDocumentPolicy.php) | Pendaftar dapat mengubah berkas pada periode published walaupun formulir sudah terkirim | Rekomendasi baru harus mengubah kebijakan ini secara eksplisit |
| [Dokumen dan policy](../../app/Models/ApplicantDocument.php), [versi policy](../../app/Models/DocumentPolicyVersion.php) | File berstatus `available`/`deleting`, disimpan privat; versi policy tersimpan | Pakai kembali, jangan membuat layanan unggah kedua |
| [Workspace](../../app/Models/ApplicantWorkspace.php) | Ada `lock_version`, `saved_at`, relasi biodata, pendidikan, dan berkas | Menjadi batas kepemilikan dan konkurensi |
| [Status aplikasi](../../app/Enums/ApplicationStatus.php) | Hanya `draf` dan `perbaikan` yang editable | Autosave mengikuti batas tersebut; tidak membuat status aplikasi baru |

Dokumen ini melanjutkan [rancangan berkas sebelumnya](10-pengelolaan-berkas-pendaftar.md). Keterangan implementasi di dokumen lama adalah catatan pada saat dokumen itu dibuat; tabel di atas berasal dari kode saat telaah ini.

## 3. Alur peserta dan rancangan layar

### 3.1 Navigasi bersama

Gunakan judul **Pendaftaran PPG** dengan penunjuk tahap yang sama pada kedua halaman:

`1. Biodata → 2. Berkas & Kirim → 3. Status Pendaftaran`

Halaman formulir tetap membagi isian menjadi Identitas, Alamat, Tambahan, Pendidikan, dan Ringkasan. Pendidikan terakhir tetap mengatur riwayat lengkap: S1; S1–S2; atau S1–S3. Autosave berlaku juga pada seluruh kolom pendidikan tersebut.

### 3.2 Halaman formulir

1. Tampilkan periode, status draf, dan status penyimpanan di atas isian.
2. Di setiap bagian, gunakan **Kembali** dan **Selanjutnya**. Perpindahan menunggu penyimpanan terakhir selesai.
3. Pada Ringkasan, tampilkan empat kelompok biodata dan tombol **Lanjut ke Berkas**.
4. Hapus tombol **Kirim Pendaftaran** dan modal persetujuan final dari halaman ini.
5. Sediakan **Simpan sekarang** sebagai cadangan, bukan kewajiban peserta setiap selesai mengetik.
6. Jika biodata belum lengkap, peserta tetap boleh menuju berkas. Tampilkan jumlah isian belum lengkap, tetapi jangan memaksa peserta mengisi semua bagian sebelum mulai unggah.

### 3.3 Halaman Berkas & Kirim

Urutan isi dari atas ke bawah:

1. Identitas periode dan ringkasan kesiapan: **Biodata lengkap / Ada isian perlu diperbaiki** dan jumlah file tersimpan.
2. Ringkasan biodata singkat dengan tautan **Perbaiki Biodata**. Tampilkan seluruh riwayat pendidikan yang relevan.
3. Panduan berkas dari policy periode: format, ukuran, kapasitas, serta daftar anjuran.
4. Komponen unggah yang sudah ada, dengan progres dan hasil per file.
5. Ringkasan **Berkas yang akan dikirim**, hanya mencakup file yang sudah tersimpan dan tersedia.
6. Tombol **Kembali ke Biodata** dan **Periksa & Kirim Pendaftaran**.
7. Tombol terakhir membuka modal konfirmasi responsif: ringkasan, daftar berkas, teks persetujuan yang berlaku beserta versinya, checkbox persetujuan, dan **Kirim Pendaftaran**.

Di ponsel, gunakan satu kolom dan tombol lebar penuh. Area aksi boleh melekat di bawah, dengan ruang untuk keyboard dan safe area, tanpa menutupi file atau pesan kesalahan. Status penyimpanan menggunakan teks dan ikon selain warna serta `aria-live="polite"`.

### 3.4 Setelah berhasil dikirim

- Arahkan ke Status Pendaftaran; tampilkan nomor PMB, waktu kirim, revisi, dan jumlah berkas pada revisi tersebut.
- Biodata serta berkas revisi yang dikirim dapat dibaca, tetapi tidak diubah dalam proses pemeriksaan biasa.
- Jika operator meminta perbaikan, buka kembali bagian draf dan berkas; tombol di tahap terakhir berubah menjadi **Kirim Ulang Perbaikan**.
- Waktu daftar pertama tidak berubah. Pengiriman ulang menambah revisi dan waktu pengiriman ulang seperti perilaku saat ini.

## 4. Kelengkapan berkas: jangan menambah kewajiban secara diam-diam

Policy saat ini mengatur format, ukuran, kapasitas, dan panduan; belum ada kontrak kategori wajib untuk mengirim pendaftaran. Oleh karena itu, pada rilis awal rancangan ini:

- Kategori dalam panduan tetap bersifat anjuran. Jangan menebak kewajiban dari nama kategori atau mengharuskan semua kategori terisi.
- Jika belum ada file, tampilkan **Belum ada berkas tersimpan. Periksa kembali panduan sebelum mengirim.** Peserta tetap dapat melanjutkan setelah konfirmasi; tidak menambahkan larangan baru yang belum ditetapkan pengelola.
- File yang sedang dipilih/diunggah bukan file tersimpan. Pengiriman menunggu file berhasil disimpan atau peserta membatalkan/mengeluarkan file gagal dari antrean.
- File gagal tidak boleh menghilang dari perhatian peserta ketika kirim. Peserta harus memilih **Coba lagi** atau **Keluarkan dari antrean**.
- Jika kemudian berkas wajib diberlakukan, tambahkan aturan eksplisit dan berversi di policy, pemeriksaan server, pesan per kategori, serta snapshot versi persyaratan. Ini pengembangan lanjutan yang memerlukan keputusan pengelola.

## 5. Kontrak autosave

### 5.1 Waktu dan pemicu

- Teks, textarea, nomor, dan tahun: `wire:model.live.debounce.700ms` sebagai titik awal.
- Select, checkbox, tanggal: pembaruan pada perubahan nilai, misalnya `wire:model.change.live` setelah diverifikasi terhadap versi terpasang.
- Saat blur, pindah bagian, **Simpan sekarang**, dan **Lanjut ke Berkas**: paksa kirim perubahan yang masih menunggu.
- Gunakan antrean satu request simpan aktif per formulir. Perubahan selama request berjalan masuk batch berikutnya.
- Jangan mengganti nilai yang sedang diketik dengan respons lama. Balasan hanya mengakui versi dan nomor urut batch yang dikirimnya.
- Default Indonesia, Kristen, Tidak menerima KPS, dan Tidak ada kebutuhan khusus harus ikut tersimpan saat inisialisasi draf baru; draf atau isian lama tetap lebih utama.

Modifier Livewire hanya menyinkronkan properti ke server. Penyimpanan database tetap membutuhkan hook/action dan status hasil yang jelas.

### 5.2 Bentuk pembaruan

Gunakan action usulan `SaveApplicantDraftPatch` yang menerima:

```text
workspace_id (dibatasi oleh sesi dan kepemilikan, bukan dipercaya dari browser)
expected_version
client_batch_id (UUID; dipakai ulang ketika request yang sama dicoba kembali)
changes = { path field yang berubah: nilai baru }
```

Path harus berada dalam allowlist, termasuk `education_history.S1.pt_name` dan padanannya hingga S3. Field yang tidak dikirim **tidak diubah**. Pengosongan field dikirim secara eksplisit sebagai nilai kosong. Validasi ukuran payload, tipe, panjang, kedalaman array, dan jumlah key tetap berlaku.

Perubahan saling bergantung dikirim satu batch: pilihan kecamatan beserta reset/turunan wilayah; pilihan KPS dan nomor KPS; pendidikan terakhir dan perubahan jenjang yang relevan. Jangan hapus data S2/S3 saat dropdown tidak sengaja berubah; pertahankan di draf selama pengisian, tetapi keluarkan dari data kanonis/snapshot final jika jenjang akhirnya lebih rendah. Tampilkan penjelasan sebelum pengiriman jika ada riwayat yang akan dikeluarkan.

### 5.3 Draf dan validasi final dipisahkan

**Rekomendasi penyimpanan:** tambahkan `draft_payload` terenkripsi dan nullable pada workspace. Isi awal berasal dari data biodata/pendidikan yang sudah ada, lalu diperbarui melalui patch. Gunakan `lock_version` workspace untuk versi draf dan `saved_at` untuk waktu konfirmasi server.

Draf boleh menyimpan nilai sementara seperti NIK `123`, tahun lulus `20`, serta pendidikan S2 yang S1-nya belum lengkap. Jangan memasukkan nilai tersebut langsung ke kolom bertipe tanggal/integer atau tabel biodata final. Validasi format ringan dilakukan setelah blur; nilai yang belum valid tetap tersimpan sebagai draf dengan pesan **Tersimpan sebagai draf; perlu diperbaiki sebelum dikirim**.

Saat kirim final, baca draf lengkap dari database, jalankan seluruh aturan `ApplicantFields::validate(..., true)`, normalisasi, lalu materialisasikan biodata dan pendidikan beserta riwayat S1–S3 ke tabel yang ada. Aturan urutan tahun dan kewajiban tiap jenjang tetap berlaku pada validasi final.

Buat pembaca data bersama, misalnya `ApplicantDraftData`: pada status draf/perbaikan gunakan draft efektif untuk pengisian, ringkasan peserta, dan pratinjau staf yang berwenang. Untuk revisi yang sudah dikirim, pemeriksaan/cetakan memakai snapshot revisi tersebut. Jangan membuat sebagian layar membaca draf baru sementara bagian lain menampilkan data lama tanpa label.

Efek samping seperti menyinkronkan nomor WhatsApp ke klaim/tujuan akun hanya dijalankan ketika nomor lengkap telah valid dan benar-benar berubah, melalui operasi domain yang sudah diperiksa. Nomor parsial yang masih diketik tidak boleh mengganti tujuan pengiriman akun. Audit autosave tidak memuat NIK, nama ibu, atau isi payload mentah.

### 5.4 Status penyimpanan yang terlihat

| Keadaan | Teks kepada peserta | Perilaku |
| --- | --- | --- |
| Belum ada perubahan | Semua perubahan tersimpan | Tampilkan waktu simpan terakhir |
| Menunggu debounce | Ada perubahan belum tersimpan | Isian tetap dapat diedit |
| Request berjalan | Menyimpan… | Jangan memblokir pengetikan |
| Tersimpan, belum valid | Draf tersimpan. Ada isian perlu dilengkapi | Tampilkan pesan per field setelah blur |
| Koneksi gagal | Belum tersimpan. Periksa koneksi lalu coba lagi | Pertahankan antrean dan tombol Coba simpan lagi |
| Konflik versi | Data diperbarui dari tab/perangkat lain | Hentikan autosave; tampilkan perbedaan dan pilihan muat ulang/gabung terarah |
| Sesi berakhir | Sesi berakhir. Masuk kembali untuk menyimpan | Jangan mengklaim perubahan sudah tersimpan |

Retry jaringan memakai batch ID yang sama dan jeda terbatas, misalnya 1, 2, dan 5 detik, dengan jitter. Jangan mencoba ulang otomatis pada 403, konflik versi, atau validasi struktur payload.

Rilis awal tidak menyimpan biodata sensitif ke `localStorage`/`sessionStorage`. Isian yang belum tersimpan hanya berada di memori tab; penutupan mendadak sebelum server mengakui penyimpanan masih dapat menghilangkannya. Peringatan keluar hanya muncul saat ada perubahan belum tersimpan. Jangan menjanjikan penyimpanan saat browser ditutup, dan jangan mengandalkan `beforeunload` atau beacon sebagai satu-satunya mekanisme.

## 6. Konkurensi dan pengiriman final

### 6.1 Versi dan retry

- Patch memeriksa `expected_version` dalam transaksi; sukses menaikkan `lock_version` sekali per batch.
- Simpan hasil batch pada tabel usulan `applicant_draft_operations`: unique `(workspace_id, client_batch_id)`, digest payload, versi hasil, dan waktu. Simpan metadata, bukan isi field.
- Retry batch yang sama mengembalikan hasil yang sama; UUID sama dengan isi berbeda ditolak. Tetapkan pembersihan metadata setelah jendela retry yang terdokumentasi, misalnya 24 jam.
- Jangan memakai last-write-wins untuk dua tab. Pertahankan perubahan lokal dan beri opsi membandingkan data server sebelum penggabungan.
- Tambahkan `documents_version` terpisah pada workspace, dinaikkan saat file tersedia, diubah nama, dilepas, atau dihapus. Perubahan unggahan tidak menimbulkan konflik autosave biodata yang tidak berkaitan.

### 6.2 Urutan finalisasi

1. Selesaikan autosave yang tertunda dan pastikan hasilnya diakui server.
2. Tunggu seluruh unggahan dan operasi file yang dipilih selesai. Selama modal final terbuka, jedakan aksi pengubahan file pada halaman tersebut.
3. Ambil ringkasan server yang memuat versi draf, versi daftar berkas, versi policy/referensi, dan versi consent. Tampilkan data yang benar-benar akan dikirim.
4. Peserta mencentang persetujuan lalu mengirim dengan `submission_request_id` yang tetap untuk retry.
5. Action usulan `SubmitApplicantRegistration` mengunci baris, memeriksa ulang kepemilikan, status, jadwal/koreksi, hold, versi draf, versi dokumen, referensi, dan consent.
6. Tolak jika versi berbeda dari ringkasan; minta peserta meninjau ulang. Jangan mengirim dokumen yang berbeda dari yang baru dikonfirmasi.
7. Validasi draf lengkap, materialisasikan biodata/pendidikan, dan snapshot manifest dokumen. Catat persetujuan serta satu `ApplicationSubmission`, naikkan revisi, lalu ubah status menjadi `terkirim` secara atomik.
8. Balasan hilang atau klik ganda dengan request ID yang sama mengembalikan pengiriman sebelumnya; tidak menciptakan revisi/persetujuan ganda. Pastikan payload berbeda tidak dapat memakai request ID lama.

Gunakan urutan kunci yang sama pada action simpan, unggah/finalisasi, ubah nama, hapus, dan kirim. Usulan urutan untuk operasi workspace: periode → workspace → aplikasi → peserta → dokumen menurut ID. Kode saat ini belum seragam: simpan formulir mengunci periode lebih dahulu, sementara unggah mengunci workspace lalu periode. Urutan ini perlu diselaraskan, bukan hanya di action baru.

Request autosave terlambat setelah pengiriman harus ditolak dan tidak boleh membuka kembali status. Pemeriksaan final berlaku di server; menonaktifkan tombol di browser saja tidak cukup.

### 6.3 Unggahan yang sedang berjalan

Event `livewire-upload-finish` hanya bukti transfer sementara selesai; tombol kirim menunggu action penyimpanan privat berhasil dan file berstatus `available`. File `deleting`, sementara, atau gagal tidak masuk manifest.

Tambahkan pencatatan batch unggah server bila prosesnya dapat berjalan terpisah dari request layar: batch ID, workspace, status, waktu kedaluwarsa, serta hasil per file. Registrasikan batch sebelum transfer; finalisasi upload memeriksa ulang status pendaftaran di bawah kunci yang sama. Pengiriman menolak batch yang masih aktif atau belum dibereskan. Batch terputus harus bisa dibatalkan/kedaluwarsa, tanpa membuat tombol kirim terkunci selamanya. File yang selesai terlambat tidak boleh masuk diam-diam ke revisi yang sudah dikirim.

Jangan menjalankan transfer file atau membaca hash file besar sambil menahan transaksi final. Hash dan penyimpanan privat diselesaikan sebelum transaksi metadata; kegagalan finalisasi ditangani oleh mekanisme pembersihan file yatim.

## 7. Riwayat dokumen dan perubahan aturan lama

**Rekomendasi:** bekukan daftar dan isi berkas pada setiap revisi yang dikirim. Tambahkan relasi `application_submission_documents` yang menyimpan submission, document/blob ID, nama saat dikirim, ukuran, MIME, hash SHA-256, serta policy version ID.

Hash file dapat dihitung satu kali ketika unggah permanen berhasil. File fisik tidak perlu disalin setiap kirim; beberapa revisi dapat merujuk blob immutable yang sama. File pengganti memakai ID/blob baru.

Konsekuensi yang harus diterapkan bersamaan:

- Berkas yang belum pernah dikirim tetap mengikuti hapus permanen yang sudah ada.
- Berkas yang telah dirujuk revisi tidak boleh dihapus fisiknya melalui aksi biasa. Saat perbaikan, peserta dapat **melepas dari draf perbaikan** atau mengganti file; revisi lama tetap dapat ditinjau pengelola.
- Ubah nama pada draf perbaikan tidak mengubah nama dalam snapshot revisi terdahulu.
- Purge fisik blob yang sudah dikirim mengikuti kebijakan retensi/penghapusan khusus; jangan mengarang durasi retensi institusi dalam implementasi.
- Policy akses dan action hapus harus memeriksa referensi submission di server; tampilan tombol saja tidak melindungi riwayat.
- Pemilik baru setelah koreksi kepemilikan tidak memperoleh file pemilik lama. Riwayat dan akses blob tetap mengikuti ownership epoch dan otorisasi yang berlaku.

Ini perubahan eksplisit dari rancangan 10 yang membolehkan pengelolaan file setelah pengiriman secara terpisah. Alasannya: setelah tombol kirim mewakili biodata **dan** berkas, operator harus bisa memastikan file mana yang sebenarnya disetujui peserta pada revisi tersebut.

## 8. Matriks status

| Status/kondisi | Autosave biodata | Berkas draf | Kirim final |
| --- | --- | --- | --- |
| Belum ada klaim disetujui, periode published | Boleh untuk workspace sendiri | Boleh sesuai policy | Tidak; tampilkan penjelasan klaim belum disetujui |
| Draf, kepesertaan aktif | Boleh | Boleh | Boleh jika jadwal dan validasi terpenuhi |
| Terkirim/diverifikasi | Tidak | Baca revisi yang dikirim | Tidak membuat kiriman baru |
| Perlu perbaikan | Boleh | Tambah/ganti/lepas dari draf perbaikan | Boleh sesuai jadwal koreksi dan validasi |
| Diterima lengkap | Tidak | Baca | Tidak |
| Hold/kepesertaan tidak aktif | Tidak untuk peserta | Baca untuk peserta | Tidak |
| Periode archived | Tidak | Baca/unduh sesuai hak | Tidak |
| Jadwal berakhir tetapi periode published | Draf masih boleh disimpan, mengikuti perilaku sekarang | Draf boleh dikelola sesuai policy | Tidak, kecuali jendela koreksi yang sah |

Pengelola tidak mengirim pendaftaran sambil berpura-pura menjadi peserta. Bantuan pengubahan file selama pemeriksaan membutuhkan pembukaan perbaikan atau alur administratif yang dicatat. Pengiriman final tetap dilakukan peserta beserta persetujuannya.

## 9. Peta implementasi dan migrasi

Nama action/komponen berikut adalah usulan, bukan berkas yang sudah tersedia.

| Area | Pekerjaan |
| --- | --- |
| Formulir | Komponen Livewire mengikuti konvensi proyek; allowlist properti; batch patch; status autosave; pendidikan S1–S3; flush saat navigasi |
| Draf | `draft_payload` encrypted pada workspace; ledger idempotensi; seed dari data lama; pembaca draft efektif; versi optimistic lock |
| Domain simpan | Pisahkan simpan patch dari materialisasi biodata/pendidikan; jangan membuat versi baru yang menyalin semua logika `SaveApplicantForm` |
| Pengiriman | Action final bersama untuk pengiriman awal dan koreksi; pemeriksaan dokumen, idempotensi, consent, snapshot |
| Halaman berkas | Ringkasan kesiapan, daftar file final, modal consent, tombol kirim tunggal; integrasikan event dengan `DocumentManager` |
| Policy dan berkas | Selaraskan kunci, versi dokumen, status setelah kirim, batch upload, manifest, guard hapus fisik |
| Database | Migrasi nullable dan bertahap untuk payload draf; `documents_version` default 0; ledger patch/request final; manifest dokumen serta hash/blob reference |
| Route | Pertahankan GET formulir dan berkas. Route submit lama mendelegasikan ke action final yang sama atau ditolak dengan arah ke halaman berkas; jangan meninggalkan bypass |
| Staf | Detail pemeriksaan menampilkan snapshot biodata dan berkas revisi yang sama; draf perbaikan diberi label dan tidak dianggap kiriman baru |
| Keluaran | Perbarui dashboard, status, bukti, cetakan, ekspor pendidikan S1–S3, audit, dan panduan peserta/pengelola |

Migrasi data:

1. Jangan mengubah status, waktu kirim, nomor PMB, atau persetujuan pendaftaran lama.
2. Workspace editable tanpa payload diinisialisasi dari data tersimpan saat pertama dibuka/disimpan melalui action yang memeriksa versi. Tidak menimpa payload yang sudah ada.
3. Submission lama tanpa manifest diberi label **Riwayat sebelum pengiriman berkas disatukan**. Jangan mengklaim daftar file sekarang adalah daftar pada waktu pengiriman lama.
4. Tentukan perilaku penguncian file untuk pendaftaran lama saat cutover; rekomendasi: daftar file saat ini dibekukan sebagai baseline cutover dengan waktu tersendiri, bukan dipalsukan sebagai snapshot historis.
5. Periksa jalur reset data, koreksi kepemilikan, purge file, serta rollback agar relasi manifest tidak menimbulkan FK error atau kebocoran akses.
6. Rilis memakai feature flag per lingkungan. Rollback menonaktifkan alur baru sambil mempertahankan payload/snapshot; jangan langsung menghapus kolom berisi draf pengguna.

## 10. Tahapan pekerjaan dan penerimaan

### Tahap A — simpan draf yang aman

Pisahkan action patch, draft efektif, ledger retry, validasi sementara, dan indikator autosave. Uji tidak ada field hilang sebelum mengubah lokasi tombol kirim.

### Tahap B — pengiriman bersama

Siapkan action final, manifest dan kebijakan berkas immutable, pengendalian upload berjalan, serta transaksi konsisten. Semua jalur pengiriman harus memakai kontrak yang sama.

### Tahap C — antarmuka dan keluaran

Pindahkan CTA dan consent, perbarui navigasi, status, layar staf, cetakan, ekspor, serta panduan. Uji ponsel dan koneksi lambat sebelum mengaktifkan flag.

Kriteria penerimaan minimum:

| Skenario | Hasil yang harus dibuktikan |
| --- | --- |
| Mengetik lalu diam >700 ms | Draf tersimpan; waktu/status berasal dari konfirmasi server |
| Mengubah satu kolom | Kolom lain dan riwayat S1–S3 tidak hilang |
| NIK/tahun belum selesai diketik | Nilai tetap dapat dipulihkan setelah reload; validasi final masih menolak |
| Klik lanjut sebelum debounce selesai | Nilai terakhir tersimpan sebelum navigasi |
| Koneksi terputus/respons hilang | Tidak menampilkan sukses palsu; retry idempoten; input lokal tidak ditimpa |
| Dua tab mengubah data | Konflik terdeteksi dan tidak ada penimpaan diam-diam |
| Pilih S3, isi semua jenjang, ubah ke S1 lalu kembali S3 | Draf tidak kehilangan isian yang belum sengaja dibuang |
| Kirim saat upload sementara/permanen masih berjalan | Pengiriman ditolak/ditunda sampai batch selesai atau dibatalkan |
| File gagal, dihapus, atau selesai terlambat | Manifest tetap sama dengan ringkasan yang disetujui |
| Tanpa berkas dengan policy panduan saja | Ada peringatan; tidak dibuat kewajiban akademik baru |
| Biodata tidak lengkap | Tautan Perbaiki Biodata membuka bagian dan field yang tepat |
| Klik kirim ganda/retry | Satu revisi, satu persetujuan, satu manifest |
| Autosave terlambat sesudah kirim | Ditolak; snapshot dan status tidak berubah |
| Operator mengganti penugasan/akun dinonaktifkan | Akses diperiksa ulang pada setiap aksi dan ditolak jika tidak berhak |
| Kirim ulang perbaikan | Revisi bertambah, tanggal daftar awal tetap, file revisi lama tetap dapat ditinjau sesuai hak |
| Periode tutup/archived/hold | Draf, berkas, dan final mengikuti matriks status server |
| Dokumen revisi dihapus lewat request langsung | Guard server mencegah hilangnya blob yang masih dirujuk |
| Tampilan 360 px, keyboard, screen reader | Tidak ada scroll horizontal; tombol, label, error, dan status simpan terbaca |
| Audit dan log request | Tidak memuat payload identitas mentah maupun credential |
| Pengguna lama dan koreksi kepemilikan | Data lama tetap utuh dan berkas pemilik lain tidak terbuka |

Tes fitur menguji patch, otorisasi, validasi, status, manifest, dan retry. Tes konkurensi membuktikan benturan autosave–submit–unggah/hapus. Pemeriksaan browser manual menguji debounce, fokus/keyboard, pergantian halaman, progres upload, serta kondisi koneksi gagal; tes PHP saja tidak membuktikan perilaku debounce di browser.

Target awal performa: satu request per jeda ketik/batch, bukan satu request per karakter; tidak mengirim file lewat payload autosave; tidak memuat ulang seluruh daftar file setiap kolom biodata berubah. Ukur latensi dan jumlah query pada data nyata sebelum menetapkan SLA.

## 11. Batas keputusan dan rujukan

Rancangan ini memilih 700 ms, dua halaman dalam satu alur, patch draf terenkripsi, satu kirim final, serta pembekuan revisi berkas sebagai rekomendasi teknis. Daftar berkas wajib dan durasi retensi tetap keputusan pengelola; fitur tersebut tidak diasumsikan sudah ada.

Rujukan teknis yang diperiksa untuk Livewire 4:

- [wire:model: live, debounce, blur, dan change](https://livewire.laravel.com/docs/4.x/wire-model).
- [Unggah file dan validasi di server](https://livewire.laravel.com/docs/4.x/uploads).

Dokumen ini tetap menjadi catatan keputusan rancangan. Perilaku kode yang sudah diterapkan dan perbedaannya dicatat dalam [laporan implementasi](../implementasi/formulir-berkas-autosave.md).
