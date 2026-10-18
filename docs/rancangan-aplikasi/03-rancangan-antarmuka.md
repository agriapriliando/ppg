# 03 — Rancangan halaman dan pengalaman pengguna

## 1. Prinsip visual dan navigasi

Pertahankan identitas prototipe: ungu `#4A154B`, ungu gelap `#320D5C`/`#1E0836`, emas `#FFB300`, permukaan putih, latar lembut, kartu beradius, dan ornamen Dayak secukupnya. Motif dekoratif tidak boleh menurunkan keterbacaan. Gunakan font Plus Jakarta Sans jika aset/lisensinya disiapkan, dengan fallback sistem; jangan menggantungkan fungsi pada font eksternal.

Implementasi produksi mengikuti Blade + Livewire 4 dan pipeline Tailwind proyek. Terjemahkan tampilan Bootstrap prototipe, tanpa membawa global CSS Bootstrap dan Alpine terpisah ke seluruh aplikasi. Hindari dua sistem utilitas yang saling menimpa. Tahap ini merancang tampilan; kelas CSS final ditentukan saat pembangunan frontend.

Portal publik memakai navbar ringkas. Ruang kerja memakai sidebar, topbar, breadcrumb, periode aktif, nama pengguna, dan logout POST. Pada ponsel sidebar menjadi drawer dengan overlay, Escape, fokus awal, dan pengembalian fokus saat ditutup. Administrator boleh memilih “Antrean seluruh operator” atau filter operator, dengan identitas administrator tetap terlihat.

## 2. Inventaris layar publik dan akses

| ID / sumber prototipe | Halaman produksi | Isi dan aksi utama | Keadaan yang wajib dirancang |
| --- | --- | --- | --- |
| UI-P01 `index.php` | `/` | Hero PMB, periode, cek SK terbatas, pilihan Google/WhatsApp, alur, jadwal, FAQ, kontak resmi | Belum ada periode; sebelum buka; terbuka; tutup; kanal bantuan belum disahkan |
| UI-P02 `index.php#cek-sk` | Bagian cek kepesertaan | Nama sesuai SK dan pengenal jika ada; “Lanjutkan pencocokan” | Loading, input salah, respons generik, rate limit; tidak ada daftar hasil publik |
| UI-P03 `login.php` | `/masuk` | Google, ID login/sandi, tautan tanpa Gmail dan pemulihan | OAuth dibatalkan/gagal, identitas bentrok, akun nonaktif, login salah, kedaluwarsa |
| UI-P04 `klaim.php` dan modal footer | `/klaim-whatsapp` dan modal yang memakai handler sama | Periode, nama SK, nomor WA, pengenal opsional, consent klaim | Validasi inline; sukses dengan referensi acak; retry aman; periode tutup |
| UI-P05 `login.php?mode=aktivasi` | `/aktivasi-sandi` | Sandi baru, konfirmasi, persyaratan, sisa masa sesi | Sesi terbatas habis, konfirmasi salah, berhasil lalu dashboard |
| UI-P06 `login.php?mode=pemulihan` | `/pemulihan` | SOP bantuan dan kontak resmi; permohonan pemulihan tanpa membuka keberadaan akun | Referensi diterima generik; helpdesk; nomor berubah |
| UI-P07 `panduan.php` | `/panduan` | Alur pendaftar, operator, admin; panduan resmi yang disetujui | Tidak menyediakan tautan pintas masuk peran demo; aset belum tersedia disembunyikan |

Teks cek SK yang diusulkan: “Pencocokan kepesertaan dilakukan oleh operator. Lanjutkan masuk atau ajukan permohonan agar identitas dapat diperiksa.” Respons tidak menampilkan nama kandidat lain, status klaim, penugasan, atau nomor peserta hasil temuan. Pemohon hanya melihat kembali inputnya sendiri yang aman. Kartu “kuota/antrean” publik menampilkan agregat yang disetujui, tanpa menafsirkan kuota sebagai seleksi bebas.

## 3. Layar pendaftar

| ID / menu demo | Isi | Aksi dan batas |
| --- | --- | --- |
| UI-D01 `dashboard` | Status kepesertaan, nomor PMB bila sudah ada, progres field wajib, WA, langkah berikut, operator setelah klaim, kirim pertama | CTA mengikuti keadaan: ajukan klaim / lanjut draf / perbaiki / lihat status / cetak |
| UI-D02 `klaim` | Form nama SK/pengenal yang dapat diisi, status klaim sendiri dan tanggapan aman | Ajukan, tambah informasi, batalkan pending; klaim disetujui tidak dapat dipindah sendiri |
| UI-D03 `formulir` | Lima tahap, waktu simpan server, daftar field wajib, tanda perbaikan | Simpan draf, kembali/lanjut, tinjau, kirim; seluruh input terkunci pada status pemeriksaan |
| UI-D04 `status` | Status utama, timeline peristiwa yang boleh dilihat, catatan perbaikan, deadline revisi, WA | Buka field yang perlu koreksi; catatan internal/audit global tidak ikut terkirim |
| UI-D05 `bukti` | Nomor PMB, nama, periode, rujukan SK, PT/prodi asal, tanggal pertama, status, waktu cetak/revisi | Cetak setelah submit pertama; teks jelas membedakan bukti kirim dan keputusan lengkap |
| UI-D06 `akun` | Nama tampilan, identitas login/provider, sesi, keamanan | Nama tampilan tidak mengganti nama SK/biodata; ganti sandi memakai autentikasi ulang |
| UI-D07 `bantuan` | FAQ kontekstual, operator/kanal resmi dan jam layanan | Nomor kontak hanya yang disetujui untuk publik/pendaftar; tidak memakai contoh |

