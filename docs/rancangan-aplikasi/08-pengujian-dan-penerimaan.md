# 08 — Pengujian dan penerimaan

## 1. Strategi dan lingkungan

Gunakan Pest yang sudah ada. Feature test menguji perilaku melalui HTTP/Livewire dan database nyata test; unit test hanya logika murni seperti distribusi round-robin atau normalisasi. Jangan menggandakan seluruh matriks input pada unit dan feature: matriks dimiliki satu lapisan, feature memastikan aturan dipakai dan pesan pengguna benar.

Gunakan database test terisolasi, factory sintetis, waktu yang dibekukan, fake storage/queue/HTTP sesuai kebutuhan. Cegah request HTTP yang tidak di-fake agar CI tidak mengirim data ke Google/Neo. Fake batas eksternal, bukan Eloquent/query builder. Pengujian otorisasi dilakukan pada matriks policy dan satu/lebih request yang membuktikan pengaman benar-benar dipanggil, termasuk aksi Livewire langsung.

SQLite dapat mempercepat sebagian tes, tetapi bukan bukti row-level locking atau perilaku nullable/unique engine produksi. Uji konkurensi menggunakan dua koneksi/proses dan database target. UAT browser dilakukan manual dengan akun berbeda; tidak perlu memasang dependency browser test baru untuk memenuhi rancangan ini.

Folder sasaran mengikuti 05, dengan nama perilaku yang jelas, misalnya `Claims/ApproveSkClaimTest.php`, `Admissions/SubmitApplicationTest.php`, `Assignments/TransferAssignmentTest.php`. Gunakan trait pembersihan database sesuai konvensi yang dipilih saat implementasi; skill pengujian menyarankan `LazilyRefreshDatabase` untuk tes yang membutuhkannya.

## 2. Data uji minimum

| Dataset | Isi |
| --- | --- |
| User | Guest, pendaftar Google, pendaftar WA pending/active, operator 1–4, admin, pengelola nonaktif, user dengan sesi aktivasi |
| Periode | Draft, published belum buka, terbuka, tutup waktu, tutup manual, revisi pascatutup diizinkan/tidak, archived |
| SK | 400 entri; variasi 403 dan 2; nama kembar; beda ejaan; pengenal nol awal; beberapa SK; peserta withdrawn/hold; tugas kosong |
| Klaim | Pending cocok tunggal, ambigu, tidak cocok, nomor bersama, sudah dimiliki, approved/needs_information/declined |
| Aplikasi | Lima status tersimpan, revisi berulang, checklist revisi lama, WA belum sah/berubah, nomor PMB dan NIM berbeda |
| File | `.xlsx` valid, salah header, row kosong/duplikat, tanggal serial/ISO, pengenal numerik rusak, formula, ukuran berlebih, file palsu/password |
| Referensi | Dataset aktif lengkap, staging parsial, PT-prodi salah pasangan, wilayah salah parent, item retired |
| Neo R2 | Biodata lama cocok/ambigu, history sudah ada, timeout setelah insert, ID tahap pertama sukses, error auth/schema/validasi |

Identitas memakai nama “Peserta Uji …” dan domain `example.test`. NIK uji tetap data sintetis dan tidak dipakai sebagai bukti identitas nyata.

## 3. Matriks pengujian R1

Setiap baris adalah skenario yang dapat dipecah menjadi dataset/test bernilai. Hasil yang dicatat harus mencakup perubahan database dan data yang tidak boleh berubah, bukan hanya status respons.

