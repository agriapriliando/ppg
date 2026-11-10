# Rancangan dukungan unggah dan pemutaran video

Tanggal: **10 November 2026**, zona waktu **Asia/Jakarta**. Status: **usulan fitur, belum diimplementasikan**.

Halaman pengaturan: `/admin/aturan-berkas?period_id={id}`. Rancangan meliputi video peserta, berkas informasi periode dan pemutaran oleh pengguna yang berhak. Dokumen ini tidak mengaktifkan video atau mengubah konfigurasi produksi.

**Revisi sesuai arahan pengguna:** validasi utama video dilakukan **di frontend, pada perangkat pengguna, sebelum upload**. Rancangan awal yang mewajibkan antrean pemeriksaan `ffprobe` di server digantikan oleh alur ini. Server tetap melakukan pemeriksaan minimum untuk menerima dan menyimpan unggahan dengan benar.

**Keputusan tampilan:** video yang telah tersimpan dibuka melalui tombol **Buka Video** di **tab baru**. Halaman formulir, tabel unggahan, daftar berkas dan detail pemeriksaan tidak memuat pemutar video inline, iframe atau modal pemutar.

**Keputusan integrasi terbaru:** seluruh dokumen, gambar dan video menggunakan **menu, input dan area Unggah Berkas yang sudah ada**, baik untuk pendaftar maupun operator. Tidak membuat menu/halaman Unggah Video atau tombol transfer khusus video. Pola umum mengikuti [15 — Validasi frontend untuk seluruh unggahan](15-validasi-frontend-unggahan.md); dokumen ini melengkapi pemeriksaan khusus media. Jika ada perbedaan mengenai antrean, pemicu transfer atau status batch, gunakan pola bersama pada rancangan 15.

**Keputusan pengguna terbaru:** durasi video **tidak dibatasi**. Ukuran video mengikuti **pengaturan ukuran maksimal berkas yang sudah ada (`max_file_size_bytes`)**, sama dengan dokumen/gambar. Tidak menambah batas ukuran atau batas durasi khusus video.

## 1. Keputusan utama dan batasannya

Peserta atau operator memilih dokumen, gambar dan/atau video pada area **Unggah Berkas** yang sudah ada. Browser menjalankan validasi umum seluruh file, lalu pemeriksaan tambahan video. File yang lolos otomatis masuk antrean transfer; tidak memerlukan tombol khusus video. File gagal tidak dikirim, sehingga pengguna tidak menghabiskan kuota internet hanya untuk mengetahui ukuran atau formatnya tidak sesuai.

Server memeriksa autentikasi, izin, periode, ukuran aktual, ekstensi-MIME terdeteksi, kuota, jumlah file dan keutuhan penyimpanan. **Server rilis awal tidak memeriksa ulang durasi, resolusi atau codec dengan FFmpeg/ffprobe.** Tidak ada antrean wajib, status menunggu worker atau kebutuhan transkode di server untuk alur awal.

Konsekuensi yang perlu diketahui: hasil pemeriksaan browser dan metadata yang dikirimnya dapat dimodifikasi melalui request. Rilis ini memberi umpan balik dan pembatasan melalui antarmuka, tetapi **tidak menjamin bahwa aturan codec/resolusi tidak dapat dilewati oleh request yang dibuat di luar antarmuka**. Apabila batas tersebut kelak harus dibuktikan oleh server, tambahkan pemeriksaan media independen pada fase lanjutan. Pemeriksaan frontend tetap dipertahankan pada fase tersebut.

## 2. Rekomendasi pengaturan awal

Gunakan **MP4 dengan video H.264/AVC dan audio AAC-LC bila ada audio**. Video tanpa audio dan video vertikal dapat diterima. Container MP4 tidak otomatis berarti codec di dalamnya H.264; lihat [panduan codec MDN](https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Formats/Video_codecs).

Nilai berikut adalah usulan untuk uji kapasitas, bukan ketentuan kampus atau konfigurasi produksi yang sudah disahkan.

