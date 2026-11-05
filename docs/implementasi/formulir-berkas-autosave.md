# Implementasi formulir, berkas, dan autosave

Mengimplementasikan [rancangan 11](../rancangan-aplikasi/11-formulir-berkas-dan-autosave.md). Catatan ini menjelaskan perilaku kode, bukan pernyataan kesiapan produksi.

## Alur yang tersedia

1. Peserta mengisi Identitas, Alamat, Tambahan, Pendidikan, dan Ringkasan pada halaman formulir.
2. Draf disimpan setelah jeda ketik 700 ms, setelah perubahan pilihan/blur, dan sebelum navigasi bagian. Tombol **Simpan sekarang** menjadi cadangan.
3. **Lanjut ke Berkas** menunggu pengakuan penyimpanan server sebelum berpindah.
4. Halaman berkas menampilkan kesiapan biodata, ringkasan, file yang akan dikirim, serta **Periksa & Kirim Pendaftaran**.
5. Modal persetujuan menyatukan biodata dan berkas dalam satu revisi. Pengiriman tanpa berkas tetap tersedia dengan peringatan karena policy saat ini hanya menyediakan panduan, bukan kewajiban kategori.
6. Pendaftaran yang terkirim tidak menerima pengubahan biodata/berkas. Status perbaikan membukanya kembali; pengiriman ulang menambah revisi tanpa mengganti tanggal daftar pertama.

## Pilihan implementasi

Formulir tetap memakai Blade dan Alpine agar picker wilayah serta riwayat S1–S3 yang sudah ada tidak perlu ditulis ulang. Debounce dan antrean request berada di `public/js/applicant-autosave.js`; penyimpanan berjalan melalui PATCH ber-CSRF pada route `applicant.form.autosave`. Komponen unggah dan konfirmasi final memakai Livewire. Ini penyesuaian teknis dari usulan konversi seluruh formulir ke Livewire, dengan kontrak patch/versi yang sama.

`SaveApplicantDraftPatch` hanya mengubah path yang dikirim. Nilai belum lengkap disimpan di `ApplicantWorkspace.draft_payload` menggunakan cast `encrypted:array`, terpisah dari biodata kanonis. Riwayat jenjang yang sementara tidak dipilih tetap berada di draf; jenjang di atas pendidikan terakhir dikeluarkan dari kiriman final dan dijelaskan pada modal.

Tidak ada salinan data identitas ke localStorage/sessionStorage. Penutupan tab sebelum konfirmasi server tetap berpotensi kehilangan perubahan; indikator status dan peringatan keluar menjelaskan kondisi tersebut. Konflik antartab menghentikan autosave dan meminta pengguna menyalin isian lokal sebelum reload; aplikasi tidak melakukan penggabungan otomatis yang berisiko salah.

Retry memakai batch ID tetap. Ledger menyimpan HMAC payload dan versi hasil, tanpa isi biodata. Pembersihan metadata berjalan melalui `pmb:prune-draft-operations` setiap hari dengan jendela retry 24 jam; draf pengguna tidak dihapus.

`SaveApplicantForm` menjadi batas transaksi final bersama. Route POST lama tetap ada untuk kompatibilitas, tetapi pengiriman harus membawa versi berkas, policy, dan UUID permintaan; tidak melewati pemeriksaan berkas. Komponen `Documents\SubmitRegistration` mengambil biodata dari draf di server, memeriksa referensi/consent, kemudian memanggil batas transaksi yang sama. Request final berulang tidak membuat revisi ganda.

## Berkas dan riwayat

- Versi berkas terpisah dari versi draf. Unggah/ubah nama/lepas/hapus mengubah `documents_version`.
- Batch dicatat sebelum transfer sementara dimulai. Unggahan yang dibatalkan/kedaluwarsa tidak boleh difinalisasi; kirim ditolak ketika batch aktif/gagal belum diselesaikan.
- Batch berlaku 30 menit. Antrean tertinggal dapat dibatalkan dari halaman berkas, termasuk setelah reload.
- Hasil ditangani per file. File valid tetap tersimpan; tombol coba ulang hanya memproses file gagal.
- File privat disimpan dan di-hash SHA-256 sebelum transaksi metadata; kegagalan finalisasi membersihkan file baru tersebut.
- Manifest menyimpan ID file, nama saat kirim, ukuran, MIME, hash, dan policy per revisi. Nama dalam riwayat tidak ikut berubah ketika draf perbaikan diubah.
- File yang pernah dikirim hanya dilepas dari draf perbaikan. File fisiknya dipertahankan; penghapusan database langsung dibatasi foreign key.
- File lama pada workspace yang sudah pernah dikirim diberi penanda retensi baseline. Sistem tidak mengarang manifest untuk pengiriman historis; tampilkan label riwayat lama.
- Purge berkas revisi yang telah dikirim tidak ditambahkan sebagai pekerjaan terjadwal; memerlukan kebijakan retensi institusi tersendiri.

Pemeriksaan operator dan cetakan memakai snapshot biodata revisi terakhir untuk ownership epoch saat ini; draf perbaikan yang belum dikirim tidak mengganti isi kiriman tersebut. Daftar manifest dapat dilihat pada pemeriksaan, berkas staf, status, dan bukti. Ekspor Excel memakai data efektif dan memberi label bila sumbernya draf otomatis belum dikirim.

## Pengoperasian

Jalankan migrasi sebelum membuka kode baru pada pengguna. Migrasi memakai nama indeks yang pendek agar kompatibel dengan batas MariaDB/MySQL dan dapat dilanjutkan jika DDL terputus. Migrasi tidak menghapus biodata atau berkas lama.

`PMB_AUTOSAVE_ENABLED=true` adalah nilai awal. Jika perlu dinonaktifkan sementara, ubah menjadi `false` lalu perbarui cache konfigurasi; formulir menampilkan **Simpan Draf** dan **Simpan & Lanjut ke Berkas**. Payload yang sudah tersimpan tetap dipertahankan. Jangan melakukan rollback destruktif kolom draf/manifest yang telah berisi data.

Perintah reset operasional sudah memasukkan tabel ledger, batch, dan manifest. Hak akses file tetap diperiksa di server dan mengikuti workspace, pemilik, periode, serta penugasan; koreksi kepemilikan tidak menyalin data pemilik lama.

## Verifikasi

- Tes fitur: patch parsial, enkripsi, retry, konflik versi, field terlarang, pemisahan draf/submission, consent, manifest, daftar file berubah, batch aktif, pembatalan/retry file, pengiriman tanpa berkas, dan jalur POST lama.
- Tes JavaScript: debounce, batching, ketikan saat request berjalan, retry dengan ID sama, dan penghentian ketika konflik.
- Dua proses PHP nyata pada database khusus `pmb_isolated_race`: autosave melawan autosave dan autosave melawan submit. Database aplikasi tidak dipakai untuk `migrate:fresh`.
- Pemeriksaan tipe PHP, format, dan kompilasi Blade.

Perintah:

```powershell
php artisan test tests/Feature/Admissions tests/Feature/Console/Commands/ResetOperationalDataCommandTest.php
node --test tests/Frontend/applicant-autosave.test.mjs
php vendor/bin/phpstan analyse --no-progress
php artisan view:cache
```

QA browser manual pada 360/768/1280 px, koneksi lambat, serta pembatalan transfer masih harus dibuktikan pada sesi browser yang dapat diakses. Dalam sesi implementasi, computer-use berhenti karena URL Chrome aktif tidak dapat dipastikan; tes otomatis tidak diklaim sebagai pengganti pemeriksaan visual tersebut.
