# Rancangan statistik peserta SK di beranda dengan Highcharts

Tanggal: **14 November 2026**, zona waktu **Asia/Jakarta**. Status: **diimplementasikan di kode lokal**; lihat [perilaku aktual, hasil pengujian dan batas verifikasi](../implementasi/statistik-peserta-sk-beranda.md). Halaman sasaran: `/` melalui `PublicPortalController` dan `resources/views/public/home.blade.php`. Rekomendasi berikut menjadi acuan implementasi; dokumen ini bukan laporan kondisi production.

## 1. Rekomendasi utama

Tambahkan bagian **Sebaran Peserta SK** sesudah informasi periode penerimaan dan sebelum alur pendaftaran. Sajikan tiga grafik Highcharts Core:

| Grafik | Bentuk yang direkomendasikan | Alasan |
| --- | --- | --- |
| Jumlah peserta per provinsi | Batang horizontal, jumlah terbesar di atas | Nama provinsi terbaca; besar jumlah mudah dibandingkan. |
| Jumlah peserta per kabupaten/kota | Batang horizontal dengan filter provinsi dan pencarian wilayah | Kategori jauh lebih banyak; pengunjung dapat menemukan daerahnya tanpa memenuhi seluruh halaman dengan grafik panjang. |
| Jumlah peserta per periode | Batang horizontal, urutan waktu periode | Nama periode cukup panjang; seluruh periode tetap terbaca pada ponsel. |

Gunakan sumbu jumlah dari nol, bilangan bulat, label jumlah pada batang, dan tooltip jumlah serta persentase. Pertahankan ungu sebagai warna utama, dengan emas sebagai penanda pilihan. Hindari diagram 3D, pie/donut dengan banyak daerah, dan warna berbeda untuk setiap kabupaten/kota. Peta Indonesia dapat menjadi pengembangan berikutnya; tiga grafik batang sudah memenuhi kebutuhan tanpa menambah Highcharts Maps atau data geometri wilayah.

**Hanya wilayah dengan jumlah peserta lebih dari nol yang menjadi kategori grafik atau pilihan filter wilayah.** Semua wilayah yang memenuhi syarat tetap dapat ditemukan. Jangan hanya menampilkan sepuluh wilayah terbesar lalu menyembunyikan sisanya.

## 2. Temuan lokal dan batas publikasi

Hasil pemeriksaan read-only tanggal penyusunan:

| Lingkup data lokal | Jumlah |
| --- | ---: |
| Seluruh baris peserta SK | 454 |
| Peserta pada periode `published`, `is_public = true` | 350 |
| Peserta pada periode `published`, `is_public = false` | 104 |
| Provinsi pada periode Dipublikasikan tersebut | 9 |
| Kabupaten/kota pada periode Dipublikasikan tersebut | 71 |
| Peserta publik tanpa kode provinsi / tanpa kode kabupaten/kota | 0 / 0 |

Seluruh 454 baris tersebut saat diperiksa mempunyai `roster_state = active`. Angka ini merupakan snapshot lokal, bukan angka yang ditulis permanen ke halaman atau target pengujian production.

Kartu informasi beranda memilih `is_public = true` dan `state = published`. Telaah lanjutan menemukan bahwa `is_public` sebenarnya memilih **satu periode utama beranda**; saat satu periode dipilih, aplikasi menghapus penanda tersebut dari periode lain. Pengguna menetapkan bahwa statistik harus menghitung **seluruh periode berstatus Dipublikasikan**, terlepas dari `is_public`. Dengan data lokal saat verifikasi perbaikan, cakupan ini menghasilkan **454 kepesertaan dari 2 periode, 10 provinsi dan 75 kabupaten/kota**. Angka 350, 9 provinsi dan 71 kabupaten/kota pada tabel di atas adalah snapshot periode utama pada telaah awal.

Periode yang pendaftarannya sudah ditutup tetap dihitung selama masih `published`. Penutupan waktu pendaftaran berbeda dari `archived`. Periode Draf dan Arsip tidak masuk statistik. Data pribadi peserta tetap tidak dikirim ke beranda; yang ditampilkan adalah jumlah agregat dan nama periode/wilayah.

## 3. Definisi jumlah dan sumber data