| ID | Skenario / tindakan | Hasil yang wajib dibuktikan |
| --- | --- | --- |
| T01 | Migrate fresh dan upgrade dari users dasar; buat dua aplikasi pada SK/user-periode yang sama | Migrasi konsisten; constraint/domain menolak aplikasi ganda; nullable email/password mendukung dua jalur |
| T02 | Login Google pertama, ulang, provider ID sama dengan email/nama berubah | Satu user dan satu social account; dashboard pribadi; perubahan nama Google tidak mengubah nama SK/biodata |
| T03 | State OAuth salah, cancel/error, email tidak ada/belum verified, email bentrok | Tidak ada akun terhubung separuh atau peran staff baru; pesan aman; akun lokal tidak digabung otomatis |
| T04 | Login WA sebelum delivery, credential expired/revoked/used, lalu valid dan ganti sandi | Yang tidak sah ditolak; sesi valid hanya aktivasi; dashboard tertutup sampai sandi baru; credential tidak dapat dipakai ulang |
| T05 | Undangan staff valid/expired/used; user mencoba role admin; nonaktifkan admin terakhir | Role hanya melalui provisioning sah; input publik tidak menaikkan peran; admin terakhir dilindungi |
| T06 | Google klaim tanpa pengenal pada nama kembar/tidak cocok | Tidak auto-approve/lock; masuk triase; data kandidat lain tidak dibuka ke pemohon |
| T07 | Dua approval dari akun/worker berbeda untuk SK sama, dan satu akun klaim dua SK dalam periode sama | Hanya satu aplikasi/pemilik; konflik kedua aman; unique dan transaksi konsisten |
| T08 | Request memakai workspace/claim/SK dari periode atau user lain | Ditolak; tidak mengubah owner, referensi, status, atau aplikasi pihak lain |
| T09 | Ulang klaim dengan request_key sama; dua akun memakai nomor WA sama; klaim needs_information | Request identik tidak membuat duplikasi; nomor bersama menjadi kasus; tambahan info tidak mengunci SK |
| T10 | Cari sebagian nama umum, pengenal orang lain, request berulang/public claim | Tidak ada roster/nama/operator/WA hasil kandidat di response/source; batas rate aktif; sukses publik generik |
| T11 | Import file valid 400, ulang identik, file sama dengan urutan baris berbeda | Jumlah roster tetap benar; kunci baris resmi menentukan identitas; klaim/status/operator lama utuh |
| T12 | Import row salah/duplikat/header/tanggal/operator/file palsu/formula/ukuran berlebih | Laporan sheet/baris jelas; tidak ada perubahan roster parsial; formula tidak dieksekusi |
| T13 | Edit SK/penugasan setelah preview; commit dua admin; file menghilangkan peserta lama | Stale/konflik dihentikan; tidak double-commit; peserta yang hilang dari file tidak otomatis dihapus |
| T14 | Bagi 400/4, 403/4, 2/4, tanpa operator, dan ulang saat semua sudah ditugaskan | Hasil 100×4; 101/101/101/100; 1/1/0/0; tanpa operator ditolak; ulang no-op |
| T15 | Explicit assignment timpang, transfer, target nonaktif, dua apply bersamaan | Explicit tidak direset; beban awal/akhir jelas; transfer beralasan; target sah; scope lama tercabut |
| T16 | Draf kosong/sebagian, NIK/NISN nol awal, KPS ya tanpa nomor, prodi/wilayah salah parent | Draf parsial tersimpan bila nilai terisi valid; final menolak field salah; nol awal utuh; parent diverifikasi server |
| T17 | Verifikasi format saja, kode salah/expired/lebih batas, nomor berubah setelah verified | Format tidak membuktikan aktif; bukti/nomor spesifik; perubahan mencabut verification dan accepted guard menolak |
| T18 | Kirim tanpa SK/consent/field final; lalu kirim valid dan klik ulang setelah timeout | Invalid tidak masuk antrean; valid satu submission awal; ulang tidak mengganti nomor/tanggal/snapshot |
| T19 | Operator mencoba lompat draf → accepted, diverifikasi tanpa checklist, accepted tanpa WA/hold selesai | Transisi ditolak server dengan hambatan jelas; status tidak berubah |
| T20 | Perbaikan tanpa note/field, perbaikan valid, edit dan resubmit beberapa kali | Note/field wajib; revisi dan consent dicatat; tanggal pertama tetap; checklist lama tidak cukup |
| T21 | Dua pemeriksa memakai versi sama untuk keputusan berbeda; dua tab mengubah draf | Satu hasil menang; penulis stale ditolak; tidak ada overwrite senyap atau audit keputusan palsu |
| T22 | Edit langsung saat terkirim/diverifikasi/accepted; lalu admin reopen beralasan | Edit pendaftar ditolak; admin exception mencatat kasus/transisi, invalidasi review terkait, histori/tanggal utuh |
| T23 | Submit 16:59 UTC dan 17:01 UTC pada tanggal yang sama | Tanggal daftar mengikuti hari WIB; timestamp tetap UTC; birth_date/sk_date tidak bergeser |
| T24 | Tepat opens_at/closes_at; tutup manual; revisi izin/tidak; melewati correction deadline; archived | Rentang `[buka,tutup)` konsisten; revisi hanya sesuai izin/deadline; arsip baca saja |
| T25 | Withdraw SK, hold identitas, pindah kepemilikan admin, pemulihan akses | Tidak menambah status ditolak; accepted/sync diblokir bila relevan; akses user lama dicabut; tidak membuat aplikasi kedua |
| T26 | Guest/role salah/IDOR/scope transfer/akun disabled pada HTTP dan Livewire langsung | Redirect login atau 403/404 sesuai kontrak; semua data/aksi tunduk scope terkini; hidden UI bukan satu-satunya pengaman |
| T27 | Secret sekali tampil, halaman reload, salah kirim lalu revoke/reissue, ganti sandi dan logout | No-store; plaintext tidak ada di DB/log/queue/Livewire; issuance lama mati; sesi dirotasi/dicabut sesuai SOP |
| T28 | Review/status/transfer/impor/ekspor dijalankan admin dalam tampilan operator | Audit actor tetap admin; waktu/request/entitas benar; NIK/token/password/kode tidak masuk log umum |
| T29 | Bukti sebelum submit, setelah submit/perbaikan/accepted/reopen, nama sangat panjang | Sebelum submit tidak tersedia; status/revisi/waktu akurat; cetak A4 terbaca, tanpa navigasi/PII berlebihan |
| T30 | Semua halaman dari inventaris 03, metrik/filter dan periode berbeda | Menu berfungsi sesuai peran; metrik konsisten denominator/scope; tidak ada fixed sample/kontak demo; empty/error benar |
| T31 | Ekspor seluruh filter, karakter formula CSV/nol awal, transfer/nonaktif sebelum download, expiry | Seluruh scope yang sah saja; formula dinetralkan; tidak hanya halaman aktif; unduh usang ditolak; audit jumlah/kolom |
| T32 | Refresh referensi parsial, parent hilang, cache aktif lama, data dipilih dari retired dataset | Dataset gagal tidak menimpa aktif; submit memakai versi yang disahkan; snapshot lama tetap terbaca |
| T33 | 50 sesi aktif, 400 lalu 4.000 SK, filter/pagination/draft/import | Catat p95/error/query/memori/worker; bandingkan target NF; perbaiki bottleneck nyata, tidak sekadar melaporkan build |
| T34 | Backup/restore ke host terpisah, worker mati, scheduler tidak jalan, rilis gagal | Restore dapat membuka data terenkripsi/file; RPO/RTO terukur; alert/SOP berjalan; rilis dapat dipulihkan |

