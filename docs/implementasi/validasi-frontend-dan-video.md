# Validasi frontend dan video pada unggah berkas

Pembaruan: **11 November 2026**, Asia/Jakarta. Implementasi tersedia di lokal, belum merupakan pernyataan deployment production. Acuan: [rancangan video](../rancangan-aplikasi/14-unggah-video.md) dan [pola validasi frontend](../rancangan-aplikasi/15-validasi-frontend-unggahan.md).

## Penggunaan

Semua format memakai area **Unggah Berkas** yang sudah ada. Tidak ada menu atau halaman Unggah Video terpisah. Berlaku pada berkas pendaftar, pengelolaan workspace/pemeriksaan oleh petugas, serta berkas informasi periode.

Pengguna dapat memilih atau menyeret beberapa file. Browser memeriksa file kosong, ekstensi, ukuran per file, sisa kuota dan slot video, termasuk reservasi file sebelumnya dalam antrean. File yang lolos diunggah otomatis satu per satu. File ditolak tetap ditandai dengan nama/alasan; file lain dapat tersimpan. Kegagalan lokal tidak membuat batch gagal di database.

Status membedakan pemeriksaan lokal, menunggu giliran, transfer, penyimpanan dan Tersimpan. Persentase 100% bukan konfirmasi penyimpanan final. Coba ulang hanya memproses file terkait dengan kunci stabil; jika respons hilang setelah file tersimpan, status direkonsiliasi sebelum transfer ulang. Pembatalan tidak menghapus file yang sudah tersimpan.

Tombol kirim mengikuti kesibukan frontend, sementara batch server tetap melindungi pengiriman ketika transfer/penyimpanan aktif atau gagal dan belum dibatalkan. Berkas yang tidak tersimpan tidak masuk snapshot pengajuan.

## Mengaktifkan video

1. Buka `/admin/aturan-berkas`, pilih periode.
2. Pilih format video **MP4**, **MOV**, dan/atau **WebM** yang diperbolehkan.
3. Atur **Batas Ukuran Maksimal per File** yang sudah ada. Batas ini sama untuk PDF, gambar dan video; tidak ada batas ukuran video terpisah.
4. Isi kapasitas total workspace; wajib terbatas jika salah satu format video aktif. Atur jumlah video 1–30 per workspace peserta, awal 1. Batas jumlah/kuota peserta tidak diterapkan pada berkas informasi periode.
5. Publikasikan versi kebijakan baru. Format baru MOV dan WebM perlu dipilih dan dipublikasikan; aturan lama tetap berlaku sampai diperbarui.

**Durasi dan resolusi video tidak dibatasi.** Sesuai pola unggah karya Jemaatku, pemeriksaan di browser mencakup ekstensi yang diizinkan, file tidak kosong, ukuran, sisa kapasitas dan jumlah video. Server memeriksa MIME yang sesuai dengan ekstensi serta batas dan izin yang berlaku. Tidak ada pemeriksaan codec, metadata, atau kemampuan pemutaran oleh browser sebelum transfer. Misalnya MP4 HEVC dapat diunggah; dukungan pemutaran bergantung pada browser penerima.

Video tersimpan menyediakan **Buka Video** dengan `target="_blank"` dan `rel="noopener noreferrer"`. Pemutar berada pada tab file browser, bukan disisipkan ke halaman aplikasi. Route tetap memeriksa izin; file peserta tetap privat. Range valid memakai 206, rentang tidak tersedia 416, tanpa membaca seluruh file ke memori PHP. Aksi Unduh tetap tersedia.

## Pemeriksaan server

Frontend bukan sumber kepercayaan untuk isi/izin file. Server memeriksa ukuran aktual, pasangan ekstensi-MIME, aturan yang berlaku, hak akses, kuota/jumlah dengan kunci transaksi, serta kunci idempoten. Pemeriksaan penerimaan peserta dilakukan sebelum penulisan permanen. File berstatus deleting atau detached yang belum dihapus tetap dihitung dalam kapasitas.

