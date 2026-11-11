# Rancangan validasi frontend untuk seluruh unggahan

> **Perubahan terbaru:** bagian pemeriksaan codec, resolusi dan metadata video di bawah merupakan rancangan lama. Pemeriksaan aktif dan format yang didukung dijelaskan dalam [catatan implementasi](../implementasi/validasi-frontend-dan-video.md).

Tanggal rancangan: **10 November 2026**, zona waktu **Asia/Jakarta**. Status: **implementasi lokal tersedia, diperbarui 11 November 2026**. Lihat [perilaku aktual, pengujian dan langkah rilis](../implementasi/validasi-frontend-dan-video.md). Matriks/gate di bawah tetap acuan penerimaan; UAT perangkat dan production dibedakan dari pengujian lokal.

Dokumen ini menjadi acuan pola bersama unggah dokumen, gambar, dan video. Rincian pemeriksaan media mengikuti [rancangan video](14-unggah-video.md). Tidak ada perubahan aplikasi, migrasi, menu, atau konfigurasi production dari penulisan dokumen ini.

## 1. Keputusan yang ditetapkan

- **Tetap menggunakan menu dan area Unggah Berkas yang sudah ada.** Tidak membuat menu Unggah Video, halaman uploader baru, atau alur pendaftaran terpisah.
- Satu input dan satu dropzone menerima dokumen, gambar, dan video yang diizinkan aturan periode. Pemilihan campuran diperbolehkan. `accept` dibangun dari ekstensi aturan aktif, bukan `*/*` atau `video/*`.
- Setiap file wajib lolos pemeriksaan frontend sebelum satu byte isi file tersebut dikirim, termasuk ke temporary upload Livewire. Permintaan kecil untuk mengambil aturan, izin, atau membuat batch bukan transfer isi file.
- Pertahankan perilaku **unggah otomatis setelah pemeriksaan lolos**. Tidak menambah tombol khusus Unggah Video. Tampilkan status pemeriksaan per file agar pengguna memahami bahwa transfer belum dimulai.
- File yang gagal pemeriksaan lokal tidak dikirim; file yang lolos tetap dapat diproses. Kegagalan satu file tidak membatalkan atau menghapus keberhasilan file lain.
- Backend tetap menjadi pemeriksa akhir ukuran aktual, pasangan ekstensi-MIME konten, izin, versi aturan, kuota dan konsistensi penyimpanan. Validasi frontend meningkatkan pengalaman pengguna dan menghemat transfer; bukan batas keamanan yang tidak dapat dilewati.
- **Durasi video tidak dibatasi. Ukuran semua format, termasuk video, memakai pengaturan `max_file_size_bytes` yang sudah ada.** Tidak ada batas ukuran atau durasi khusus video.
- Video tersimpan hanya ditampilkan sebagai informasi file dan tombol **Buka Video (tab baru)**. Tidak ada player, iframe, atau modal pemutar di halaman aplikasi.

## 2. Kondisi saat telaah dan cakupan integrasi

| Jalur yang sudah ada | Implementasi sekarang | Sasaran perubahan |
| --- | --- | --- |
| Pendaftar `/pendaftar/berkas/{periode}` | `DocumentManager`, mode `applicant` | Validator bersama sebelum transfer |
| Operator di detail pemeriksaan peserta | `DocumentManager`, mode `reviewer` | Pola dan pesan yang sama; tetap mengikuti izin operator |
| Pengelola pada workspace pendaftar | `DocumentManager`, mode `admin` | Pola yang sama; tidak melewati aturan karena peran pengelola |
| Pengelola berkas informasi periode | `PeriodDocumentManager` | Validator yang sama; kuota/jumlah video workspace peserta tidak diterapkan pada berkas periode |

Pada telaah awal, kedua view memakai `accept` dan langsung memanggil `$uploadMultiple` setelah persiapan batch, tanpa pemeriksaan ukuran, ekstensi, kapasitas pilihan campuran atau metadata video sebelum transfer. Implementasi lokal sekarang menambahkan validator dan antrean bersama sebelum temporary upload, serta dukungan MP4 pada konfigurasi kebijakan.

Berkas peserta tetap privat, sedangkan informasi periode mengikuti visibilitas yang sudah berlaku. Rancangan ini tidak menambah kewenangan atau mengubah akses file lama.

## 3. Kontrak aturan bersama