Sebelum persetujuan klaim, dashboard tidak berpura-pura mempunyai nomor SK, nomor PMB, atau operator final. Progres menghitung field final yang benar-benar wajib menurut konfigurasi aktif, termasuk ketergantungan KPS; 100% terisi tidak menggantikan validasi server.

## 4. Formulir lima tahap

Detail tipe, panjang, dan pemetaan nama field ada pada [kamus data](04-basis-data.md). Aturan berikut berlaku saat kirim; draf dapat belum lengkap.

| Tahap | Kelompok isian | Perilaku |
| --- | --- | --- |
| 1 — Identitas dan kontak | Nama SK baca saja setelah klaim; nama biodata; jenis kelamin; tempat/tanggal lahir; NIK; NISN jika tersedia; agama; kewarganegaraan; nama ibu; WA; email/telepon lain | Tanggal kalender, bukan konversi timestamp; NIK/NISN input teks numeric; perbedaan nama diberi tanda untuk operator |
| 2 — Alamat | Jalan, dusun, RT/RW, desa/kelurahan, provinsi/kota/kecamatan, kode pos, jenis tinggal, transportasi | Ganti provinsi mengosongkan kota dan kecamatan; ganti kota mengosongkan kecamatan; validasi relasi tetap di server |
| 3 — Tambahan | Penerima KPS, nomor KPS jika ya, NPWP opsional, kebutuhan khusus | Nomor KPS wajib hanya jika ya; jika tidak, nilai lama dikosongkan saat simpan; tidak mengisi nilai palsu |
| 4 — Pendidikan asal | PT asal dan prodi asal melalui pencarian | Prodi nonaktif sampai PT dipilih; ganti PT mengosongkan prodi; ID dan nama snapshot diambil server |
| 5 — Tinjau dan kirim | Ringkasan empat kelompok, ubah tiap kelompok, pemberitahuan data, versi pernyataan | Consent eksplisit; pesan penguncian sesudah kirim; konfirmasi akhir; field invalid membawa fokus ke tahap asal |

Pilihan agama/kewarganegaraan/jenis tinggal/transportasi/kebutuhan khusus pada demo bukan daftar final. Gunakan referensi yang disahkan; “Lainnya” harus mempunyai mekanisme rujukan yang jelas, bukan string bebas yang dianggap ID Neo. Email kontak tidak wajib untuk jalur WhatsApp.

## 5. Layar operator

| ID / menu | Rancangan isi | Aksi |
| --- | --- | --- |
| UI-O01 `dashboard` | Beban SK, formulir terkirim saat ini, klaim menunggu, WA perlu tindak lanjut, accepted; prioritas umur antrean | Buka daftar sesuai filter; semua agregat memakai scope tugas sekarang |
| UI-O02 `antrean` | Tab seluruh status; pencarian nama/pengenal/nomor PMB; status WA; urut kirim terlama lalu ID; paginasi | Buka detail; tidak menampilkan peserta tanpa aplikasi sebagai formulir terkirim |
| UI-O03 `detail` | SK, biodata lengkap sesuai izin, alamat, pendidikan, riwayat, checklist, konflik, versi pemeriksaan | Simpan pemeriksaan; verifikasi; minta perbaikan; diterima lengkap; tampilkan syarat yang belum terpenuhi |
| UI-O04 `klaim` | Tab Google, WhatsApp, perlu informasi; kandidat sah dalam tugas; badge nomor aktif/kredensial | Catat bukti WA; setujui/kembalikan klaim; terbitkan akses; catat penyerahan |
| UI-O05 `peserta` | Seluruh SK tugas termasuk belum mempunyai akun/aplikasi | Status “Belum mendaftar”; nama resmi; klaim pending; tidak ada biodata rekaan |
| UI-O06 `riwayat` | Aktivitas sendiri: waktu, aksi, entitas, hasil | Cari/rentang waktu; rincian tunduk hak akses terkini |
| UI-O07 `akun` | Profil/keamanan bersama | Tidak dapat mengubah peran, scope, atau status aktif sendiri |

Detail data sensitif tidak ditumpahkan ke atribut HTML tersembunyi atau state browser untuk semua baris. Tampilkan NIK masking pada daftar; tombol lihat rinci hanya mengambil data objek yang sudah diotorisasi. Modal perbaikan meminta setidaknya satu kelompok/field dan catatan yang menjelaskan tindakan, bukan “data salah”.