Kontrak frontend membawa ID versi aturan. Perubahan sebelum transfer menyebabkan pemeriksaan ulang; perubahan sesudah transfer menolak penyimpanan, membersihkan temporary file, dan meminta retry dengan aturan terbaru. Konfirmasi penyimpanan diikat ke nama temporary file dari callback transfer untuk mencegah hasil upload lama/cancel tertukar dengan file baru.

Metadata `client_media_metadata` dari unggahan lama tetap tersimpan. Alur baru tidak memerlukan metadata media; server tidak memeriksa codec atau resolusi dengan ffprobe. Pemeriksaan MIME tidak menjamin video dapat diputar atau semua frame bebas kerusakan. Tidak ada transkode otomatis. Temporary file yang tertinggal karena koneksi/halaman ditutup mengikuti cleanup Livewire yang sudah ada.

## Rilis

- Jalankan migrasi `2026_11_10_172233_add_video_metadata_to_document_uploads.php` dan `2026_11_10_175603_add_file_hash_to_period_documents.php` sebelum menjalankan kode baru. Migrasi menambah batas jumlah video pada versi aturan, JSON metadata klien pada kedua tabel file, dan hash SHA-256 berkas periode; tidak menambah batas durasi/ukuran video. Berkas periode lama boleh memiliki hash null; unggahan baru mencatat hash dari file server.
- `npm ci` lalu `npm run build`; sertakan seluruh aset dan manifest. Bangun ulang cache konfigurasi/view sesuai prosedur deployment.
- Selaraskan PHP/FastCGI, web server/proxy/CDN, temporary storage dan disk dengan maksimal ukuran berkas yang akan diizinkan. Plafon aplikasi/temporary Livewire saat ini 100 MiB. Jika memakai batas tersebut, contoh transport adalah `upload_max_filesize=100M`, `post_max_size=128M`, body limit minimal 128 MiB; ini perlu diverifikasi pada server target.
- Satu file per request menghindari agregat beberapa video melampaui post/body limit. Retry dapat mengulang transfer file dari awal; belum ada resumable upload.
- Berkas dan snapshot lama tetap tersedia sesuai izin. Penonaktifan format video tidak menghapus video yang sudah diterima.

## Pengujian dan batas QA

Perintah yang dapat diulang:

Pengujian mencakup batas format, kuota dan jumlah, termasuk file HEVC, MOV dan WebM serta akses video di tab baru.

```text
php artisan test --compact
npm run test:uploads
npm run build
npm run test:uploads:browser
npm run test:uploads:livewire -- applicant
npm run test:uploads:livewire -- staff
npm run test:uploads:livewire -- period
```

Tes PHP mencakup mode pendaftar/operator/admin, aturan, metadata durasi tanpa batas, kuota/jumlah video termasuk deleting, pasangan MIME, stale policy, idempotensi, privasi dan Range 206/416. Tes JavaScript mencakup antrean, penolakan tanpa transfer, pemilihan campuran/nama sama, reservasi, perubahan aturan dan retry.

Harness Chrome headless terisolasi menguji antrean dan intersepsi Network menggunakan fixture sintetis. File yang gagal pemeriksaan lokal tidak ditransfer.

Tes browser lokal Laravel/Livewire memakai akun/periode QA sementara pada `ppg.test`, lalu membersihkannya. Script menolak APP_ENV=production, menggunakan profil browser terpisah, dan tidak memakai akun pengguna. Jalur pendaftar, pengelola workspace dan operator berkas periode diuji dengan viewport 390x844: PDF, MP4 HEVC, MOV dan WebM tersimpan; dua file ditolak tidak mencapai temporary upload; tautan video membuka tab baru; tidak ada overflow horizontal. Screenshot lokal berada di `storage/framework/testing/video-upload-*-mobile.png`.

Chrome headless dengan viewport mobile bukan perangkat Android/iPhone nyata. Safari iPhone, ponsel bermemori terbatas, file mendekati limit produksi, jumlah peserta serentak, batas proxy dan kapasitas disk/backup masih memerlukan UAT pada lingkungan target. Pengujian lokal tidak menyatakan limit infrastruktur production sudah diselaraskan.