| Pengaturan | Usulan awal | Keterangan |
| --- | --- | --- |
| Pengaktifan video | Nonaktif sampai pengelola memilih MP4 | Tidak mengubah periode lama secara otomatis |
| Format | `.mp4`, H.264, AAC-LC jika ada audio | Diperiksa oleh validator frontend |
| Ukuran video | Mengikuti `max_file_size_bytes` pada aturan berkas periode | Batas yang sama untuk semua format; frontend dan server memakai nilai yang sama |
| Durasi | Tidak dibatasi | Metadata durasi hanya informasi opsional, bukan syarat kelolosan |
| Resolusi | Anjuran 720p; sisi panjang maksimal 1920 dan sisi pendek maksimal 1080 piksel | Mendukung orientasi horizontal dan vertikal |
| Jumlah video peserta | 1/workspace/periode; dapat diatur 1-5 | Server menghitung ulang untuk menangani upload bersamaan |
| Kuota workspace | Disarankan 500 MiB dan wajib terbatas jika video aktif | Mencakup dokumen dan video |
| Batas PDF/gambar | Mengikuti `max_file_size_bytes` yang sama | Mengubah pengaturan ukuran berkas berlaku pada seluruh format |
| Melihat video | Tombol **Buka Video** menuju tab baru | Halaman utama hanya menampilkan tautan/tombol dan informasi file |
| Penyimpanan | Disk privat yang sudah ada | Izin file peserta tetap berlaku |

Video pendek dapat berukuran besar dan video panjang dapat berukuran kecil. Penerimaan ukuran mengikuti pengaturan berkas, tanpa menolak berdasarkan lamanya rekaman. Petunjuk menyarankan ekspor 720p, kompresi secukupnya dan pemeriksaan ukuran akhir. Frame rate 25/30 fps merupakan anjuran, bukan syarat penolakan baru.

Untuk rekaman mengajar 30-60 menit atau banyak upload serentak, rencanakan unggahan yang dapat dilanjutkan dan object storage privat sebelum menaikkan batas. Belum ada ukuran server/jumlah peserta produksi yang diverifikasi pada pekerjaan ini.

## 3. Kondisi aplikasi yang teramati

| Bagian | Temuan saat telaah |
| --- | --- |
| Format dan pratinjau | [`config/document_policy.php`](../../config/document_policy.php) belum memuat video |
| Kebijakan versi | [`DocumentPolicyVersion`](../../app/Models/DocumentPolicyVersion.php) tidak dapat ditimpa; batas ukuran sekarang masih umum per file |
| Publikasi | [`PublishDocumentPolicy`](../../app/Actions/Documents/PublishDocumentPolicy.php) membatasi ukuran per file sampai 100 * 1024^2 byte dan kuota sampai 10 GiB |
| Temporary upload | [`config/livewire.php`](../../config/livewire.php) sudah menetapkan `max:102400` KiB, yaitu 100 MiB |
| Upload peserta | [`UploadApplicantDocument`](../../app/Actions/Documents/UploadApplicantDocument.php) memakai UUID, disk privat, SHA-256, kuota, versi workspace dan kunci idempoten |
| Pencocokan konten | Jalur peserta memeriksa ekstensi dan gabungan MIME; perlu memeriksa pasangan ekstensi-MIME yang tepat pada penerimaan file |
| Upload pengelola | [`UploadPeriodDocument`](../../app/Actions/Documents/UploadPeriodDocument.php) menggunakan aturan periode yang sama; harus mengikuti validasi frontend yang sama untuk video |
| Akses | Policy berkas dan visibilitas berkas periode sudah ada; bukan kewenangan baru dari fitur video |
| Infrastruktur | PHP-FPM, Nginx, proxy/CDN, kapasitas disk dan backup produksi belum diaudit |

## 4. Pengaturan pada Aturan Berkas

1. Pengelola memilih periode seperti sekarang.
2. Kelompokkan pengaturan **Dokumen & Gambar** dan **Video** pada halaman Aturan Berkas yang sama, bukan menu/tab navigasi unggah terpisah. Area unggah pengguna tetap satu input/dropzone untuk semua format.
3. Pilihan **Izinkan video MP4** menambah `mp4` ke `allowed_extensions`. Daftar ini menjadi sumber pengaktifan tunggal; jangan menambah boolean lain yang dapat bertentangan.
4. Saat MP4 aktif, tampilkan resolusi, jumlah video peserta dan kuota workspace. Ukuran tetap memakai satu pengaturan maksimal berkas yang sudah ada; tidak ada input durasi maksimum atau ukuran khusus video. Resolusi awal dapat berupa profil tetap Full HD, sedangkan 720p menjadi petunjuk ekspor.
5. Pertahankan batas ukuran berkas bersama, petunjuk umum, riwayat dan publikasi versi baru.
6. Kirim aturan aktif ke frontend sebagai JSON yang dihasilkan server, beserta ID/versi kebijakan. Jangan menggandakan nilai batas secara manual pada JavaScript.

```text
Dokumen & Gambar:        [PDF] [JPG] [PNG] ...
Batas semua berkas:      10 MiB per file (contoh pengaturan periode)

Video:                  [x] Izinkan video MP4
Ukuran video:           mengikuti batas semua berkas di atas
Durasi:                 tidak dibatasi (keterangan, bukan input batas)
Resolusi maksimum:      Full HD; mendukung video vertikal
Jumlah video peserta:   1 per peserta pada periode ini
Kuota total peserta:    500 MiB

Petunjuk umum:          [................................]
                        [Publikasikan Aturan Baru]
```