Server menghasilkan satu payload aturan dari versi kebijakan yang berlaku, untuk kedua komponen. Berikut struktur konseptual rancangan. Implementasi `DocumentUploadRules::contract()` mengirim batas bersama dan kapasitas tersisa langsung pada payload, tanpa objek `context`, `file_types` atau `workspace`; pasangan MIME diperiksa di server, dan codec diperiksa validator video.

```text
policy_version_id, validator_contract_version
context: applicant_workspace | period_documents
allowed_extensions, max_file_size_bytes
file_types: extension -> group, allowed_mime_types
workspace: remaining_bytes|null, available_video_slots|null
video: max_long_side, max_short_side,
       allowed_video_codecs, allowed_audio_codecs
```

`workspace` hanya berlaku untuk peserta. Nilai `null` berarti tidak dibatasi oleh aturan tersebut, bukan nol. Batas ukuran berupa byte; tampilan menggunakan MiB. Aktivasi video tetap bersumber dari `allowed_extensions` yang memuat MP4. Versi kebijakan lama tetap menonaktifkan video. Satu `max_file_size_bytes` berlaku untuk dokumen, gambar dan video. Tidak menambah `max_video_size_bytes` atau `max_video_duration_seconds`; mengubah batas ukuran pada pengaturan berkas berlaku untuk seluruh format.

Ambil aturan terbaru sebelum memulai pemrosesan antrean dan setelah mutasi berkas, perpindahan periode, serta konflik server. Jangan menyalin angka batas ke JavaScript secara manual. Data sisa kapasitas di halaman adalah perkiraan; backend menghitung ulang untuk mengatasi tab/perangkat yang bersamaan.

## 4. Alur satu area unggah

1. Pengguna memilih atau menyeret satu/beberapa file di **Unggah Berkas** yang sudah ada.
2. Buat identitas lokal unik untuk setiap objek `File`; simpan urutan pilihan dan referensi objek, bukan mencocokkan berdasarkan nama saja. Dua file dengan nama sama tetap berbeda.
3. Periksa akses/kesiapan aturan. Bila aturan tidak dapat diperoleh, jangan transfer isi file; tampilkan pesan coba lagi.
4. Jalankan pemeriksaan umum untuk semua file. Lanjutkan pemeriksaan tambahan video hanya untuk MP4 yang diizinkan.
5. Proses antrean sesuai urutan pilihan. File gagal diberi alasan dan tidak mengambil reservasi kapasitas. File lolos mengambil reservasi lokal ukuran dan slot video; file berikutnya diperiksa terhadap sisa setelah reservasi tersebut. Pilihan baru ditambahkan ke antrean yang sama, bukan menghitung ulang dari sisa awal.
6. Jika tidak ada file lolos, jangan membuat batch upload server. Jika ada, buat batch untuk file lolos dan mulai transfer otomatis.
7. Untuk baseline, transfer **satu file per request**, berurutan pada kedua komponen. Ini mencegah kumpulan file valid secara individual melampaui batas body request gabungan. Sesuaikan orkestrasi Livewire yang saat ini memakai `$uploadMultiple`; jangan sekadar mengganti argumen tanpa menyesuaikan akhir batch.
8. Persentase transfer 100% diikuti status **Menyimpan** sampai server mengonfirmasi file final. Jangan menampilkan Tersimpan hanya berdasarkan callback temporary upload.
9. Segarkan daftar file, versi workspace, aturan/sisa kapasitas dan jumlah video setelah konfirmasi final. Pindahkan reservasi lokal menjadi penggunaan server, tanpa menghitungnya dua kali. Lepaskan reservasi hanya setelah penolakan final, pembatalan terkonfirmasi, atau rekonsiliasi server.
10. File tersimpan muncul dalam daftar yang sama. Dokumen mengikuti aksi lihat/unduh yang sudah ada; video memperoleh Buka Video di tab baru dan Unduh sesuai izin.

Pemrosesan metadata video dibatasi satu file pada satu waktu. File yang sedang diperiksa tidak boleh melompati urutan antrean dan memakai sisa kapasitas yang sudah diberikan kepada file lain. Status antrean harus tetap responsif pada ponsel.

## 5. Matriks validasi

