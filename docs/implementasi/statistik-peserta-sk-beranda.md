# Statistik peserta SK di beranda

Tanggal implementasi lokal: **14 November 2026**. Acuan: [rancangan 18](../rancangan-aplikasi/18-statistik-peserta-sk-beranda.md). Status: implementasi dan pengujian otomatis lokal tersedia; verifikasi visual browser serta pemeriksaan lisensi production belum dinyatakan selesai.

## Perilaku yang tersedia

Beranda `/` menampilkan bagian **Sebaran Peserta SK** setelah informasi periode penerimaan, sebelum alur pendaftaran:

- Ringkasan total kepesertaan SK aktif, jumlah provinsi, dan jumlah kabupaten/kota terwakili.
- Grafik batang horizontal per provinsi, per kabupaten/kota, dan per periode publik.
- Filter periode untuk ringkasan dan sebaran wilayah; perbandingan periode tetap mencakup semua periode publik dengan peserta aktif. Periode terpilih diberi warna emas.
- Pilihan provinsi, pencarian nama/kode kabupaten/kota, klik batang provinsi/periode, dan Reset statistik.
- Area gulir untuk seluruh kategori; tidak ada batas top-10 atau penggabungan wilayah menjadi “Lainnya”. Nama periode yang panjang mendapat jarak antarkategori lebih besar.
- Tabel HTML untuk setiap grafik. Tanpa JavaScript tabel tetap tersedia dan kontrol interaktif disembunyikan. Setelah grafik berhasil dimuat, tabel dapat dibuka melalui **Lihat tabel data**.
- Keterangan cakupan dan snapshot dalam WIB, jumlah peserta yang wilayahnya belum tersedia, serta keadaan kosong/pemuatan/kegagalan dengan tombol coba lagi.

Ringkasan tidak berubah saat hanya provinsi atau pencarian kabupaten/kota diubah. Persentase kabupaten/kota memakai total kepesertaan pada cakupan provinsi/periode sebelum pencarian, termasuk kepesertaan yang kabupaten/kotanya belum tersedia. Semua periode menghitung kepesertaan, bukan orang unik lintas periode.

## Cakupan dan sumber data

Sumber adalah baris `sk_participants` dengan `roster_state = active` pada periode `is_public = true` dan `state = published`. Periode yang waktunya sudah ditutup tetap termasuk selama memenuhi syarat tersebut. Draf, periode privat dan arsip dikecualikan.

Peserta belum mempunyai akun, akun nonaktif dan penahanan administratif tidak mengubah hitungan kepesertaan aktif. Peserta yang mundur tidak dihitung. Staging impor, klaim, biodata dan antrean aplikasi bukan sumber penghitungan. Penghapusan riwayat impor tidak mengurangi statistik selama peserta masih tersimpan.

Kode wilayah diperlakukan sebagai teks dan dipangkas dari whitespace. Referensi pada dataset asal memvalidasi jenis wilayah dan hubungan kabupaten/kota–provinsi; dataset aktif menyediakan nama terkini apabila cocok. Data lama tanpa ID dataset dicocokkan dengan referensi aktif. Jika referensi yang dikenal tidak memiliki nama, snapshot nonkosong dengan ID peserta terbaru menjadi fallback yang deterministik. Nama provinsi pada seluruh kabupaten/kota diselaraskan dengan nama provinsi hasil agregasi.

Kode tidak dikenal, pasangan wilayah tidak cocok atau wilayah yang belum dapat diberi nama masuk ringkasan **belum tersedia**. Pesertanya tetap termasuk total dan grafik periode. Nama yang sama pada dua kode kabupaten/kota tidak digabung.

## Endpoint dan perlindungan publikasi

`GET /statistik-peserta-sk` menerima `period_id` opsional dan mengembalikan `generated_at`, `scope`, `summary`, `periods`, `provinces` serta `cities`.

- Tanpa periode: ringkasan dan wilayah mencakup seluruh periode publik yang memenuhi syarat.
- Periode publik yang masih memenuhi syarat tetapi kehilangan seluruh peserta aktif: respons kosong HTTP 200 yang bisa direset.
- Periode privat, draf, arsip, tidak ada, atau parameter cacat/array: HTTP 404 tanpa pengalihan ke seluruh periode.
- Daftar pilihan normal hanya berisi periode/wilayah dengan jumlah positif.
- Respons tidak berisi nama orang, PEG ID, nomor HP, email, status akun, penugasan operator atau data pemeriksaan.
- Endpoint dibatasi 60 permintaan per menit melalui middleware proyek. Homepage dan endpoint memakai `Cache-Control: no-store, private` agar browser/proxy tidak menyajikan statistik lama setelah publikasi diubah.

Payload awal beranda dienkode dengan `Js::encode`, tabel menggunakan escaping Blade, dan pembaruan frontend menggunakan `textContent`. Label yang masuk ke formatter Highcharts juga di-escape.

## Query dan cache: penyesuaian dari rancangan

`PublicSkParticipantStatistics` mengambil agregat SQL berdasarkan periode, kode wilayah, dataset dan variasi nama snapshot. Satu query agregat menjadi sumber jumlah ringkasan, seri wilayah dan perbandingan periode. Dua query referensi mencari dataset aktif dan wilayah yang kodenya benar-benar dibutuhkan. Data peserta per orang tidak dimuat untuk penghitungan dan tidak ada query per daerah.

