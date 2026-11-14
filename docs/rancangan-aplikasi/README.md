# Rancangan lengkap aplikasi PMB PPG IAKN Palangka Raya

Tanggal penyusunan: **18 Oktober 2026**, zona waktu bisnis **Asia/Jakarta**. Status: **rancangan untuk implementasi dan pembahasan pengelola**. Bahasa antarmuka: Indonesia. Acuan utama: [`../ppgui`](../ppgui/README.md).

Tujuan aplikasi adalah menangani peserta yang telah ditetapkan dalam SK: memperoleh akses, mencocokkan kepesertaan, mengisi formulir, memperbaiki data, diperiksa operator, dan mendapat keputusan **diterima lengkap**. Integrasi Neo Feeder merupakan rilis terpisah sesudah proses PMB stabil.

**Keputusan tampilan:** seluruh halaman produksi menggunakan **Bootstrap 5**, Blade, Livewire 4, dan CSS khusus identitas PPG, mengikuti prototipe `docs/ppgui`. Tailwind tidak digunakan untuk tampilan produksi. Penggantian dependensi dan konfigurasi bawaan proyek termasuk pekerjaan fase F02.

## Susunan dokumen

| Urutan | Dokumen | Isi dan pemakai utama |
| --- | --- | --- |
| 00 | [Dasar rancangan dan hasil telaah](00-dasar-rancangan.md) | Kondisi nyata, daftar sumber, perbedaan demo dengan produksi; semua tim |
| 01 | [Kebutuhan dan cakupan](01-kebutuhan-dan-cakupan.md) | Kebutuhan bernomor, peran, ukuran keberhasilan, batas rilis; pengelola dan analis |
| 02 | [Alur bisnis dan aturan status](02-alur-bisnis.md) | Klaim, WhatsApp, impor, penugasan, formulir, konflik, penutupan; pengelola dan pengembang |
| 03 | [Rancangan halaman dan pengalaman pengguna](03-rancangan-antarmuka.md) | Seluruh menu prototipe, komponen, keadaan layar, validasi; desainer dan frontend |
| 04 | [Basis data dan kamus data](04-basis-data.md) | Entitas, kolom, tipe, relasi, indeks, transaksi, migrasi; backend |
| 05 | [Arsitektur dan kontrak aplikasi](05-arsitektur-dan-kontrak.md) | Struktur Laravel/Livewire, route, action, policy, kesalahan, keamanan; pengembang |
| 06 | [Impor, referensi, dan integrasi](06-impor-referensi-integrasi.md) | Kontrak Excel, Google, WhatsApp manual, Neo, cetak, ekspor; pengembang integrasi |
| 07 | [Tahapan pembangunan terperinci](07-tahapan-pembangunan.md) | Urutan kerja, dependensi, berkas sasaran, keluaran, kriteria selesai, estimasi; pelaksana proyek |
| 08 | [Rencana pengujian dan penerimaan](08-pengujian-dan-penerimaan.md) | Matriks uji positif/negatif, konkurensi, UAT, bukti rilis; QA dan pengelola |
| 09 | [Operasional, risiko, dan keputusan](09-operasional-dan-keputusan.md) | Deployment, backup/restore, SOP, risiko, daftar keputusan yang belum disahkan; pengelola sistem |
| 10 | [Pengelolaan berkas pendaftar](10-pengelolaan-berkas-pendaftar.md) | Perluasan rancangan 2 November 2026: unggah bertahap, ubah nama, hapus permanen, akses pengelola, serta aturan dan panduan berkas oleh admin; belum diimplementasikan |
| 13 | [Penghapusan periode menyeluruh](13-penghapusan-periode-menyeluruh.md) | Rancangan 6 November 2026: konfirmasi kata sandi administrator, seluruh data/file periode, freeze, manifest, retry, audit, dan perlindungan data periode lain |
| 14 | [Unggah dan pemutaran video](14-unggah-video.md) | Usulan 10 November 2026: validasi frontend, batas per periode, pemeriksaan minimum server, tombol buka video di tab baru dan penerimaan |
| 15 | [Validasi frontend seluruh unggahan](15-validasi-frontend-unggahan.md) | Rancangan 10 November 2026: dokumen/gambar/video pada area unggah lama, validasi sebelum transfer, antrean campuran, kuota, retry dan pengujian |
| 16 | [Impor ulang SK berdasarkan ID](16-impor-ulang-sk-berdasarkan-id.md) | Rancangan 10 November 2026: pembaruan berdasarkan periode + id_peserta_sk, diff, penundaan konflik, perlindungan data terikat dan commit |
| 17 | [Filter status peserta SK](17-filter-status-peserta-sk.md) | Rancangan filter status akun dan antrean, konsistensi daftar dan ekspor Excel, serta pengujian |
| 18 | [Statistik peserta SK di beranda](18-statistik-peserta-sk-beranda.md) | Rancangan 14 November 2026: grafik Highcharts per provinsi, kabupaten/kota dan periode; cakupan publik, filter, cache, aksesibilitas, lisensi dan pengujian |

