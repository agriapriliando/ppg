# 06 — Impor, referensi, dan integrasi

## 1. Kontrak template Excel SK

Template unduhan produksi berupa `.xlsx` versi `1`, sheet `Peserta_SK` dan `Panduan`, header pada baris 1. Tidak ada sel gabungan, macro, password workbook, formula, atau tautan eksternal. Setiap file boleh memuat beberapa SK, tetapi semuanya harus berada pada **satu periode tujuan** yang dipilih admin. Tanggal SK milik setiap SK; jangan hanya menyimpan satu tanggal pada header impor bila ada beberapa SK.

| Header | Wajib | Format dan aturan |
| --- | --- | --- |
| `nomor_sk` | Ya | Teks, maksimum 150; trim spasi; nomor dokumen tidak diganti menjadi angka |
| `tanggal_sk` | Ya | Tanggal Excel sah atau teks ISO `YYYY-MM-DD`; parser menyamakan hasil menjadi date |
| `periode` | Ya | Kode periode yang sama dengan periode tujuan; bukan nama tampilan bebas |
| `nomor_urut_sk` | Ya | Bilangan bulat positif; unik pada kombinasi periode + nomor SK |
| `nama_sesuai_sk` | Ya | Teks resmi, maksimum 255; nama asli disimpan, normalisasi hanya untuk pencarian |
| `id_peserta_sk` | Tidak | Teks resmi maksimum 100, nol awal utuh; unik per periode bila terisi |
| `operator_email` | Tidak | Operator yang ada, aktif, dan terdaftar pada periode tujuan |
| `catatan_internal` | Tidak | Teks aman maksimum usulan 1.000; tidak dikirim ke pendaftar/Neo |

Contoh sintetis, bukan data resmi:

```text
nomor_sk: SK-UJI-001/2026
tanggal_sk: 2026-10-01
periode: PMB-UJI-2026-G1
nomor_urut_sk: 1
nama_sesuai_sk: Peserta Uji Satu
id_peserta_sk: 000001
operator_email: operator.uji@example.test
```

Normalisasi nama: trim dan rapikan spasi, Unicode case normalization untuk pencarian. Jangan menghapus bagian nama atau memakai kemiripan sebagai keputusan identitas. `sk_number` mempunyai bentuk kanonik konsisten untuk kunci, sementara nilai resmi tetap tersedia untuk tampilan. Dua baris pada nomor SK yang sama dengan tanggal SK berbeda menjadi error, bukan diterima diam-diam.

## 2. Pipeline impor nyata

1. **Unggah:** admin memilih periode dan file. Batas awal yang diusulkan 5 MiB/5.000 baris; konfigurabel. Validasi file, MIME/ekstensi, struktur workbook, jumlah sheet/row/cell, dan batas ekspansi arsip. Simpan nama acak pada disk privat dan hash SHA-256.
2. **Parse/staging:** pilih parser `.xlsx` yang kompatibel PHP/Laravel proyek melalui proof of concept. Parser membaca nilai tanpa menghitung formula atau mengambil tautan eksternal. Belum ada package parser yang dipasang dalam pekerjaan dokumentasi ini.
3. **Validasi seluruh baris:** cek header, kolom tidak dikenal, tipe/tanggal, baris wajib kosong, key duplikat, identifier bentrok, periode, operator, konsistensi nomor/tanggal SK. Baris kosong seluruhnya dapat diabaikan dengan hitungan yang jelas.
4. **Bandingkan:** kunci peserta `(period_id, sk_number, sk_order)`; hash field resmi yang ternormalisasi membedakan baru, identik, perubahan aman, konflik, atau error. Nama bukan kunci.
5. **Pratinjau:** total baris terbaca, diabaikan, baru, identik, berubah, konflik, error; nomor sheet/baris, field, pesan, nilai aman. Kesalahan dapat diunduh sebagai laporan privat tanpa mengekspor data lain.
6. **Pengaman pratinjau:** simpan preview hash yang mencakup file, mapping/template, periode, dataset_version, dan hasil validasi. Usulan masa berlaku 24 jam. Jika SK/operator/klaim/config relevan berubah, preview menjadi stale dan harus dihitung ulang.
7. **Konfirmasi:** lock periode, verifikasi ulang hak akses/versi dan konflik penting, tulis seluruh baris yang valid dalam satu transaksi. Satu error atau konflik yang belum diputuskan menggagalkan seluruh commit roster.
8. **Hasil:** tampilkan hitungan yang benar-benar committed, tautan roster/penugasan, aktor/waktu, dan run ID. Gagal menulis tidak boleh mengubah run menjadi committed. Simpan audit ringkasan.