| Pemeriksaan | Frontend sebelum transfer | Backend penerimaan |
| --- | --- | --- |
| File dipilih dan ukuran positif | Wajib; tolak file kosong | Wajib |
| Ekstensi diizinkan | Wajib, normalisasi huruf besar/kecil; ekstensi terakhir | Wajib |
| Batas ukuran berkas bersama | Wajib, `File.size` | Wajib, byte aktual |
| MIME | `File.type` sebagai informasi pendukung; MIME kosong bukan alasan tunggal menolak | MIME terdeteksi dari isi harus sesuai pasangan ekstensi dan allowlist |
| Sisa kuota dan slot video | Wajib bila berlaku, termasuk reservasi seluruh antrean lokal | Wajib, transaksi/reservasi untuk upload bersamaan |
| Struktur/keabsahan PDF, gambar, Office, teks | Baseline tidak mengklaim membaca atau membuktikan seluruh isi file | Pemeriksaan MIME bukan bukti seluruh dokumen bebas kerusakan atau malware |
| Container, codec dan resolusi video | Wajib melalui validator video lokal, sesuai rancangan 14 | Penerimaan minimum MIME/ukuran dan validasi bentuk metadata; tidak ada ffprobe wajib pada rilis awal |
| Durasi video | Informasi opsional; tidak dibatasi dan tidak menjadi dasar penolakan | Tidak ada validasi batas durasi; metadata tidak terbaca disimpan null |
| Hak akses, periode, kebijakan terkini | Tampilkan keadaan; hentikan UI bila sudah tidak boleh | Wajib diperiksa ulang |
| Integritas, duplikasi retry, penyimpanan | Kunci lokal/per-file stabil | UUID, kunci idempoten dan hash dari file sebenarnya |

Frontend menerima semua format dokumen/gambar yang saat ini diizinkan tanpa menambahkan parser berat wajib untuk tiap format. `File.type` dan ekstensi bukan bukti isi. Pemeriksaan signature awal dapat dikembangkan terpisah setelah diuji, tetapi tidak menggantikan backend; DOCX/XLSX yang sama-sama ZIP tidak boleh diidentifikasi hanya dari awalan ZIP.

Untuk video, parser MP4 dan metadata browser memeriksa isi media secukupnya sebelum transfer. Metadata/codec frontend masih dapat dipalsukan oleh request di luar UI. Jangan menyebut metadata klien sebagai hasil pemeriksaan server. Batas ini dijelaskan lebih lanjut pada rancangan 14.

## 6. Status, pesan dan aksesibilitas

Tetap satu area unggah dengan daftar antrean ringkas; tidak menambah menu/tab navigasi dokumen versus video. Pada mobile, nama file membungkus, status dan alasan terbaca, serta tombol dapat disentuh tanpa tabel melebar.

| Status lokal | Contoh tampilan | Tindakan |
| --- | --- | --- |
| Menunggu pemeriksaan | `ijazah.pdf — menunggu` | Dapat dikeluarkan dari antrean |
| Memeriksa | `Memeriksa berkas di perangkat Anda. File belum diunggah.` | Batalkan |
| Ditolak lokal | `ijazah.pdf: ukuran 14 MiB melebihi batas 10 MiB.` | Pilih ulang / keluarkan |
| Siap | `Lolos pemeriksaan awal; menunggu giliran unggah.` | Batalkan sebelum transfer |
| Mengunggah | Persentase transfer file aktif | Batalkan transfer |
| Menyimpan | `Transfer selesai. Menyimpan berkas...` | Tunggu konfirmasi server |
| Tersimpan | Nama, ukuran dan aksi file | Lihat/Buka Video/Unduh sesuai izin |
| Gagal server/jaringan | Nama file dan alasan yang dapat ditindaklanjuti | Coba ulang file itu / batalkan antrean |

Pesan tambahan:

- `format-file.exe: format tidak didukung. Gunakan PDF, JPG, atau PNG sesuai aturan periode ini.` Daftar format harus berasal dari aturan, bukan teks tetap.
- `berkas.pdf: file kosong. Pilih file yang berisi dokumen.`
- `Sisa kapasitas tidak cukup untuk video.mp4 setelah file sebelumnya dalam antrean. Kurangi ukuran atau keluarkan file lain.`
- `Aturan berkas berubah. File yang belum tersimpan akan diperiksa ulang.`
- `2 berkas tersimpan, 1 berkas tidak diunggah. Periksa alasan pada file yang ditandai.`

Gunakan `role="status"`/`aria-live="polite"` untuk perubahan status dan ringkasan kesalahan yang aksesibel. Jangan hanya memakai warna; tampilkan nama file dan alasan. Tidak menampilkan alert berulang setiap kenaikan 1% progress. Kesalahan file tidak menutup atau mereset daftar file lain.

