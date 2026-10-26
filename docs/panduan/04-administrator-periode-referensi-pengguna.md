# 04 — Administrator: periode, referensi, dan pengguna

[Daftar panduan](README.md) · [Berikutnya: impor dan penugasan](05-impor-sk-dan-penugasan.md)

## 1. Urutan persiapan penerimaan

1. Masuk sebagai administrator dan pastikan kata sandi awal sudah diganti.
2. Buat periode berstatus Draf beserta jadwalnya.
3. Buat akun operator; minta pengguna menyelesaikan penggantian sandi awal.
4. Terbitkan referensi resmi yang lengkap.
5. Terbitkan dua keperluan pernyataan: pemeriksaan klaim dan pengiriman formulir.
6. Daftarkan operator pada periode melalui Pembagian Operator.
7. Impor peserta SK dan periksa hasil konfirmasi.
8. Jalankan pembagian peserta yang belum ditugaskan.
9. Perbarui kontak helpdesk, jam layanan, dan pengumuman.
10. Publikasikan periode dan pilih periode publik setelah persiapan selesai.
11. Periksa portal dan alur pengguna dengan akun uji terpisah pada lingkungan latihan.

## 2. Membuat periode dan gelombang

Buka **Periode & Gelombang** (`/admin/periode`), lalu isi **Buat periode penerimaan**.

| Kolom | Ketentuan | Contoh latihan |
| --- | --- | --- |
| Kode periode | Unik, maksimal 40 karakter; huruf, angka, `_`, atau `-` | `PPG-2026-G1` |
| Nama periode | Wajib, maksimal 255 karakter | PPG Tahun 2026 Gelombang 1 |
| Tahun akademik | Bentuk empat digit/empat digit | `2026/2027` |
| Gelombang | Wajib, maksimal 50 karakter | `1` |
| Waktu buka (WIB) | Tanggal dan jam mulai | Sesuai keputusan kampus |
| Waktu tutup (WIB) | Harus sesudah waktu buka | Sesuai keputusan kampus |
| Status | Draf, Dipublikasikan, atau Diarsipkan | Draf saat menyiapkan |
| Izinkan perbaikan setelah penutupan | Centang jika kebijakan mengizinkan | Sesuai kebijakan |
| Batas perbaikan (WIB) | Wajib bila opsi perbaikan aktif; sesudah waktu tutup | Sesuai keputusan kampus |
| Tampilkan sebagai periode pada portal publik | Hanya untuk status Dipublikasikan | Centang saat siap |

Klik **Simpan Periode** dan pastikan pesan berhasil muncul serta periode tercantum dalam daftar. Contoh kode dan tahun di atas hanya ilustrasi.

### Makna status dan jadwal

- **Draf:** persiapan periode; bukan periode yang menerima pengiriman pendaftar.
- **Dipublikasikan:** dapat digunakan sesuai jadwal dan kondisi buka/tutup.
- **Diarsipkan:** tidak dapat diubah melalui pengelolaan periode dan membatasi proses operasional terkait.
- **Belum dibuka:** waktu sekarang belum mencapai waktu buka.
- **Pendaftaran dibuka:** sudah mencapai waktu buka, belum mencapai waktu tutup, dan tidak ditutup manual.
- **Pendaftaran ditutup:** ditutup manual atau sudah mencapai waktu tutup.

Hanya satu periode ditandai publik pada satu waktu. Memilih periode publik baru otomatis menghilangkan penanda publik pada periode sebelumnya. Periode lain yang dipublikasikan tetap memiliki statusnya sendiri; penanda publik bukan pengganti pengaturan jadwal.

## 3. Mengubah jadwal, menutup, dan membuka kembali

Untuk mengubah periode, klik **Ubah**, perbarui kolom, lalu **Simpan Periode**. Bila muncul konflik perubahan pengguna lain, muat ulang, baca nilai terbaru, lalu ulangi perubahan yang masih diperlukan.

Untuk penutupan manual:

1. Isi **Alasan tutup/buka manual**, 10–1.000 karakter.
2. Pada periode berstatus Dipublikasikan, klik **Tutup manual**.
3. Baca konfirmasi lalu lanjutkan.
4. Pastikan label menunjukkan pendaftaran ditutup.

Untuk membuka kembali, tulis alasan dan klik **Buka kembali**. Pembukaan tetap mengikuti jadwal. Jika waktu tutup sudah berlalu, perbarui waktu tutup terlebih dahulu.

**Ketelitian jadwal:** pengiriman pertama diterima mulai tepat waktu buka dan hanya sebelum waktu tutup. Pengiriman perbaikan setelah waktu tutup mengikuti opsi dan batas perbaikan tersendiri. Pada implementasi saat ditinjau, penutupan manual menghentikan klaim dan pengiriman pertama; jendela perbaikan setelah waktu tutup tetap diperiksa melalui pengaturan perbaikannya. Periksa kedua pengaturan jika ingin menghentikan semua pengiriman.

## 4. Mengarsipkan periode

1. Pastikan klaim `pending` dan `needs_information` sudah ditindaklanjuti.
2. Pastikan pendaftaran peserta SK aktif sudah berstatus Diterima lengkap atau kondisi kepesertaannya telah diselesaikan sesuai keputusan resmi.
3. Tinjau kasus, ekspor yang dibutuhkan, serta audit.
4. Klik **Ubah**, ubah status menjadi **Diarsipkan**, dan pastikan tidak lagi ditandai publik.
5. Simpan dan periksa hasilnya.