## Cara menggunakan

Rancangan impor ulang terbaru: **[16 — Impor ulang SK berdasarkan ID](16-impor-ulang-sk-berdasarkan-id.md)** mengganti kunci pembaruan nomor SK/urut dengan periode + `id_peserta_sk`. Implementasi lokal tersedia; baca [perilaku aktual dan langkah rilis](../implementasi/impor-ulang-sk-id.md). Perlindungan klaim dan penugasan tetap berlaku.

Rancangan unggah: **[15 — Validasi frontend seluruh unggahan](15-validasi-frontend-unggahan.md)** menjadi pola bersama semua format/peran pada area Unggah Berkas yang sudah ada. **[14 — Unggah dan pemutaran video](14-unggah-video.md)** melengkapi pemeriksaan media dan akses tab baru. Implementasi lokal tersedia; lihat [perilaku aktual, pengujian dan langkah rilis](../implementasi/validasi-frontend-dan-video.md). Tidak menambah menu unggah video terpisah.

Perluasan terbaru: **[11 — Formulir, berkas, dan autosave](11-formulir-berkas-dan-autosave.md)**, usulan 5 November 2026 untuk menggabungkan perjalanan pengisian dan unggah, menyimpan draf otomatis, serta memindahkan pengiriman final ke halaman berkas. Dokumen ini juga mengusulkan perubahan eksplisit atas aturan pengubahan berkas setelah kirim pada rancangan 10.

1. Baca 00–02 untuk menyamakan pemahaman bisnis. Cocokkan setiap layar pada 03 dengan prototipe.
2. Gunakan 04–06 sebagai rancangan teknis yang saling melengkapi. Nama tabel dan berkas di sana adalah sasaran pembangunan, kecuali diberi label **sudah ada**.
3. Kerjakan tiket pada 07 secara berurutan menurut dependensi; tandai selesai hanya jika keluaran dan uji penerimaannya tersedia.
4. Gunakan 08 sebagai daftar penerimaan. Catat keputusan kampus dan hasil operasi pada 09 sebelum membuka periode nyata.

## Kedudukan keputusan

- **Teramati:** dapat dibuktikan dari kode/prototipe saat telaah; belum tentu fitur produksi.
- **Ditetapkan dalam rancangan:** usulan teknis konkret agar implementasi dapat dimulai. Dapat direvisi dengan catatan dampak.
- **Perlu keputusan pengelola:** kebijakan institusi atau kontrak eksternal yang belum tersedia. Nilai contoh tidak boleh dijadikan nilai produksi.

Jika ada pertentangan, kebutuhan bisnis dalam rancangan MVP sebelumnya menjadi bahan dasar, sedangkan paket ini menjelaskan dan menyelesaikan celah implementasinya. Kebijakan kampus yang disahkan kemudian mengungguli asumsi rancangan. Perubahan harus diperbarui serentak pada alur, skema, tiket, dan pengujian yang terdampak.

## Batas hasil pekerjaan ini

Paket ini merupakan baseline rancangan, bukan laporan penyelesaian implementasi. Pengembangan aplikasi telah dimulai pada pekerjaan berikutnya; lihat [fitur dan hasil pengujian aktual](../implementasi/README.md) serta [status tiap fase dan pekerjaan tersisa](../implementasi/kemajuan.md). Dokumen asal dan prototipe dipertahankan. Status rilis produksi hanya berubah setelah gate operasional dan penerimaan benar-benar dipenuhi.