## 4. Matriks pengujian Neo R2

| ID | Skenario | Hasil |
| --- | --- | --- |
| T35 | Kontrak auth/reference pagination berubah atau run hanya selesai sebagian | Gagal aman; tidak mengaktifkan dataset parsial; catatan schema/run sanitasi tersedia |
| T36 | Operator/guest memanggil sync langsung; admin tanpa NIM/mapping/accepted atau flag off | Tidak ada outbound write; readiness menyebut hambatan; secret tidak ke browser |
| T37 | Biodata sudah ada, history belum; data baru; history sudah cocok | Reuse ID terverifikasi; insert hanya yang diperlukan; history sudah ada tidak dibuat lagi |
| T38 | Biodata sukses, history gagal; retry; timeout sesudah insert | ID tahap sukses dipertahankan; tidak double insert; hasil ambigu perlu readback sebelum retry |
| T39 | Dua worker bersamaan, lock habis, data berubah setelah preview, akun pengaju nonaktif | Tidak ada write ganda; snapshot/lease tervalidasi; preview stale tidak dikirim; izin diperiksa ulang |
| T40 | API mengaku sukses tetapi readback tidak cocok/ambigu; koreksi setelah tersinkron | Bukan tersinkron sebelum cocok; kasus rekonsiliasi; PMB tetap; tidak insert mahasiswa kedua |

Integration test ke Neo nyata hanya di instance uji yang diizinkan dengan data sintetis dan pembersihan terencana. CI default memakai fake; test produksi tidak mengirim identitas nyata untuk “mencoba”.

## 5. Skenario UAT per peran

### Pendaftar Google