## 7. Batch campuran, retry, pembatalan dan kirim final

- Ikat hasil validator, metadata video, versi kebijakan dan kunci permintaan ke identitas file yang sama. Nama file/indeks array tidak cukup karena urutan dapat berubah dan nama dapat sama.
- Retry hanya file gagal. Bila hasil penerimaan tidak diketahui akibat jaringan putus, rekonsiliasi status menggunakan kunci yang sama sebelum membuat transfer baru. Jangan menggandakan file yang sebenarnya sudah tersimpan.
- Setelah refresh halaman, objek file lokal tidak tersedia lagi: minta pilih ulang, cocokkan dengan identitas/fingerprint dan konfirmasi status server; jangan mengklaim dapat melanjutkan transfer dari byte terakhir. Tidak ada resumable pada rilis awal.
- Pembatalan pemeriksaan menghentikan pembacaan/worker serta membuang hasil yang terlambat. Ganti periode/file/versi aturan membatalkan hasil lolos lama. Object URL dan buffer video dilepaskan.
- Pembatalan temporary upload tidak menghapus file yang sudah dikonfirmasi tersimpan. Bersihkan temporary file dan batch yang ditinggalkan menurut kebijakan server.
- File yang ditolak sebelum transfer **tidak dibuat menjadi batch gagal di database**. Tampilkan bahwa file tersebut belum termasuk pendaftaran; pengguna dapat memperbaiki atau mengeluarkannya. Status ini tidak menjadi syarat wajib baru yang tersembunyi.
- Sinyal kesibukan untuk tombol kirim berlaku selama pemeriksaan lokal, antrean siap, transfer atau penyimpanan masih aktif. Batch gagal setelah transfer tetap harus dicoba ulang atau dibatalkan sesuai mekanisme yang sudah ada. Server tetap memeriksa guard batch sebelum kirim final.
- Kirim final hanya menyertakan file yang sudah tersedia di server. Pemeriksaan frontend tidak mengubah snapshot pengajuan lama atau aturan wajib dokumen yang berlaku.

## 8. Penerimaan backend dan batas transport

Pertahankan validasi server, termasuk pada request langsung dan dari operator. Perbaiki jalur peserta agar ukuran, ekstensi serta pasangan ekstensi-MIME diperiksa sebelum menulis file final. Pengecekan ulang kuota/jumlah dan versi kebijakan dilakukan dalam transaksi; kegagalan setelah penulisan harus membersihkan file tanpa meninggalkan record tersedia yang tidak utuh.

Saat aturan berubah, hentikan antrean yang belum dikirim, muat aturan baru dan ulangi pemeriksaan lokal. Untuk file yang sudah tertransfer, tolak penerimaan dengan konflik yang jelas bila versi tidak sesuai; bersihkan temporary file. Jangan otomatis mengirim ulang seluruh antrean, karena dapat menghabiskan bandwidth.

Plafon temporary upload, PHP, Nginx/proxy/CDN dan aplikasi harus selaras. Batas 100 MiB sementara Livewire yang ada bukan berarti setiap format diizinkan sebesar itu. Batas tiap file, termasuk video, memakai `max_file_size_bytes` pada kebijakan periode. Angka 100 MiB hanya plafon teknis saat telaah, bukan batas video baru. Sesuaikan transport dengan ukuran yang diizinkan pengaturan berkas; tidak menambah input ukuran video.

## 9. Usulan pembagian kode

| Sasaran | Perubahan yang dirancang |
| --- | --- |
| `DocumentPolicyVersion`/helper | Ekspor satu kontrak aturan menurut konteks, batas ukuran bersama dan versi |
| `resources/js/file-upload-validator.js` | Validator umum murni: kosong, ekstensi, ukuran, kuota/slot; hasil terstruktur per file |
| `resources/js/video-file-validator.js` | Validator MP4 tambahan; parser dimuat hanya jika ada video yang perlu diperiksa |
| Orkestrator antrean bersama | Identitas file, reservasi lokal, pembatalan, callback final, retry dan pemetaan metadata |
| View `document-manager` dan `period-document-manager` | Panggil validator sebelum upload; satu input/dropzone lama; tampilkan antrean dan alasan yang sama |
| Komponen Livewire terkait | Kontrak batch/per-file dan metadata; cegah batch selesai terlalu awal saat transfer dibuat serial |
| `UploadApplicantDocument`/`UploadPeriodDocument` | Pemeriksaan penerimaan dan pasangan MIME konsisten; kuota/jumlah tetap menurut konteks |
| SubmitRegistration | Sinyal busy frontend untuk pemeriksaan/antrean dan guard server untuk batch; bukan validasi berdasarkan DOM saja |