Cache hasil normalisasi berlaku **300 detik**, diatur dalam `config/pmb.php` melalui `public_statistics_cache_seconds`. Implementasi memakai **revisi berdasarkan isi agregat**, menggantikan usulan hook invalidasi pada tiap jalur tulis:

1. Baca cakupan periode publik dan seluruh masukan agregat/referensi dalam transaksi baca.
2. Validasi periode terhadap cakupan terkini sebelum mengakses cache.
3. Bentuk identitas cache dari isi agregat, metadata periode, referensi dan pilihan periode.
4. Gunakan kembali hasil cache hanya apabila semua masukan tersebut masih sama.

Cara ini mendeteksi update/delete massal yang melewati observer, termasuk perubahan tanpa `updated_at`, serta pengubahan publikasi periode. Tidak diperlukan perubahan pada action impor/penghapusan atau migrasi. Konsekuensinya, query agregat dan referensi **tetap dilakukan pada setiap permintaan**; cache menghemat penyusunan payload, bukan menghilangkan query database. Ukuran dan frekuensi akses yang jauh lebih besar perlu diukur kembali sebelum beralih ke invalidasi versi berbasis kejadian.

## Frontend dan Highcharts

Paket **Highcharts 13.1.1** dikunci dalam `package.json` dan lockfile. Vite menyediakan entry khusus `public-sk-statistics.js` hanya pada beranda. Core dan Accessibility module dimuat melalui dynamic import saat bagian statistik mendekati viewport; halaman lain tidak memuat paket grafik ini. Tidak ada CDN atau layanan ekspor eksternal.

Grafik tidak digambar ulang apabila data dan pilihannya tidak berubah, sehingga mengetik pencarian kota tidak membangun ulang grafik periode/provinsi yang sama. Animasi singkat hanya dipakai ketika grafik pertama digambar, dan dimatikan pada pembaruan serta `prefers-reduced-motion`.

Pergantian periode memakai AbortController dan nomor urut permintaan. Respons lama yang tetap selesai tidak boleh menimpa respons pilihan terbaru. Pilihan yang terlihat tetap mengikuti snapshot yang sedang tampil sampai data baru berhasil diterapkan; kegagalan menjaga data sebelumnya dan memberikan penjelasan. Kegagalan modul grafik membuka kembali tabel agar seluruh angka tetap tersedia.

## Verifikasi lokal

- `PublicSkParticipantStatisticsControllerTest` dan `PublicPortalControllerTest`: **23 pengujian lulus, 155 assertion**. Mencakup agregasi, akun belum terhubung/nonaktif, penahanan, pengunduran diri, data pribadi, periode privat/draf/arsip, keadaan kosong, kode tidak valid, referensi multi-versi, fallback nama, escaping, cache dan perubahan massal/publikasi.
- `npm run test:statistics`: **6 pengujian JavaScript lulus**. Mencakup pilihan kota, penyebut persentase, seluruh 71 kategori, konfigurasi gulir, reduced motion, pemilihan periode, escaping tooltip, pembagian nol, reset, respons cacat dan balapan permintaan.
- `composer test`: **939 pengujian PHP lulus, 6.841 assertion**. Pemeriksaan format Pint dan analisis tipe PHPStan juga lulus tanpa kesalahan. `git diff --check` lulus.
- `npm run build` berhasil. Entry statistik sekitar 9,6 kB sebelum gzip; Core sekitar 278 kB dan Accessibility sekitar 137 kB dimuat terpisah saat diperlukan.
- Pemeriksaan impor ESM mengonfirmasi Core dan Accessibility memakai instance Highcharts 13.1.1 yang sama; factory grafik dan komponen aksesibilitas tersedia.
- HTTP nyata ke `http://ppg.test/` dan entry JavaScript menghasilkan 200. Endpoint lokal mengembalikan **350 kepesertaan, 9 provinsi, 71 kabupaten/kota**, tanpa wilayah tidak tersedia, sesuai cakupan publik pada waktu pemeriksaan.
- Pengukuran sederhana endpoint melalui PowerShell mencatat 365 ms pada permintaan awal rangkaian dan **74 ms / 76 ms** pada dua permintaan berikutnya. Ini hasil lokal, bukan SLA production atau hasil benchmark beban.
- Verifikasi visual desktop/ponsel dan interaksi browser belum selesai: Computer Use menghentikan sesi karena tidak dapat memastikan URL aktif untuk penegakan kebijakan. Tidak ada klaim bahwa tampilan sudah diperiksa melalui screenshot atau seluruh navigasi keyboard telah diuji langsung.

## Pemeriksaan sebelum rilis production

Jalankan `composer test`, `npm run test:statistics`, dan `npm run build` pada lingkungan rilis. Sertakan manifest serta seluruh aset build baru. Tidak ada migrasi database baru untuk fitur ini.

Periksa langsung pada desktop dan ponsel sekitar 360 px: gulir semua wilayah, label panjang, klik batang, dropdown, pencarian, reset, tabel, keyboard, pengurangan animasi serta pesan ketika jaringan/modul grafik gagal.

Pastikan institusi memiliki lisensi Highcharts yang mencakup penggunaan operasional situs publik, sesuai catatan [lisensi pada rancangan](../rancangan-aplikasi/18-statistik-peserta-sk-beranda.md#8-lisensi-highcharts). Pekerjaan ini tidak membeli lisensi atau melakukan deployment production.