## 6. Layar administrator

| ID / menu | Rancangan isi dan fungsi |
| --- | --- |
| UI-A01 `dashboard` | Agregat seluruh periode yang dipilih, distribusi beban, backlog, peserta belum ditugaskan, konflik, kesiapan layanan |
| UI-A02 `peserta` | Semua SK, filter operator/status/jalur/WA/pengecualian, rincian sumber, riwayat perubahan resmi |
| UI-A03 `impor` | Unduh template → unggah → validasi → pratinjau baru/identik/berubah/konflik/error → konfirmasi → hasil dan riwayat |
| UI-A04 `penugasan` | Pilih operator periode, beban awal/akhir, daftar perpindahan, peserta belum ditugaskan; pembagian dan transfer beralasan |
| UI-A05 `antrean`, `klaim`, `detail` | Kemampuan operator lintas tugas dengan aktor admin; triase klaim ambigu/tidak cocok dan kasus koreksi administratif |
| UI-A06 `akun-pengguna` | Buat/invitasi pengelola, peran, status aktif, periode tugas, reset/pencabutan akses; lindungi admin aktif terakhir |
| UI-A07 `periode` | Banyak periode/gelombang, kode/nama, jadwal, publikasi, tutup/buka, deadline revisi, versi konfigurasi dan arsip |
| UI-A08 `referensi` | Wilayah/pilihan biodata/PT/prodi, sumber, versi, jumlah, waktu refresh; aktivasi dataset lengkap; error run parsial |
| UI-A09 `neo` | R2: feature flag, pengesahan pemetaan, NIM, pratinjau kesiapan/payload whitelist, job dan tahap, riwayat sanitasi, rekonsiliasi |
| UI-A10 `audit` | Filter aktor/aksi/objek/tanggal, perubahan aman, ekspor berizin; kolom sensitif tidak otomatis diekspor |
| UI-A11 `pengaturan` | Identitas aplikasi, kontak/jam layanan, pengumuman, FAQ, aset panduan, naskah consent, versi kebijakan; tanpa secret di browser |
| UI-A12 akun pribadi | Akses melalui menu pengguna, walau demo admin tidak memiliki item sidebar akun tersendiri |

Halaman Neo R1 boleh menampilkan keterangan fitur belum aktif bagi admin; tombol pengiriman tidak tersedia dan endpoint tulis juga menolak. Tombol reset demo, isi data contoh, pilihan peran, dan angka simulasi tidak ada pada produksi.

## 7. Komponen yang dipakai bersama

| Komponen | Kontrak perilaku |
| --- | --- |
| Badge status | Label Indonesia + warna + ikon opsional; status WhatsApp dan Neo memakai kolom sendiri |
| Tabel | Pagination server 25/50/100; reset halaman saat filter berubah; query string menyimpan filter aman; empty state dan total hasil |
| Konfirmasi | Nama/nomor entitas, akibat aksi, alasan bila diperlukan, batal, indikator proses; hasil hanya sesudah server berhasil |
| Pesan validasi | Inline dekat field, ringkasan atas form, label terbaca pembaca layar; data input tetap setelah kegagalan |
| Notifikasi | Sukses/gagal berbeda; jangan menampilkan toast hijau pada kegagalan; pesan penting tidak hilang sebelum dibaca |
| Timeline | Urut waktu stabil, aktor/aksi yang diizinkan; catatan internal dipisahkan dari catatan kepada pendaftar |
| Pencarian referensi | Debounce, minimum input, pagination hasil; no-result dan server unavailable berbeda |
| Bukti cetak | Layout A4, header institusi, nomor/revisi/waktu cetak, tanpa sidebar/topbar; diuji nama panjang dan lebih dari satu halaman |

## 8. Keadaan layar dan aksesibilitas

Wajib menyediakan loading, kosong, validasi gagal, jaringan gagal, sesi habis, akses tidak berhak, data berubah oleh pengguna lain, periode tutup, dan layanan eksternal tidak tersedia. Konflik versi memberi “Muat data terbaru” serta ringkasan field belum tersimpan jika aman; tidak otomatis menimpa perubahan.

Label input eksplisit; gunakan `autocomplete` yang sesuai; tombol ikon memiliki nama aksesibel; fokus terlihat; modal menjebak fokus dan mengembalikannya; navigasi/form bisa memakai keyboard. Uji pada 360, 768, dan 1280 px sebagai titik pemeriksaan rancangan. Tabel boleh bergulir mendatar pada ponsel, dengan kolom identitas/aksi tetap dapat dipahami. Animasi dekoratif mengikuti preferensi pengurangan gerak. Aksi ganda dibatasi tombol loading dan tetap dilindungi idempotensi server.

Teks final harus menghapus kata “demo/contoh” setelah data dan fungsi benar-benar siap; selama staging, tampilkan banner lingkungan uji dan gunakan data sintetis.