Job validasi boleh menyimpan sebagian **staging**, tetapi tidak sebagian **roster produksi**. Untuk skala awal, commit roster sinkron/transaksi memadai setelah validasi berat selesai; jika kelak perlu chunk produksi, rancangan atomisitas harus diubah secara sadar, bukan diam-diam mengurangi jaminan.

### Kebijakan impor ulang/perubahan

| Kondisi | Hasil |
| --- | --- |
| Semua baris identik | Commit no-op berhasil; tidak membuat SK/aplikasi baru, tidak reset klaim/status/WA/operator |
| SK baru ditambahkan | Insert sesuai kunci, penugasan eksplisit bila sah; sisanya belum ditugaskan |
| Nama/pengenal/tanggal peserta belum diklaim berubah | Tampilkan diff; admin mengonfirmasi perubahan yang aman secara eksplisit |
| Field resmi peserta sudah diklaim berubah | Konflik; selesaikan lewat kasus administratif dan validasi ulang; tidak ditimpa oleh import biasa |
| `operator_email` berbeda pada peserta yang sudah ditugaskan | Bukan transfer otomatis; tampilkan konflik/anjuran menu penugasan |
| Peserta tidak ada lagi dalam file terbaru | Tidak dihapus/ditarik otomatis; penarikan harus aksi admin beralasan |
| Pengenal sama pada business key berbeda | Konflik identitas; tidak dianggap nama baru |
| Nama sama pada business key berbeda | Boleh sebagai roster resmi, diberi indikator kemungkinan nama kembar |

## 3. Algoritme pembagian operator

Sumber tanggung jawab adalah SK, termasuk peserta yang belum mempunyai akun. Bagi hanya entri yang belum ditugaskan, dengan operator periode yang dipilih dan aktif saat commit.

```text
operators = urutkan operator terpilih menurut sort_order, lalu user_id
participants = SK aktif belum ditugaskan, urut nomor_sk, nomor_urut_sk, id
untuk setiap peserta i:
    operator = operators[i mod jumlah_operators]
    simpan assignment + event
```

Penugasan eksplisit Excel sudah diselesaikan sebelum pembagian otomatis dan dikecualikan dari daftar belum ditugaskan. Jika tidak ada operator, tolak. Jika tidak ada peserta baru, hasil no-op. Pratinjau menampilkan beban sebelumnya, jumlah tambahan, dan beban akhir. Simpan batch/hash/versi, lalu transaksi dan lock periode mencegah dua admin menjalankan pembagian bersamaan.

| Skenario awal tanpa tugas lama/eksplisit | Hasil |
| --- | --- |
| 400 peserta / 4 operator | 100, 100, 100, 100 |
| 403 / 4 | 101, 101, 101, 100 menurut urutan operator stabil |
| 2 / 4 | 1, 1, 0, 0 |
| Impor ulang identik lalu bagi lagi | Tidak ada perpindahan atau tambahan |

Jaminan selisih maksimum satu berlaku pada **tambahan dalam batch round-robin**, dan pada total hanya jika beban awal setara tanpa penugasan eksplisit yang timpang. Jangan mengklaim otomatis menyeimbangkan seluruh tugas yang sudah ada. Rebalancing merupakan aksi lain dengan daftar perpindahan beralasan dan konfirmasi, terutama untuk peserta yang sudah diperiksa.

Transfer memeriksa operator tujuan aktif dan terdaftar, target bukan operator yang sama, serta versi SK belum berubah. Sesudah commit, kartu/tabel operator lama harus kehilangan akses objek tersebut. Histori pemeriksaan tidak dipindahkan identitasnya.

## 4. Google dan identitas lokal