Nama modul baru adalah usulan. Tidak membuat controller/menu khusus unggah video. Tidak perlu migrasi untuk status pemeriksaan lokal; kolom kebijakan dan metadata video tetap mengikuti rancangan 14. Jangan menyimpan isi file ke localStorage atau mengirimnya ke layanan validasi pihak ketiga.

## 10. Tahap implementasi dan penerimaan

1. Kontrak kebijakan dan validator umum; integrasi semua mode DocumentManager serta PeriodDocumentManager.
2. Antrean campuran serial, reservasi, pesan per-file, pembatalan, rekonsiliasi retry dan guard kirim final.
3. Validator video rancangan 14 sebagai perluasan pada area yang sama; tautan Buka Video di tab baru.
4. Penyelarasan backend, batas transport, pembersihan dan UAT perangkat target. Video tetap nonaktif sampai diizinkan kebijakan.

Matriks minimum:

1. PDF/gambar/Office/teks yang diizinkan tetap dapat diunggah; ekstensi kapital diterima dan file tanpa ekstensi/tidak diizinkan ditolak jelas.
2. File kosong, ukuran tepat batas, satu byte di atas batas dan sisa kuota diperiksa. Berkas ditolak lokal tidak menghasilkan request berisi file, termasuk temporary upload; buktikan melalui Network/intersepsi request browser.
   Pengaturan ukuran yang sama harus menghasilkan keputusan yang sama pada PDF, gambar dan video. Mengubah ukuran pada Aturan Berkas memperbarui semua format; video panjang tetap lolos bila ukuran dan pemeriksaan formatnya sesuai, tanpa batas durasi tambahan.
3. Pemilih file, drag-and-drop dan pemilihan campuran melewati validator yang sama pada ketiga mode peserta/pengelola dan berkas periode.
4. MIME browser kosong tidak otomatis menolak file sah; mengganti nama `.exe` menjadi `.pdf` tetap ditolak oleh penerimaan konten server. Tidak mengklaim frontend membuktikan semua dokumen sah.
5. Dua file bernama sama, pemilihan tambahan saat antrean berjalan, reservasi slot video dan kuota beberapa file dihitung tanpa duplikasi.
6. Sebagian gagal lokal/server: file lain yang lolos tersimpan, ringkasan tepat, retry tidak menggandakan keberhasilan. Rekonsiliasi respons hilang setelah server menyimpan diuji.
7. Ganti file/periode/aturan, logout, batal, parser timeout, jaringan putus dan refresh tidak memakai hasil pemeriksaan usang atau meninggalkan busy selamanya.
8. Request langsung tetap menjalani izin, ukuran aktual, pasangan MIME, kuota/jumlah dan versi kebijakan di server; tab bersamaan tidak melewati kuota.
9. Seluruh matriks video rancangan 14 lulus, termasuk MP4 vertikal, metadata di akhir dan cleanup object URL; tidak ada menu baru atau player inline.
10. Tombol kirim mengikuti status lokal aktif dan batch server. File ditolak lokal dapat dikeluarkan tanpa batch database macet; pengajuan tidak mengikutsertakan file yang belum tersimpan.
11. UI diuji pada Chrome Android, Safari iPhone dan desktop: pesan terbaca, keyboard/fokus aksesibel, progress tidak membekukan halaman.
12. Pemeriksaan frontend memakai tes JavaScript/fixture file nyata, serta uji browser dengan intersepsi Network; tes PHP saja tidak membuktikan pencegahan transfer di browser. Backend tetap diuji melalui request/action yang sudah ada. Alat tes browser dipilih saat implementasi; tidak memasang dependensi pada tahap rancangan.

Gate selesai: pola sama pada semua jalur, tidak ada transfer file yang ditolak lokal, tidak ada menu baru, validasi backend tidak berkurang, dan bukti uji frontend/backend serta UAT tersedia. Hasil tes lokal dan batas UAT yang masih diperlukan dicatat pada [catatan implementasi](../implementasi/validasi-frontend-dan-video.md); matriks ini tetap menjadi acuan penerimaan.