Masuk → pilih periode → lihat dashboard tanpa klaim → simpan sebagian biodata → ajukan SK → tunggu persetujuan → lengkapi lima tahap → tinjau/consent → kirim → lihat status → terima catatan perbaikan → ubah data → kirim ulang → simpan bukti. Penguji memeriksa tanggal awal tidak berubah, nomor PMB berbeda dari NIM, dan data tetap ada setelah keluar/masuk perangkat lain.

### Pendaftar WhatsApp

Ajukan nama/WA/pengenal → dapat referensi tanpa dashboard → balas konfirmasi operator pada kanal uji → terima kredensial manual → login terbatas → ganti sandi → lanjut formulir sama seperti Google. Uji pula akses kedaluwarsa dan nomor yang perlu diganti melalui operator.

### Operator

Masuk memakai akun nyata staging → bandingkan beban → buka peserta belum mendaftar → tinjau klaim tugas sendiri → catat bukti WA → approve klaim → serah akses → periksa formulir terbaru → minta perbaikan spesifik → periksa ulang → accepted. Coba ID peserta operator lain, perubahan tugas saat detail terbuka, dan aksi dengan versi lama; semua harus ditangani aman.

### Administrator

Buat periode/referensi/operator → import valid/error/reimport → preview dan bagi 400/4 → transfer → selesaikan nama kembar/nomor bersama → pantau dashboard → nonaktifkan operator → ekspor filter → audit aksi sendiri → tutup periode/revisi → cek restore. R2 menambahkan penetapan NIM/mapping, sync, kegagalan tahap kedua, dan rekonsiliasi.

## 6. Prosedur konkurensi yang dapat direproduksi

1. Siapkan satu SK belum diklaim dan dua klaim pemohon berbeda pada database engine target.
2. Jalankan dua koneksi/proses yang menahan dan melepas barrier sebelum transaksi approval, sehingga kedua pihak benar-benar berkompetisi.
3. Pastikan satu hasil sukses dan satu konflik/no-op sesuai identitas request; hitung aplikasi, claimed_user, approved_claim, audit, dan workspace yang terkait.
4. Ulangi pola untuk assignment batch, submit ganda, keputusan verifikasi/perbaikan, dan consume/revoke credential.
5. Catat isolation level/engine/versi serta log aman. Jangan menyimpulkan aman hanya dari dua klik berurutan pada satu browser.

## 7. Pemeriksaan dan bukti rilis

Perintah proyek yang **direncanakan dijalankan saat implementasi**:

```powershell
composer lint:check
composer types:check
php artisan test
npm run build
```

`composer test` sudah merangkai config clear, lint check, static analysis, dan Pest; `composer ci:check` memanggil rangkaian itu. Pilih satu jalur penuh agar tidak mengulang check yang sama tanpa alasan. Mulai dari tes sempit pada tiket, kemudian check penuh pada gate. Pemeriksaan kode aplikasi tidak perlu dijalankan hanya untuk mengubah dokumen rancangan; jangan melaporkan test PMB lulus ketika implementasinya belum ada.

| Artefak penerimaan | Isi minimum |
| --- | --- |
| Laporan otomatis | Commit/release ID, lingkungan, waktu, suite/check, lulus/gagal, temuan |
| Laporan UAT | ID skenario, penguji/peran, langkah/input sintetis, expected/actual, bukti, keputusan |
| Laporan visual | Halaman/viewport, keyboard/modal, nama panjang, bukti cetak, temuan dan perbaikan |
| Laporan beban | Dataset, sesi, durasi, konfigurasi server, p95/error/query/memori, simpulan terhadap NF |
| Laporan restore | Backup ID/waktu, target terpisah, kunci/file tersedia, waktu pemulihan, verifikasi data |
| Persetujuan rilis | Kebutuhan diterima, risiko sisa/pemilik, blocker nihil, keputusan minimum lengkap |

Klasifikasi temuan: blocker = alur inti tidak dapat berjalan/duplikasi atau kebocoran data; tinggi = invariant/keamanan/pemulihan utama gagal; sedang = fungsi penting memiliki jalan kerja terbatas; rendah = kosmetik/teks. Blocker dan tinggi harus selesai sebelum membuka periode produksi. Status pengujian semua skenario saat penyusunan dokumen ini adalah **belum dijalankan untuk fitur PMB**.