Tambahkan Socialite melalui Composer pada tahap implementasi, cocokkan hasil lock dengan Laravel 13 dan PHP proyek. Simpan client ID, secret, dan redirect URI pada secret server/config; gunakan URI staging dan produksi yang terdaftar, state OAuth, serta scope minimum identitas dasar. Jangan mematikan pemeriksaan state hanya agar callback berhasil. Dasar alur provider: [Socialite 13](https://laravel.com/framework/docs/13.x/socialite).

Kontrak resolver aplikasi: `(provider, provider_user_id)` adalah kunci utama identitas sosial. Email verified dibutuhkan sesuai kebijakan login; email bentrok dengan user lain menghasilkan proses pengaitan terverifikasi. Kegagalan provider tidak membuat akun setengah terhubung. Pengguna staff yang diundang membuktikan identitas yang cocok dengan undangan sekali pakai dan tidak dapat memilih role dari request.

Google tidak menjadi tempat menyimpan data PMB. Token login tidak dipersisten bila tidak dipakai untuk fungsi lain. Uji callback valid, state salah, provider error, data tanpa email, email belum verified, user nonaktif, provider sudah terhubung, dan email berubah.

## 5. WhatsApp manual

Tidak ada WhatsApp API pada R1. Aplikasi membantu menyiapkan teks aman, mencatat bukti balasan, menghasilkan kredensial, dan mencatat penyerahan. Membuka `wa.me` bila dipakai hanya tindakan operator pada browser; klik tidak dianggap bukti pesan telah terkirim atau diterima.

Template awal yang perlu disahkan:

> Salam, kami operator resmi PMB PPG IAKN Palangka Raya. Kami menindaklanjuti permohonan [referensi]. Mohon balas kode [kode] pada percakapan ini untuk mengonfirmasi nomor aktif. Jangan kirim NIK lengkap atau kata sandi.

Template penyerahan memuat URL resmi, login_id, sandi sementara, masa berlaku, dan instruksi wajib ganti sandi. Secret tidak disisipkan ke log, link publik, parameter URL, notifikasi email, atau riwayat audit. SOP salah kirim: cabut segera, hentikan sesi yang terkait, konfirmasi tujuan, verifikasi ulang, terbitkan akses baru, catat insiden.

## 6. Referensi tanpa ketergantungan wajib pada Neo

R1 harus dapat memakai dataset lokal yang disahkan pengelola. Neo otomatis bukan prasyarat MVP, tetapi **referensi yang sah** tetap prasyarat field yang membutuhkannya. Sumber PT/prodi/wilayah yang disahkan dapat dimuat melalui perintah/import admin tervalidasi; jangan merilis pilihan dua kampus contoh sebagai daftar produksi.

Setiap pemutakhiran membuat dataset staging. Validasi schema, ID unik, relasi PT-prodi/wilayah, semua halaman/pagination, dan jumlah masuk akal. Dataset baru menjadi aktif hanya setelah semua halaman selesai. Run parsial/gagal mempertahankan dataset lama. Pilihan yang pernah disubmit menyimpan ID dan label snapshot agar pergantian nama tidak mengubah bukti lama.

Jika data tidak tersedia sejak awal, blok submit yang memerlukan referensi dan tampilkan hambatan yang spesifik; draf tetap dapat disimpan. Jika dataset lama masih disahkan, kegagalan refresh tidak otomatis menghentikan PMB. Masa berlaku referensi dan persetujuan memakai versi lama dicatat pada konfigurasi periode.

## 7. Neo Feeder — kontrak R2 yang harus dibuktikan

Nama aksi berikut berasal dari rancangan lama: `GetAllPT`, `GetAllProdi`, `InsertBiodataMahasiswa`, `InsertRiwayatPendidikanMahasiswa`, serta pembacaan referensi pendaftaran/jalur/semester. **Nama, parameter, autentikasi, pagination, limit, dan response schema belum diverifikasi ke instance tujuan.** Pengembang membuat catatan kontrak sanitasi dari instance uji yang disediakan pengelola sebelum mengaktifkan kirim.

Jangan menyambungkan database PMB ke tabel produksi Feederdash atau menyalin kode lama sebagai kontrak aktif. Client Neo di server mengatur koneksi, timeout, kredensial, whitelist field, parsing hasil, dan pembacaan balik. Secret tidak disimpan pada pengaturan frontend.

### 7.1 Data dan pemetaan

| Kelompok | Sumber PMB | Syarat kirim |
| --- | --- | --- |
| Identitas biodata | Nama, gender, tempat/tanggal lahir, agama, NIK/NISN, kewarganegaraan, ibu, alamat, kontak, KPS/NPWP/kebutuhan khusus | Hanya field dalam kontrak aktif dan data valid; format tanggal/kode diuji |
| `id_mahasiswa` | Hasil cocok biodata yang diverifikasi atau insert sukses | Tidak menebak hanya dari nama; hasil ambigu memerlukan peninjauan |
| `nim` | Penetapan resmi admin/pejabat berwenang | Bukan nomor PMB; unik sesuai kebijakan dan instance tujuan |
| `tanggal_daftar` | `applications.tanggal_daftar` dari kirim pertama WIB | Serializer mengikuti format yang diuji; tidak memakai waktu job/ulang kirim |
| PT/prodi asal | ID dan nama snapshot pendidikan asal | Pasangan valid; nama field asal sesuai kontrak instance |
| Jenis/jalur/periode/PT/prodi tujuan | Versi pemetaan yang disahkan | ID nyata diverifikasi pada instance tujuan |
| Bidang minat/SKS diakui/pembiayaan/biaya masuk | Konfigurasi periode yang disahkan | Nullable/nilai nol hanya jika memang sah menurut kontrak, bukan tebakan |

Nilai historis seperti jenis daftar `18`, jalur `11`, periode `20261`, dan UUID PT/prodi pada rancangan sebelumnya hanya bahan pemeriksaan. Jangan menjadikannya default aktif. Payload tidak membawa role, password, kode WA, catatan operator, klaim, assignment, atau audit internal.

### 7.2 Kesiapan dan snapshot

Pengiriman hanya jika: fitur aktif; admin berhak dan masih aktif; aplikasi diterima lengkap tanpa hold; revisi terakhir sudah diperiksa; WA masih sah; NIM tersedia; pemetaan/kontrak/reference IDs telah diverifikasi; belum ada job aktif/sukses yang bertentangan.

Snapshot PMB saat kirim pertama tetap utuh walau konfigurasi Neo baru disahkan belakangan. `neo_syncs.mapping_snapshot` mencatat pemetaan tambahan, revisi aplikasi, payload hash, aktor pengesah, dan waktu. Pratinjau admin memperlihatkan asal field dan perbedaan dengan snapshot lama. Konfirmasi memakai hash; perubahan data setelah pratinjau membatalkan kesiapan.

### 7.3 Protokol sinkron dan pemulihan

Status Neo diusulkan `belum_dikirim`, `antri`, `diproses`, `gagal`, `perlu_verifikasi`, `tersinkron`. Tahap biodata dan riwayat disimpan terpisah. `siap` adalah hasil perhitungan kesiapan, bukan keberhasilan pengiriman.

1. Admin memulai run; transaksi menyimpan pengaju, snapshot/hash, status antri. Job dikirim sesudah commit.
2. Worker mengambil lock per aplikasi dengan lease yang lebih panjang daripada durasi maksimum operasi, atau mekanisme renewal yang teruji. Cek status/versi kembali dan cegah dua worker insert bersamaan.
3. Cari riwayat target dan biodata lebih dulu memakai kunci resmi yang kontraknya sudah dibuktikan. Hasil lebih dari satu/ambigu menjadi perlu_verifikasi.
4. Bila biodata sudah cocok, pakai ID yang diverifikasi; jika belum ada, insert biodata sekali. Simpan ID segera sesudah sukses yang pasti.
5. Cek riwayat pendidikan target sebelum insert; bila belum ada dan identitas pasti, insert riwayat. Jika biodata sukses tetapi riwayat gagal, simpan tahap sukses dan jangan ulang insert biodata.
6. Baca balik riwayat dan periksa identitas, NIM, prodi/periode tujuan serta field penting. Baru tandai tersinkron.
7. Timeout/koneksi putus setelah request insert bukan bukti gagal insert. Tandai perlu_verifikasi; lakukan baca balik/rekonsiliasi, tanpa blind retry insert.
8. Retry hanya untuk tahap yang dipastikan belum berhasil. Jika instance tidak menyediakan cara lookup/readback yang memadai, kirim produksi tetap diblokir sampai SOP rekonsiliasi manual disahkan.

Request baca dapat memakai retry terbatas dengan backoff; insert tidak memakai retry umum HTTP tanpa rekonsiliasi. Error validasi diperbaiki admin/operator sesuai kewenangan; perubahan data setelah tersinkron membuka kasus koreksi, tidak membuat mahasiswa/riwayat kedua. Token kedaluwarsa, salah kredensial, gagal koneksi, schema respons berubah, dan hasil duplikat harus punya pesan berbeda pada log aman/admin, sementara status PMB tetap.

## 8. Bukti, ekspor, dan aset publik

Bukti R1 menggunakan halaman Blade khusus cetak dan fitur browser “Simpan PDF”; tidak memerlukan mesin PDF server atau tanda tangan digital. Cetakan memuat nomor PMB, periode, identitas secukupnya, SK, tanggal pertama, status, revisi/waktu cetak, dan keterangan arti bukti. NIK/ibu tidak dimasukkan tanpa kebutuhan sah. Verifikasi QR publik belum menjadi scope.

Ekspor CSV R1 mengambil seluruh hasil filter yang diizinkan, bukan hanya halaman tabel. Kolom baku: nomor PMB/pengenal SK, nama, periode, status, operator, dan status WA; nomor identitas lengkap hanya pada paket kolom khusus yang disahkan. Netralisasi nilai yang dapat dieksekusi sebagai formula spreadsheet (`=`, `+`, `-`, `@`, tab/CR awal), lakukan quoting CSV yang benar, dan pertahankan nol awal saat format tujuan memerlukan.

File ekspor privat, masa berlaku awal diusulkan 24 jam. Hak akses diperiksa saat membuat dan saat mengunduh; perubahan tugas/nonaktif menyebabkan unduh ditolak atau file dibuat ulang dengan scope terbaru. Semua run mencatat aktor, filter, kolom, jumlah, dan download. Aset panduan publik hanya berkas yang disahkan; roster lengkap dan hasil pemeriksaan bukan aset publik.