Administrator dan operator mengikuti akses publikasi yang sudah berlaku. Kuota workspace dan batas jumlah video peserta berlaku untuk workspace peserta, bukan sebagai kuota workspace pada berkas informasi periode milik pengelola. Video panduan pengelola memakai pemeriksaan format, ukuran dan resolusi frontend yang sama; durasi hanya informasi opsional.

Jika video aktif, kuota wajib terbatas dan minimal sebesar batas ukuran berkas bersama. Plafon teknis pengaturan berkas saat telaah adalah 100 MiB untuk semua format; angka itu bukan batas video terpisah dan tidak otomatis menjadi ukuran yang diizinkan setiap periode. Penonaktifan video atau penurunan batas tidak menghapus file sah terdahulu; file lama tetap memiliki versi kebijakan saat diterima. Peserta di atas kuota baru tidak dapat menambah file, tetapi file lama tidak dihapus otomatis.

## 5. Validasi frontend sebelum transfer

### 5.1 Urutan pemeriksaan

1. Pengguna memilih atau menyeret file melalui input/dropzone **Unggah Berkas** yang sama untuk dokumen, gambar dan video. `accept` memuat seluruh ekstensi nyata kebijakan aktif, termasuk `.mp4` jika diizinkan, bukan input video terpisah atau `video/*`.
2. Browser memeriksa video diizinkan, ukuran `File.size` positif dan di bawah batas, jumlah file yang sedang dipilih serta sisa kuota terakhir yang diketahui. Tolak file besar sebelum membaca metadata.
3. Periksa ekstensi `.mp4`. `File.type` hanya informasi pendukung, karena nilainya dapat kosong atau bergantung pada penamaan/lingkungan perangkat; bukan bukti isi file. Dasar perilakunya: [Blob.type](https://developer.mozilla.org/en-US/docs/Web/API/Blob/type).
4. Baca container dan track menggunakan parser MP4 lokal di browser. Kandidat: **MP4Box.js dari GPAC**. Parser menyediakan informasi track/codec dan dapat membaca buffer secara bertahap; evaluasi lisensi, versi, ukuran bundle dan konsumsi memori sebelum memasangnya. Rujukan: [dokumentasi MP4Box.js](https://github.com/gpac/mp4box.js).
5. Pastikan container sesuai MP4, terdapat track video H.264 (`avc1`/`avc3` sesuai profil yang diuji), audio AAC-LC bila ada, dan media tidak terenkripsi. HEVC atau format lain ditolak dengan pesan ekspor ulang. MP4 berisi audio saja tidak lolos.
6. Untuk validasi saja, buat object URL lokal dan elemen video sementara yang tidak ditampilkan pada halaman. Tunggu `loadedmetadata`, lalu baca durasi dan dimensi; tangani juga perubahan durasi. Elemen ini tidak memiliki kontrol yang terlihat dan tidak menjalankan `play()`. Event tersebut menandai tersedianya metadata, bukan selesainya upload. Rujukan: [loadedmetadata](https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/loadedmetadata_event).
7. Tidak membandingkan durasi dengan batas apa pun. Jika durasi finite dan positif tersedia, tampilkan sebagai informasi; jika tidak tersedia/`NaN`/`Infinity`, simpan null dan jangan menolak hanya karena nilai durasi tersebut. Container/track tidak sah, dimensi nol, atau kegagalan pemeriksaan format/codec tetap menghasilkan pesan tidak dapat diperiksa. Terapkan batas waktu proses pemeriksaan, usulan awal 15 detik untuk diuji pada ponsel; ini timeout kerja browser, bukan batas panjang video.
8. Validasi dimensi dengan orientasi/rotasi yang benar: periksa sisi panjang dan pendek, bukan hanya `width <= 1920`. Jangan menolak video vertikal yang masih memenuhi profil.
9. Tampilkan ringkasan nama, ukuran, durasi dan resolusi sebagai teks, tanpa pratinjau video. Setelah semua pemeriksaan lolos untuk file dan versi aturan yang sama, masukkan file ke antrean unggah otomatis bersama dokumen/gambar. Setelah server mengonfirmasi penyimpanan, tampilkan tombol **Buka Video** yang membuka tab baru.

`canPlayType()` hanya menyatakan kemungkinan browser mendukung kombinasi MIME/codec yang ditanyakan. Metode itu tidak membaca codec file yang dipilih, sehingga tidak dapat menggantikan parser. Browser yang bisa memainkan HEVC juga tidak menjadi alasan meloloskan HEVC ketika kebijakannya H.264. Lihat [canPlayType](https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/canPlayType).

### 5.2 Memori, pembatalan dan data lokal

- Validator video adalah perluasan validator umum file pada rancangan 15. Gunakan implementasi yang sama pada DocumentManager mode applicant/reviewer/admin dan PeriodDocumentManager. Muat parser secara lokal melalui bundle aplikasi ketika diperlukan; jangan mengirim video ke layanan pemeriksa eksternal.
- Gunakan `Blob.slice()` dan pembacaan buffer bertahap, bukan satu `arrayBuffer()` untuk seluruh file. Hindari menyimpan payload media `mdat` di memori parser saat hanya memerlukan metadata.
- Tangani metadata `moov` di awal maupun akhir file. Strategi offset/pembacaan mengikuti parser; baca metadata secukupnya dan hentikan jika melampaui anggaran waktu/memori. Jangan menganggap membaca beberapa byte pertama selalu cukup.
- Proses satu video per giliran; gunakan Web Worker untuk parsing bila diperlukan agar formulir tetap responsif. Tidak melakukan transkode atau decode seluruh video pada rilis awal.
- Setiap pilihan memiliki ID/generasi pemeriksaan lokal. Jika pengguna mengganti file, menutup dialog atau mengganti periode, batalkan proses lama dan abaikan hasil yang datang terlambat.
- Ketika file berubah, batalkan status lolos dan hasil metadata lama. Jangan mencocokkan hanya berdasarkan nama file; gunakan referensi objek file serta ID pemeriksaan yang sama sampai unggah dimulai.
- Hapus sumber elemen pemeriksa sementara dan lepaskan object URL setelah pemeriksaan/batal selesai. Object URL tersebut hanya untuk membaca metadata, tidak ditampilkan atau dijadikan tautan melihat file tersimpan. Rujukan pelepasan URL lokal: [URL.revokeObjectURL](https://developer.mozilla.org/en-US/docs/Web/API/URL/revokeObjectURL_static).
- Jika parser gagal dimuat, JavaScript tidak aktif atau metadata tidak dapat dibaca, video tidak mulai diunggah dari UI. Tampilkan petunjuk mencoba browser lain atau mengekspor ulang file; dokumen biasa tetap mengikuti alurnya.

### 5.3 Keadaan antarmuka

| Status | Tampilan dan tindakan |
| --- | --- |
| Belum memilih | Pilih/seret berkas pada area unggah lama; ringkasan batas dokumen/video terlihat |
| Memeriksa video | Indikator pemeriksaan lokal; belum ada transfer file; Batalkan tersedia |
| Perlu diperbaiki | Alasan pada file terkait; tidak ada transfer file ke server |
| Siap diunggah | Ringkasan teks ukuran, durasi dan resolusi; menunggu giliran transfer otomatis |
| Mengunggah | Persentase transfer, ukuran dan tombol Batalkan |
| Menyimpan | Transfer selesai, menunggu konfirmasi penyimpanan final dari server |
| Tersimpan | Tampilkan nama, ukuran dan tombol Buka Video yang membuka tab baru, sesuai izin |
| Upload gagal | Pesan jelas; retry hanya file gagal; pemeriksaan diulang bila file/aturan berubah |

**Tidak ada status menunggu worker pemeriksa pada rilis ini.** Transfer 100% tetap perlu menunggu konfirmasi penyimpanan server, tetapi peserta tidak menunggu proses ffprobe. Jangan menamai metadata browser sebagai "terverifikasi server".

## 6. Batas pemeriksaan di frontend dan server

| Pemeriksaan | Frontend sebelum upload | Server saat menerima |
| --- | --- | --- |
| Format dipilih dalam aturan | Ya | Ya, aturan yang berlaku saat penerimaan |
| Ukuran per file | Ya, `File.size` | Ya, ukuran aktual file |
| Container/codec | Parser MP4 dan metadata lokal tanpa player yang terlihat | Pencocokan ekstensi-MIME konten; tidak melakukan probe codec |
| Durasi | Informasi opsional, tanpa batas dan tanpa penolakan berdasarkan lama rekaman | Metadata opsional yang dapat dinullkan; bukan syarat penerimaan |
| Resolusi | Ya, parser dan metadata video | Memvalidasi bentuk metadata kiriman, bukan mengukur ulang file |
| Jumlah video/kuota | Cek awal berdasarkan data halaman | Hitung ulang dalam transaksi; keputusan final ada di server |
| Identitas, izin, kepemilikan/periode | Memberi informasi yang relevan | Wajib diperiksa ulang |
| Duplikasi retry dan integritas penyimpanan | ID unggah yang stabil | Kunci idempoten, UUID, SHA-256 |

Validasi bentuk metadata kiriman berarti tipe angka/string, nilai finite dan panjang field diperiksa sebelum disimpan. Durasi tidak dibandingkan dengan aturan maksimum dan tidak boleh menjadi alasan penolakan; nilai durasi yang tidak dapat direpresentasikan disimpan null. Metadata tersebut **bukan pengukuran aktual oleh server**. Jangan menggunakannya sebagai bukti keaslian konten atau keputusan verifikasi akademik otomatis.

MIME terdeteksi harus cocok dengan ekstensi; jangan memakai `application/octet-stream` untuk meloloskan semua video. Jalur upload langsung yang tidak melalui UI tetap wajib memenuhi pemeriksaan penerimaan server. Tidak ada jaminan seluruh frame bebas kerusakan hanya dari metadata lokal; uji pemutaran sampel nyata tetap diperlukan.

## 7. Penerimaan, kuota dan pengajuan

1. Frontend mengirim file hanya setelah pemeriksaan lokal lolos. Metadata dan ID kebijakan menyertai upload, terikat ke kunci permintaan file yang sama. Implementasi perlu meneruskan kontrak ini dari temporary upload sampai penyimpanan final.
2. Server memeriksa izin dan batas penerimaan sebelum menyalin file permanen. Perbaiki jalur peserta yang saat ini melakukan sebagian validasi sesudah penulisan file.
3. Dalam transaksi singkat, periksa kembali periode, versi kebijakan, batch, ukuran, MIME, jumlah video dan kapasitas yang sudah dipakai/dipesan. Upload paralel tidak boleh melewati kuota.
4. Jika aturan berubah dan hasil pemeriksaan lokal sudah usang, jangan menerima diam-diam. Kembalikan konflik aturan, muat batas terbaru dan periksa ulang file lokal sebelum transfer ulang. Bersihkan temporary file yang ditolak agar tidak menjadi sampah.
5. Simpan privat menggunakan UUID, hash dan versi kebijakan penerimaan. Sesudah penyimpanan berhasil, file mengikuti status `available` yang sudah ada; tidak perlu menambahkan status antrean pemeriksaan media di server.
6. Retry dengan kunci yang sama tidak menggandakan file. Jika sebagian batch gagal, file yang sudah berhasil tetap tersimpan.

Untuk pilihan campuran, pemeriksaan umum dokumen/gambar dan video memakai antrean, identitas per-file serta reservasi lokal yang sama, sesuai rancangan 15. Transfer baseline satu file per request dan berurutan; jangan mengirim kumpulan file 100 MiB sekaligus dalam satu request. Slot video dan kuota yang sudah dipesan file sebelumnya diperhitungkan sebelum file berikutnya lolos. File yang ditolak lokal tidak membuat batch gagal di database. Busy pada kirim final mencakup pemeriksaan lokal, antrean siap, transfer dan penyimpanan; konfirmasi temporary upload bukan konfirmasi final.

Video tidak otomatis menjadi syarat wajib pendaftaran. Pengiriman final mengikuti guard batch yang sudah ada: jangan izinkan kirim ketika upload masih berlangsung atau ada batch gagal yang belum diselesaikan/dibatalkan. Tidak ada guard menunggu ffprobe. Versi berkas, referensi pengajuan dan rekaman pengajuan lama tetap dipertahankan.

File yang masih disimpan/dipesan harus tetap masuk perhitungan kapasitas sampai penghapusan/pembersihan benar-benar selesai. Gunakan alur `deleting`, manifest dan retry hapus yang sudah ada. Jangan menimpa file lama dengan nama sama atau menghapus bukti pengajuan untuk mengejar kuota baru. Temporary file yang dibatalkan atau kedaluwarsa dibersihkan menurut kebijakan yang terukur.

## 8. Melihat video di tab baru dan kontrol akses

Semua menu yang menampilkan file video menyediakan tombol/tautan **Buka Video**. Tautan membuka route file aplikasi terotorisasi pada tab baru; tidak menyisipkan player ke halaman asal. Formulir, tabel unggahan, detail peserta dan pemeriksaan tetap menampilkan informasi file secara ringkas.

- Gunakan elemen tautan `<a>` yang ditata seperti tombol, dengan `target="_blank"` dan `rel="noopener noreferrer"`. Beri keterangan aksesibel **Buka Video (tab baru)**. Tab baru hanya dibuka saat pengguna mengeklik tautan, bukan secara otomatis setelah unggah atau melalui popup JavaScript.
- Route file mengembalikan video yang sudah disimpan dengan MIME benar dan `Content-Disposition: inline`; browser menangani pemutar pada tab baru. Aksi **Unduh** tetap terpisah dengan disposition unduhan. Jangan membuka object URL temporary atau URL publik `storage/...` untuk file peserta.
- Jangan memakai `<video>` yang terlihat, iframe, autoplay atau modal pemutar pada halaman aplikasi. Elemen video sementara yang tidak terlihat hanya dipakai untuk validasi metadata pada bagian 5, kemudian dibersihkan.
- Pemilik, operator dan administrator mengikuti policy berkas yang sudah ada. Berkas informasi periode mengikuti visibilitas pengelola; video peserta tetap privat.
- Route lihat/stream/unduh memeriksa pengguna, periode, hubungan kepemilikan dan status file pada setiap request. Tidak mengubah kewenangan hanya karena validasi frontend lolos.
- Dukung HTTP Range secara nyata: seek diuji menghasilkan `206 Partial Content`, range salah `416`; jangan memuat video utuh ke memori PHP. Rujukan: [HTTP Range requests](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Range_requests).
- Gunakan MIME yang benar, `nosniff`, cache privat dan pencatatan audit tanpa membuat satu audit berulang untuk setiap potongan range.
- Pada perangkat yang tidak dapat memutar file, tampilkan pesan yang jelas dan pilihan unduh sesuai izin. Kelolosan di browser pengunggah tidak menjamin perangkat pemeriksa identik.

## 9. Usulan perubahan data dan kode

Semua nama baru berikut adalah **usulan implementasi**, belum tersedia sebagai fitur.

| Sasaran | Perubahan |
| --- | --- |
| `document_policy_versions` | Ukuran tetap memakai `max_file_size_bytes` yang sudah ada; hanya usulan `max_video_files` untuk jumlah, tanpa `max_video_size_bytes` atau `max_video_duration_seconds`; profil dimensi/codec dari konfigurasi server; aktivasi tetap melalui `allowed_extensions` |
| Helper kebijakan | Ekspor satu batas ukuran berkas bersama dan JSON aturan aktif; versi lama tanpa MP4 tetap menonaktifkan video |
| Metadata video tersimpan | `client_duration_seconds`, `client_width`, `client_height`, `client_video_codec`, `client_audio_codec`, `client_validator_version` dan `client_policy_version_id`, nullable pada kedua model file |
| Identitas metadata | Terikat pada UUID/kunci upload yang sama dan hash file final dari server; metadata ditandai berasal dari browser |
| `VideoFileValidator` | Modul tambahan pada validator umum rancangan 15, untuk parser, metadata native, timeout, pembatalan dan pembersihan sumber lokal |
| Komponen upload yang sudah ada | Satu input/dropzone campuran; validator umum lalu validator video harus selesai sebelum upload Livewire/temporary upload, bukan setelah `wire:model` otomatis mengirim file |
| Tombol video | Komponen tautan Buka Video dengan tab baru; route file privat tetap memeriksa akses |
| `ffprobe`/job media | Tidak dibutuhkan pada rilis frontend ini; opsi penguatan terpisah bila kelak diperlukan |

Usulan tipe metadata: durasi nullable `double` hanya untuk informasi, dimensi `unsignedInteger`, codec/versi string terbatas. Durasi yang tidak tersedia/tidak finite disimpan null dan tidak menolak file; tidak ada batas durasi bisnis. Ukuran file aktual dan hash tetap berasal dari server. Jangan membuat field bernama `verified_duration` untuk nilai yang hanya dilaporkan browser. Metadata lokal tidak digunakan untuk mengubah status kelulusan atau mengganti bukti revisi pengajuan.

Berkas sasaran:

- `config/document_policy.php`, `DocumentPolicyVersion`, `PublishDocumentPolicy`, `Admin/DocumentPolicyController` dan `admin/document-policies.blade.php`.
- `Livewire/Documents/DocumentManager`, `Livewire/Documents/PeriodDocumentManager` beserta view dan modul frontend baru.
- `UploadApplicantDocument` dan `UploadPeriodDocument` untuk batas ukuran berkas bersama, pasangan MIME, penerusan metadata serta pemeriksaan kapasitas.
- `ApplicantDocumentController`, `StaffDocumentController`, `PeriodDocumentController` dan komponen tautan Buka Video bersama.
- Guard batch/pengajuan dan pembersihan tetap mengikuti arsitektur yang ada; tidak menambahkan ketergantungan worker media pada kirim final.

## 10. Kesiapan server dan ukuran satuan

`1 MiB = 1.048.576 byte`; `100 MiB = 104.857.600 byte`. UI memakai MiB sesuai perhitungan aplikasi. Sebelum rilis, periksa PHP, web server, proxy/CDN, temporary storage, disk final, timeout, masa berlaku upload serta backup.

Transport disesuaikan dengan ukuran maksimal pada pengaturan berkas, bukan angka khusus video. Jika pengelola mengatur 100 MiB, contoh baseline uji adalah PHP `upload_max_filesize=100M`, `post_max_size=128M` dan batas body web server setidaknya 128 MiB. Jika pengaturannya 10 MiB, video tetap dibatasi 10 MiB meskipun transport menerima lebih besar. Ini rekomendasi untuk diuji, bukan konfigurasi produksi yang sudah diverifikasi. Limit upstream yang lebih kecil harus diselaraskan atau batas aplikasi diturunkan. Rujukan Nginx: [client_max_body_size](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size).

Pemindahan validasi media ke frontend mengurangi pekerjaan pemeriksaan media di server, tetapi tidak menghilangkan transfer, penyimpanan atau bandwidth pemutaran. Sebagai perhitungan batas, 1.000 peserta x satu video x 100 MiB sekitar **97,7 GiB** sumber video, belum termasuk dokumen, temporary file dan backup.

## 11. Pesan antarmuka

| Keadaan | Contoh pesan |
| --- | --- |
| Pemeriksaan lokal berjalan | "Memeriksa video di perangkat Anda. File belum diunggah." |
| Ukuran berlebih | "Ukuran video 14 MiB. Batas ukuran berkas periode ini 10 MiB. Kompres video, lalu pilih ulang." Angka diambil dari file dan pengaturan aktif. |
| Codec tidak sesuai | "Format video belum sesuai. Ekspor sebagai MP4 H.264 dengan audio AAC, lalu pilih ulang." |
| Metadata tidak terbaca | "Video belum dapat diperiksa di browser ini. Coba browser lain atau ekspor ulang sebagai MP4 H.264." |
| Lolos lokal | "Video memenuhi pemeriksaan pada perangkat ini dan siap diunggah." |
| Aturan berubah | "Aturan berkas telah diperbarui. Video akan diperiksa ulang dengan aturan terbaru." |
| Jaringan putus | "Unggahan belum selesai. Sambungkan kembali internet dan coba ulang file ini." |
| Selesai | "Video berhasil disimpan." |

Pada rilis awal, retry video dapat mengulang transfer file tersebut dari awal. Jangan menjanjikan lanjut dari persentase terakhir sebelum protokol resumable diimplementasikan. File lolos lokal masih dapat ditolak server jika izin, ukuran aktual, kuota atau aturan berubah.

## 12. Tahapan pembangunan dan pengujian

### Tahap 1: Validasi frontend dan unggah MP4

Urutan: kontrak/validator umum rancangan 15 -> antrean campuran pada area unggah lama -> evaluasi parser dan validator video -> integrasi sebelum upload di kedua komponen serta semua mode pengelola -> validasi minimum server/kuota -> tautan tab baru dan dukungan Range -> pembersihan -> UAT dan panduan. Tidak membuat menu atau halaman unggah baru.

Tidak memerlukan FFmpeg, ffprobe atau antrean pemeriksaan media. Tidak melakukan kompresi/transkode otomatis di ponsel. Versi parser dipilih dan dikunci sesudah pengujian kompatibilitas, lisensi serta konsumsi memori.

### Tahap 2: Penguatan sesuai kebutuhan

Tambahkan resumable/multipart upload dan object storage untuk file besar/jaringan tidak stabil. Jika codec/resolusi harus menjadi aturan yang tidak dapat dilewati melalui request, tambahkan pemeriksaan independen `ffprobe` dengan worker terkontrol, status pemeriksaan terpisah, metadata server, retry dan guard pengajuan. Ini merupakan perubahan cakupan, bukan sesuatu yang sudah dijamin oleh rilis frontend.

### Matriks uji minimum

1. File di atas batas ditolak di frontend; inspeksi Network memastikan tidak ada transfer file, termasuk ke temporary upload Livewire.
2. Video pendek maupun panjang lolos bila ukuran dan pemeriksaan lain memenuhi aturan; tidak ada penolakan karena durasi. Durasi tidak terbaca/`NaN`/`Infinity` menjadi null tanpa menolak karena durasinya. Dimensi nol, timeout pemeriksaan dan file tidak sah ditangani sesuai alasan teknisnya.
3. MP4 H.264/AAC-LC, tanpa audio dan vertikal lolos; HEVC ditolak walaupun browser dapat memutarnya; audio saja atau file yang hanya diganti ekstensi ditolak parser.
4. MIME browser kosong tidak otomatis menolak file sah; MIME dari browser juga tidak menjadi bukti tunggal kelolosan.
5. Metadata di awal/akhir, video mendekati 100 MiB, ponsel dengan memori terbatas dan penggunaan berulang tidak membekukan halaman atau meninggalkan object URL.
6. Mengganti file/periode atau membatalkan pemeriksaan mengabaikan respons lama; upload tidak menggunakan hasil file sebelumnya.
7. Parser gagal dimuat/JavaScript mati tidak memulai upload video di UI; kesalahan pada satu file tidak menghapus file yang sudah tersimpan.
8. Ukuran aktual, pasangan MIME, izin, jumlah dan kuota tetap diperiksa server; request langsung tidak melewati pemeriksaan minimum ini. Pengujian tidak boleh mengklaim server mengukur durasi aktual pada rilis ini.
9. Upload paralel, retry dan perubahan versi aturan ditangani; tidak menggandakan file atau melewati kuota.
10. Video peserta privat; akses peserta lain ditolak; operator dan berkas periode mengikuti izin/visibilitas yang berlaku.
11. Tombol Buka Video membuka tab baru pada Chrome Android, Safari iPhone dan desktop. Halaman asal tidak berubah, tidak memuat player/iframe dan tidak mulai memutar video. Putar/pause/seek pada tab video serta aksi Unduh diuji; Range menghasilkan 206/416 sesuai permintaan. Akses tidak sah ditolak meskipun URL disalin ke tab baru.
12. Dokumen lama, unggah campuran, rename, hapus, batch, kirim final, riwayat kebijakan, arsip dan bukti pengajuan tetap bekerja.
13. Satu input/dropzone lama menerima dokumen, gambar dan video; tidak ada menu/tombol Unggah Video terpisah. Antrean memvalidasi semua file sebelum transfer masing-masing, otomatis mengunggah yang lolos, dan menandai yang gagal tanpa menghapus keberhasilan lain.
14. Seluruh matriks pola bersama pada rancangan 15 lulus, termasuk reservasi kuota, busy selama pemeriksaan lokal, retry idempoten dan konfirmasi penyimpanan final.

Gate rilis: pengujian frontend dan backend minimum lulus, sampel media nyata lolos pada perangkat target, limit transport/kapasitas terverifikasi, dan panduan diperbarui. Seluruh pengujian di atas masih **rencana**, belum merupakan hasil implementasi video.

## 13. Keputusan yang masih diperlukan

- Apakah satu video dan kuota 500 MiB sesuai kebutuhan kampus? Ukuran mengikuti pengaturan berkas yang sudah ada dan durasi tidak dibatasi, sesuai keputusan pengguna.
- Apakah rekaman mengajar panjang, audio wajib, watermark, subtitle atau retensi khusus diperlukan?
- Apakah pembatasan melalui frontend cukup untuk kebutuhan operasional, atau kelak perlu pembuktian metadata di server?
- Berapa jumlah peserta serentak dan kapasitas disk/backup yang tersedia?

## Referensi teknis

Ditelaah pada 10 November 2026. Angka kapasitas di atas adalah usulan aplikasi; sumber berikut menjelaskan mekanisme teknis.

- [MP4Box.js GPAC](https://github.com/gpac/mp4box.js): pembacaan MP4 bertahap serta informasi track/codec.
- [MDN: codec video](https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Formats/Video_codecs): pemilihan container dan codec.
- [MDN: loadedmetadata](https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/loadedmetadata_event) dan [duration](https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/duration): metadata lokal dan keadaan durasi yang perlu ditangani.
- [MDN: canPlayType](https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/canPlayType): kemungkinan dukungan pemutaran, bukan identifikasi file.
- [MDN: Blob.type](https://developer.mozilla.org/en-US/docs/Web/API/Blob/type), [Blob.slice](https://developer.mozilla.org/en-US/docs/Web/API/Blob/slice) dan [revokeObjectURL](https://developer.mozilla.org/en-US/docs/Web/API/URL/revokeObjectURL_static): batas keandalan MIME, pembacaan sebagian file dan pembersihan URL lokal.
- [Livewire 4: upload](https://livewire.laravel.com/docs/4.x/uploads): alur temporary upload yang harus ditunda sampai validasi frontend selesai.
- [Laravel: validasi file](https://laravel.com/framework/docs/validation#rule-mimes): deteksi MIME dan pemeriksaan ekstensi di server.
- [MDN: HTTP Range](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Range_requests) dan [Nginx: batas body](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size): pemutaran sebagian file dan batas transport.