1. **Unit hitung:** satu baris `sk_participants` yang memenuhi cakupan publik dan `roster_state = active`. Peserta yang sudah mundur dikecualikan. Judul ringkasan: **Total kepesertaan SK aktif**.
2. Seseorang pada dua periode dihitung sekali pada masing-masing periode. Total lintas periode adalah jumlah **kepesertaan**, bukan jumlah orang unik. Jangan melakukan deduplikasi global berdasarkan nama atau PEG ID.
3. Akun yang belum diklaim, menunggu aktivasi, atau nonaktif tetap dihitung selama kepesertaan SK aktif. Status antrean aplikasi dan kelengkapan biodata tidak mengubah hitungan ini. `admin_hold` juga tidak mengurangi jumlah; penahanan administratif bukan pengunduran diri.
4. Gunakan `origin_province_code/name` dan `origin_city_code/name` pada peserta SK, bukan alamat domisili pada biodata. Subtitle: **Berdasarkan asal wilayah pada data SK, bukan domisili pengisian formulir**.
5. Impor yang masih berupa staging belum dihitung. Data mulai berpengaruh setelah peserta benar-benar disimpan. Menghapus riwayat impor tidak mengurangi statistik selama peserta SK masih tersimpan.
6. Kepesertaan yang dihapus dari tabel tidak lagi dihitung. Perubahan statistik tidak mengubah data peserta, akun, antrean, penugasan atau audit.

### Identitas dan kualitas wilayah

- Kode wilayah diperlakukan sebagai teks; nol awal dipertahankan. Nilai kosong dan whitespace dianggap belum terisi.
- Kelompok provinsi memakai kode provinsi. Kelompok kabupaten/kota memakai pasangan kode provinsi dan kode kabupaten/kota. Dua daerah dengan nama sama tidak digabung berdasarkan nama.
- Satu kode yang mempunyai beberapa variasi nama tetap menjadi satu kategori. Gunakan nama referensi yang sesuai jika tersedia; jika tidak, pilih snapshot nama nonkosong terbaru secara deterministik, dengan ID sebagai pemecah urutan. Jangan menggabungkan versi dataset referensi melalui join yang menggandakan baris peserta.
- Kode tidak dikenal, pasangan provinsi–kabupaten yang tidak konsisten, atau nama wilayah yang tidak dapat ditentukan dicatat sebagai masalah kualitas data. Jangan menebak wilayah dari potongan nama atau alamat.
- Grafik wilayah berisi daerah yang dapat diidentifikasi dengan jumlah positif. Peserta dengan wilayah belum terisi/tidak dapat dipetakan tetap masuk total dan grafik periode; tampilkan ringkasan terpisah **Asal provinsi belum tersedia: N** dan **Asal kabupaten/kota belum tersedia: M** jika nilainya positif. Kategori “Belum tersedia” bukan nama daerah dan tidak menjadi batang wilayah.
- Kelengkapan setiap tingkat dihitung terpisah. Provinsi yang valid masih dapat masuk grafik provinsi walaupun kabupaten/kotanya belum tersedia. Grafik kabupaten/kota memerlukan pasangan wilayah yang valid; peserta yang tidak memenuhi syarat masuk ringkasan ketidaklengkapan tingkat kabupaten/kota.

Rekonsiliasi untuk cakupan periode yang sama: jumlah seluruh batang provinsi + jumlah peserta dengan provinsi belum tersedia = total; jumlah seluruh batang kabupaten/kota sebelum pencarian/filter provinsi + jumlah peserta dengan kabupaten/kota belum tersedia = total.

## 4. Susunan bagian statistik

Urutan komponen dari atas ke bawah:

1. Judul **Sebaran Peserta SK**, penjelasan sumber, cakupan periode dan waktu snapshot data.
2. Filter **Periode untuk sebaran wilayah**, default **Semua periode Dipublikasikan**, serta tombol **Reset statistik**.
3. Tiga kartu: **Total kepesertaan SK aktif**, **Provinsi terwakili**, **Kabupaten/kota terwakili**. Ketiganya mengikuti filter periode, sebelum filter/pencarian kabupaten/kota.
4. Grafik provinsi dan grafik kabupaten/kota, berdampingan pada layar lebar dan bertumpuk pada ponsel. Filter provinsi dan pencarian berada di kartu kabupaten/kota.
5. Grafik **Perbandingan peserta per periode Dipublikasikan** selebar area konten. Grafik ini tetap membandingkan seluruh periode Dipublikasikan yang memenuhi syarat, meskipun filter sebaran wilayah sedang memilih satu periode. Periode terpilih diberi penanda dan keterangan agar perbedaan cakupan tidak membingungkan.
6. Tombol **Lihat tabel data** pada masing-masing grafik, catatan data belum lengkap bila ada, dan keterangan hitungan kepesertaan lintas periode.