Sistem menolak pengarsipan bila masih ada klaim terbuka atau pendaftaran peserta aktif yang belum Diterima lengkap. Periode yang sudah diarsipkan tidak menyediakan pengubahan biasa. Jangan mengarsipkan hanya untuk menyembunyikan periode dari portal; gunakan penanda publik sesuai kebutuhan.

## 5. Menerbitkan referensi formulir

Buka **Referensi & Pernyataan** (`/admin/referensi`). Referensi menyediakan pilihan biodata, wilayah, perguruan tinggi, dan program studi.

1. Siapkan berkas `.json` berdasarkan [template struktur referensi](../implementasi/referensi.template.json).
2. Ganti seluruh penanda contoh dengan data dari sumber yang telah diperiksa dan disahkan.
3. Gunakan `version` unik dan `source` yang menjelaskan asal data.
4. Periksa kelengkapan struktur pada tabel di bawah.
5. Pilih berkas pada **Dataset referensi yang disahkan (.json)**; ukuran maksimal **10.240 KB**.
6. Centang konfirmasi sumber dan kelengkapan.
7. Klik **Validasi & Terbitkan Referensi**.
8. Pastikan versi baru tampil Aktif. Dataset yang sebelumnya aktif menjadi Riwayat dan tetap tersimpan.

| Bagian JSON | Isi dan aturan |
| --- | --- |
| `version` | Teks unik, maksimal 64 karakter |
| `source` | Sumber data, maksimal 255 karakter |
| `options` | 6–500 entri; semua kelompok `gender`, `religion`, `nationality`, `residence`, `transport`, `special_need` harus ada |
| Entri `options` | `group`, `code`, `label`; kode tidak boleh berulang pada kelompok yang sama |
| `regions` | 3–10.000 entri; `code` unik, `parent_code`, `level`, `name` |
| Hubungan wilayah | Provinsi tanpa induk; kota/kabupaten berinduk provinsi; kecamatan berinduk kota/kabupaten |
| `pt` | 1–10.000 entri; `external_id` unik dan `name` |
| `prodi` | 1–30.000 entri; `external_id` unik, `pt_external_id`, `name`; induk PT harus ada pada dataset yang sama |

Penerbitan langsung mengaktifkan versi baru untuk formulir yang memakai referensi aktif. Koordinasikan perubahan saat penerimaan berjalan. Pilihan lama pada draf mungkin perlu dipilih ulang; saat verifikasi, aplikasi juga memeriksa validitas data terhadap referensi aktif. Riwayat dataset dipertahankan untuk pembacaan catatan sebelumnya.

## 6. Menerbitkan naskah pernyataan

Pada halaman yang sama, gunakan **Terbitkan pernyataan baru**.

1. Pilih **Keperluan**: Pemeriksaan klaim atau Pengiriman formulir.
2. Isi **Versi**, maksimal 40 karakter. Kombinasi keperluan dan versi harus belum pernah digunakan.
3. Isi naskah resmi sepanjang 20–5.000 karakter.
4. Centang bahwa naskah telah disahkan pengelola kampus.
5. Klik **Terbitkan Pernyataan**.
6. Periksa naskah pada daftar pernyataan yang diterbitkan.
7. Ulangi untuk keperluan lain bila belum tersedia.

Versi lama tidak ditimpa. Formulir menggunakan naskah terbaru untuk keperluannya. Jika naskah berubah saat pengguna masih membuka halaman lama, pengguna harus memuat ulang dan menyetujui versi baru sebelum mengirim.

## 7. Membuat dan mengelola akun pengelola

Buka **Kelola Pengguna** (`/admin/pengguna`). Halaman ini mengelola akun operator dan administrator.

1. Isi nama pengguna.
2. Isi email jika diperlukan; email harus unik. Jika penugasan melalui kolom `operator_email` pada impor akan digunakan, operator harus mempunyai email yang sesuai.
3. Tentukan ID login unik, maksimal 64 karakter, berupa huruf kecil, angka, titik, garis bawah, atau tanda hubung.
4. Pilih peran Operator atau Administrator.
5. Isi sandi awal minimal 12 karakter beserta konfirmasinya.
6. Simpan akun.
7. Sampaikan akses kepada pengguna melalui kanal yang sesuai dan minta mengganti sandi pada login pertama.

Untuk mengubah status akun, gunakan tindakan status pada baris pengguna dan isi alasan 10–1.000 karakter. Penonaktifan mencabut sesi tersimpan. Anda tidak dapat menonaktifkan akun sendiri maupun administrator aktif terakhir.

Menonaktifkan operator tidak otomatis mendistribusikan ulang tugasnya. Tinjau dan pindahkan tugas menggunakan prosedur penugasan agar layanan tetap berjalan.

## 8. Memperbarui informasi publik

Buka **Pengaturan Aplikasi** (`/admin/pengaturan`).

| Kolom | Ketentuan |
| --- | --- |
| Nama helpdesk | Wajib, maksimal 255 karakter |
| WhatsApp helpdesk | Opsional; format internasional tanpa spasi, 8–15 digit dengan tanda `+` opsional |
| Jam layanan | Opsional, maksimal 100 karakter; jelaskan hari dan jam WIB |
| Pengumuman | Opsional, maksimal 2.000 karakter |

Simpan, kemudian buka portal publik untuk memeriksa informasi yang terlihat pengguna. Bila pengaturan berubah oleh administrator lain saat halaman masih terbuka, muat ulang dahulu agar tidak menimpa perubahan terbaru.