Grafik provinsi diurutkan jumlah menurun, lalu nama dan kode untuk urutan stabil. Kabupaten/kota mengikuti aturan yang sama. Grafik periode diurutkan `opens_at`, lalu ID; label menampilkan nama, tahun akademik dan gelombang bila diperlukan untuk membedakan periode bernama sama. Periode dengan nol peserta tidak dijadikan batang atau pilihan filter pada versi awal.

### Banyak kategori dan layar kecil

- Sediakan tinggi area grafik sekitar 360–440 px, dengan ruang sekitar 32–40 px per kategori. Gunakan area gulir vertikal agar seluruh kategori tetap tersedia dan label tidak saling menimpa.
- Highcharts menyediakan mekanisme native scrolling melalui `scrollablePlotArea`; [opsi `minHeight`](https://api.highcharts.com/highcharts/chart.scrollablePlotArea.minHeight) menyediakan area gulir saat tinggi plot minimum melampaui area yang tersedia. Gunakan kemampuan Core yang sesuai versi terpasang, bukan mengasumsikan axis scrollbar Highcharts Stock tersedia. Lihat [dokumentasi scrolling resmi](https://www.highcharts.com/docs/chart-concepts/scrollbar).
- Pada layar sekitar 360 px, kartu menjadi satu kolom; label dapat dibungkus dan tooltip menampilkan nama lengkap. Tidak ada gulir horizontal seluruh halaman.
- Pencarian kabupaten/kota memfilter kategori, bukan mengubah total tiga kartu di atas. Tampilkan keterangan, misalnya **Menampilkan 4 dari 71 wilayah**. Persentase tooltip kabupaten/kota tetap memakai jumlah peserta pada lingkup provinsi/periode yang dipilih sebelum pencarian, bukan hanya hasil pencarian.
- Semua data yang cocok tetap ada pada grafik gulir dan tabel. Tidak ada kelompok “Lainnya” yang menghilangkan akses ke nama wilayah.

## 5. Perilaku filter dan interaksi

| Tindakan | Perubahan |
| --- | --- |
| Memilih periode | Memperbarui tiga kartu, grafik provinsi dan kabupaten/kota; mengosongkan filter provinsi dan pencarian kabupaten/kota. Grafik perbandingan periode tetap utuh, dengan pilihan disorot. |
| Mengeklik batang provinsi | Memilih provinsi pada kartu kabupaten/kota. Tersedia dropdown yang melakukan tindakan identik untuk keyboard/ponsel. |
| Memilih provinsi di kartu kabupaten/kota | Memperbarui grafik/tabel kabupaten/kota saja dan membersihkan pencarian. Ada pilihan **Semua provinsi**. |
| Mengeklik batang periode | Melakukan tindakan yang sama dengan memilih periode pada filter sebaran wilayah. |
| Reset statistik | Kembali ke semua periode Dipublikasikan, semua provinsi dan pencarian kosong; tidak mereset bagian beranda lain. |
| Tidak ada data | Tampilkan penjelasan **Belum ada data peserta SK yang dapat ditampilkan**; jangan menampilkan batang nol atau angka contoh. |
| Tidak ada hasil pencarian | Tampilkan **Wilayah tidak ditemukan pada pilihan ini**, tombol hapus pencarian, dan pertahankan ringkasan cakupan. |

Filter bersifat lokal pada bagian statistik, tidak perlu mengubah query parameter beranda. Daftar periode/wilayah dan validasi server berasal dari kumpulan periode Dipublikasikan yang sama; ID periode draf, arsip atau tidak ada memberikan respons 404 yang sama, tanpa beralih diam-diam ke semua periode. Periode Dipublikasikan yang bukan periode utama tetap dapat dipilih. Periode yang kehilangan seluruh peserta setelah halaman dimuat memberikan keadaan kosong yang wajar dan pilihan dapat direset.

Tampilkan indikator pemuatan dan cegah respons permintaan lama menimpa pilihan terbaru. Gunakan pembatalan permintaan atau nomor urut respons. Jika permintaan gagal, tampilkan tombol **Coba lagi** dan tandai bahwa data sebelumnya belum diperbarui; jangan memasangkan label filter baru dengan grafik lama seolah-olah cocok.

## 6. Rancangan integrasi Laravel dan frontend

Penerapan mengikuti struktur Laravel, Blade, Bootstrap dan Vite yang sudah dipakai proyek. Pembagian berkas berikut menjadi dasar implementasi lokal; lihat catatan implementasi untuk perilaku aktual:

| Berkas / komponen | Tanggung jawab |
| --- | --- |
| `app/Support/PublicSkParticipantStatistics.php` | Cakupan publik tunggal, agregasi jumlah, normalisasi label, metadata kelengkapan dan snapshot. |
| `PublicPortalController` | Menyediakan statistik awal bersama data beranda yang sudah ada. |
| `app/Http/Controllers/PublicSkParticipantStatisticsController.php` | Endpoint read-only `GET /statistik-peserta-sk?period_id=...` untuk penggantian periode; tidak mengembalikan model peserta. |
| `resources/views/public/partials/sk-statistics.blade.php` | Bagian statistik, ringkasan, kontrol, tabel HTML dan fallback. |
| `resources/js/public-sk-statistics.js` | Inisialisasi Highcharts, pemilihan kategori, filter kabupaten/kota, pembaruan grafik dan penanganan kegagalan. |
| CSS publik dan konfigurasi Vite | Tata letak responsif dan pemuatan aset pada beranda. |

Beranda saat ini memakai layout publik dengan `interactive = false`. Jangan mengaktifkan Livewire untuk seluruh beranda hanya untuk menggambar grafik. Gunakan Blade untuk keluaran awal dan JavaScript kecil untuk interaksi; Highcharts dapat dimuat secara dinamis ketika bagian statistik mendekati viewport. Sediakan titik pemasangan aset yang eksplisit pada layout publik setelah memeriksa mekanisme Vite/stack yang sudah ada.

Pasang paket `highcharts` melalui npm dan kunci versi dalam lockfile. Impor Highcharts Core dan Accessibility module sesuai dokumentasi versi yang dipilih. Hindari memuat Highcharts melalui URL CDN tanpa versi atau ke semua halaman aplikasi. Integrasi ESM tersedia dalam [dokumentasi resmi](https://www.highcharts.com/docs/getting-started/installation-with-esm); sintaks modul harus diverifikasi kembali saat implementasi.

### Kontrak data

Satu respons statistik memuat:

- `generated_at`: waktu snapshot dalam format ISO 8601, ditampilkan dalam WIB; tidak mengklaim waktu impor terakhir.
- `scope`: periode untuk sebaran wilayah, `roster_state = active`, dan penjelasan periode Dipublikasikan.
- `summary`: total kepesertaan, jumlah provinsi/kabupaten terwakili, serta jumlah peserta tanpa wilayah pada masing-masing tingkat.
- `periods`: agregat semua periode Dipublikasikan yang boleh tampil, masing-masing berisi ID, label dan jumlah.
- `provinces`: kode, label, jumlah peserta pada lingkup periode yang dipilih.
- `cities`: kode provinsi, kode kabupaten/kota, label dan jumlah pada lingkup periode yang sama, sebelum pemilihan provinsi dan pencarian di browser.

Dengan ukuran lokal 71 kabupaten/kota publik, semua agregat kabupaten/kota dapat dikirim sekaligus untuk periode terpilih. Pemilihan provinsi dan pencarian kemudian berlangsung di browser. Respons berisi jumlah agregat saja; tidak memuat nama orang, PEG ID, nomor HP, email, status akun, status pemeriksaan atau penugasan operator. Data grafik dan tabel menggunakan payload yang sama.

### Query, konsistensi dan cache

- Hitung melalui agregasi SQL pada `sk_participants` dan cakupan relasi periode; jangan memuat seluruh peserta ke PHP/JavaScript untuk menghitung atau menjalankan query per wilayah.
- Mulai dari peserta yang memenuhi syarat, kemudian kelompokkan berdasarkan kode. Jangan memulai dari seluruh master wilayah dan mengisi daerah kosong dengan nol. Terapkan jumlah positif sebelum mengirim kategori.
- Hindari join ke aplikasi, klaim atau referensi multi-versi yang dapat menggandakan jumlah. Penghitungan tidak membutuhkan relasi tersebut. Penyelesaian nama dilakukan dengan peta referensi tunggal yang tidak menambah baris agregat.
- Gunakan snapshot baca konsisten untuk ringkasan dan seri pada satu respons agar impor bersamaan tidak menyebabkan total berbeda. Data awal controller dan endpoint menggunakan layanan yang sama.
- Cache agregat selama sekitar 5 menit sebagai batas awal yang dapat dikonfigurasi. Grafik diberi waktu snapshot sebenarnya, bukan waktu setiap cache dibaca.
- Perubahan status periode menjadi Draf/Arsip harus segera mengeluarkan datanya. Periksa ulang daftar ID periode Dipublikasikan sebelum menggunakan cache, dan masukkan daftar tersebut ke identitas cache. Jangan menyajikan periode Draf/Arsip dari payload cache lama. Perubahan pilihan periode utama `is_public` tidak mengubah cakupan statistik.
- Implementasi lokal membentuk revisi cache dari isi agregat dan referensi yang dibaca ulang sebelum mengakses cache. Perubahan peserta, asal, roster, penghapusan, impor terkonfirmasi dan publikasi akan menghasilkan identitas cache baru, termasuk bulk update/delete yang melewati observer. Query agregat tetap dijalankan pada setiap permintaan; lihat [tradeoff cache](../implementasi/statistik-peserta-sk-beranda.md#query-dan-cache-penyesuaian-dari-rancangan). TTL menjadi batas cadangan, bukan pengganti pemeriksaan cakupan publik.
- Untuk halaman/endpoint publik gunakan kebijakan cache browser yang memvalidasi kembali cakupan akses; jangan membiarkan CDN menyimpan payload lama setelah periode disembunyikan. Tentukan ETag dan revalidation bila dipakai.
- Belum diperlukan migrasi. Ukur agregasi dan `EXPLAIN` pada data lokal serta dataset representatif sebelum mengusulkan indeks baru. Target awal: respons cache hangat di bawah 300 ms pada lingkungan uji yang dicatat; ini target penerimaan, bukan hasil ukur saat penyusunan.

## 7. Aksesibilitas, ketahanan dan ekspor

Gunakan Accessibility module untuk navigasi keyboard dan pembacaan grafik oleh teknologi bantu, sesuai [rekomendasi Highcharts](https://www.highcharts.com/docs/accessibility/accessibility-module). Tetap sediakan tabel HTML dengan judul kolom dan angka yang dapat dibaca tanpa JavaScript. Tabel menjadi sumber fallback jika modul gagal dimuat. Dropdown dan reset mempunyai label yang jelas, status pemuatan diumumkan secukupnya, dan pilihan tidak bergantung pada warna saja.

Format angka memakai lokal Indonesia, tanpa pecahan untuk jumlah peserta. Persentase dapat satu desimal, dengan penyebut dijelaskan; jika penyebut nol jangan membagi dengan nol. Animasi cukup singkat saat pertama tampil, tidak berulang, dan dimatikan untuk `prefers-reduced-motion`.

Ekspor bukan syarat tiga grafik awal. Rekomendasi tahap berikutnya: unduh PNG dan CSV agregat dari browser, beserta lingkup periode dan waktu snapshot. Jangan menautkan ekspor peserta administratif ke halaman publik. Jika memakai modul ekspor Highcharts, pastikan proses lokal dan jangan mengandalkan fallback ke server eksternal; dokumentasi menjelaskan opsi [client-side exporting dan export-data](https://www.highcharts.com/docs/export-module/export-module-overview). Tabel/ekspor lengkap harus mencakup seluruh wilayah dalam cakupan, bukan hanya bagian grafik yang sedang terlihat karena gulir.

## 8. Lisensi Highcharts

Highcharts bukan dependensi yang boleh diasumsikan bebas untuk semua penggunaan institusi. Halaman resmi [Highcharts for Education](https://www.highcharts.com/education/) menyatakan penggunaan administratif/operasional tidak termasuk cakupan pendidikan gratis. Portal penerimaan ini merupakan penggunaan operasional; sebelum rilis, periksa lisensi institusi yang sudah ada atau konfirmasikan lisensi yang mencakup situs publik kepada penyedia. Situs publik juga perlu dibedakan dari lisensi internal, sebagaimana dijelaskan dalam [FAQ lisensi resmi](https://shop.highcharts.com/faq).

Rancangan tetap menggunakan Highcharts sesuai permintaan; paket 13.1.1 telah dipasang untuk implementasi lokal. Pemilihan lisensi dan biayanya tetap menjadi kebutuhan rilis; pekerjaan ini tidak membeli lisensi. Pertahankan ketentuan atribusi/credits sesuai lisensi yang berlaku.

## 9. Tahapan implementasi dan kriteria penerimaan

1. Tetapkan cakupan publik dan definisi peserta aktif yang disarankan di dokumen ini; catat bila kampus memilih cakupan lain. Konfirmasikan lisensi untuk situs publik.
2. Buat layanan agregasi bersama dan pengujian backend sebelum memasang grafik. Uji variasi kode/nama, asal kosong, penamaan sama pada daerah berbeda, seluruh periode Dipublikasikan dengan/tanpa penanda periode utama, serta periode Draf/Arsip.
3. Tambahkan ringkasan dan tabel server-rendered ke beranda, lalu integrasikan Highcharts melalui Vite dan endpoint pergantian periode.
4. Tambahkan filter provinsi, pencarian kabupaten/kota, pemilihan batang, reset, area gulir dan keadaan gagal/memuat/kosong.
5. Verifikasi pembaruan data/cache setelah setiap jalur perubahan; uji perubahan periode menjadi Draf/Arsip setelah cache terisi, serta pergantian periode utama yang tidak boleh mengurangi statistik.
6. Jalankan pemeriksaan format, analisis tipe, tes relevan, build frontend, serta pengujian browser desktop/ponsel dan keyboard. Catat hasil aktual dalam dokumen implementasi terpisah.

Kriteria selesai:

- Ketiga grafik tampil pada beranda dan mengambil agregat dari data peserta SK yang benar-benar tersimpan.
- Wilayah tanpa peserta tidak muncul; seluruh wilayah positif dapat ditemukan melalui gulir, pencarian dan tabel. Filter tidak menawarkan provinsi kosong pada lingkup periode terpilih.
- Label, angka, persentase dan urutan grafik/tabel sesuai query; jumlah dapat direkonsiliasi dengan data wilayah yang belum tersedia.
- Peserta tanpa akun tetap terhitung; peserta mundur tidak terhitung; penahanan administratif tidak menghilangkan peserta aktif.
- Satu orang dalam dua periode terhitung sebagai dua kepesertaan dan penjelasan lintas periode terlihat.
- Periode Draf/Arsip tidak muncul melalui angka total, nama, endpoint langsung, cache atau ekspor. Semua periode Dipublikasikan tetap dihitung meskipun tidak ada periode utama beranda yang dipilih.
- Pilihan periode tidak mengubah cakupan grafik perbandingan periode secara diam-diam; pencarian kabupaten/kota tidak mengubah total ringkasan.
- Pergantian filter cepat tidak menghasilkan grafik dari respons lama. Kegagalan JavaScript/jaringan tetap menyediakan informasi yang jelas dan tabel awal yang dapat dibaca.
- Tidak ada label terpotong/tumpang tindih pada layar 360 px, tablet dan desktop; akses keyboard dan pengurangan animasi berfungsi.
- Aset Highcharts tidak membebani halaman yang tidak menggunakannya; tidak ada request ke layanan ekspor eksternal tanpa kebutuhan yang ditetapkan.

## 10. Keputusan yang perlu dicatat saat implementasi

Keputusan pengguna setelah pemeriksaan production adalah **kepesertaan SK aktif pada seluruh periode berstatus published**, dengan semua periode Dipublikasikan sebagai pilihan awal dan asal wilayah dari data SK. `is_public` tetap menjadi pemilih satu periode utama pada kartu informasi beranda, tanpa membatasi statistik. Statistik ini tidak menyatakan peserta telah lolos pemeriksaan atau menyelesaikan pendaftaran.

Sumber daring di atas ditelaah pada tanggal penyusunan. Catatan implementasi menjelaskan pemakaian revisi cache berdasarkan isi agregat sebagai penyesuaian dari usulan hook invalidasi, serta hasil pengujian lokal. Cakupan lisensi production dan verifikasi visual sebelum rilis tetap perlu diselesaikan; lihat [catatan implementasi](../implementasi/statistik-peserta-sk-beranda.md).
