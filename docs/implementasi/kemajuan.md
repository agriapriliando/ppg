# Kemajuan implementasi dan pekerjaan berikutnya

Dokumen ini menghubungkan kode aktual dengan [tahapan rancangan](../rancangan-aplikasi/07-tahapan-pembangunan.md). Status per 18 Oktober 2026; bukan pengesahan kebijakan kampus atau berita acara produksi.

## Urutan pekerjaan yang telah dilaksanakan

1. **Fondasi dan tema:** migrasi akun/periode/audit, Bootstrap dan ikon dari versi prototipe, layout publik/portal, autentikasi, role middleware, periode Livewire, pengguna dan pengaturan.
2. **SK dan penugasan:** parser Excel nyata, storage privat, queue validasi, pratinjau/commit, scope operator, round-robin, transfer dan riwayat.
3. **Pendaftaran:** dataset/naskah, klaim dua jalur, nomor aktif, credential/aktivasi, workspace dan formulir, pengiriman/revisi, pemeriksaan/keputusan, kasus administratif dan penarikan SK.
4. **Keluaran dan penguatan awal:** dashboard nyata, bukti, audit/riwayat, CSV privat, cleanup, profile/Google linking, pengujian HTTP/domain, lint/static analysis/build/audit.
5. **Penyempurnaan MVP:** laporan kesalahan Excel, retensi berkas/staging impor, transfer massal dan penyeimbangan beban dengan rincian pratinjau, delegasi klaim ambigu, tanggapan pemohon dan pembatalan klaim sendiri. Pemeriksaan otomatis terakhir lulus **97 tes / 642 assertion**, Pint dan Larastan level 7. Dua migrasi tambahan diterapkan pada SQLite lokal.
6. **Versi ketentuan, pemulihan dan konkurensi:** konfigurasi efektif relasional immutable dan rujukan setiap submission; riwayat admin/pemeriksa; pemulihan nomor hilang oleh admin dengan bukti dan aktivasi ulang. Pemeriksaan terbaru: **113 tes / 763 assertion** di SQLite serta MariaDB 11.8.2, ditambah **5 tes / 39 assertion** dua proses nyata di MariaDB. Lihat [laporan pengujian](pengujian-database-dan-konkurensi.md). Seluruh migrasi diterapkan pada `db_ppg` yang sebelumnya kosong, mengikuti koneksi `.env` terbaru tanpa menulis ulang konfigurasi pengguna.
7. **Koreksi kepemilikan F08/F10:** admin dapat mengoreksi kaitan satu pendaftaran ke akun aktif atau pemohon WhatsApp terverifikasi. Workspace/consent lama dipertahankan, review pendaftar dibatasi per kepemilikan dan bukti baru menunggu pengiriman pemilik saat ini. Kasus, pencabutan sesi/issuance, versi data dan konflik akun ditangani atomik. [SOP dan batas teknis](koreksi-kepemilikan.md) menjelaskan proses serta kondisi penolakan. Migrasi tambahan sudah diterapkan pada `db_ppg`; akun demo dipertahankan.

8. **Pengukuran dan optimasi F12:** dataset 400/4.000 peserta, impor/ulang identik/ekspor dan 50 sesi logis dengan maksimal delapan worker kernel telah diukur. Validasi 4.000 peserta turun 34,36 menjadi 9,26 detik dan 20.005 menjadi 8.053 query. Dua tes tambahan menjaga perilaku staging dan kelayakan operator. [Laporan performa](pengujian-performa.md) memuat bukti serta batas metode.

**Pemeriksaan terbaru:** Pint, Larastan level 7, **139 tes / 997 assertion** pada SQLite dan MariaDB 11.8.2 lulus; suite dua proses **6 tes / 49 assertion** sebelumnya lulus, termasuk dua admin yang memilih pemilik tujuan berbeda. Build Bootstrap dan kompilasi Blade berhasil. Pengesahan SOP, QA browser, beban HTTP staging dan UAT tetap terbuka.

Pemeriksaan HTTP lokal menemukan `SESSION_DRIVER=cache` yang tidak didukung. `.env` diperbaiki menjadi `SESSION_DRIVER=database`, sesuai implementasi pencabutan sesi. Beranda, masuk, panduan dan klaim publik kembali HTTP 200; koneksi tetap `db_ppg` dan ketiga akun demo tetap ada.

**Arahan pengguna:** selesaikan MVP terlebih dahulu; Neo menyusul. F15 tetap di luar pekerjaan aktif. Penyelesaian fitur tidak menggantikan gate browser, database target, UAT, dan operasional di bawah.

**Akun pemeriksaan lokal:** sesuai permintaan pengguna, `DatabaseSeeder` kini memanggil `DemoAccountSeeder` untuk akun admin/operator/pendaftar aktif. Seeder telah dijalankan pada `db_ppg`; akun dan password terverifikasi. Tidak menimpa akun yang sudah ada dan hanya berjalan di local/testing. Rincian ada di [akun demo](akun-demo.md). Pemeriksaan terbaru `composer test`: **117 tes / 817 assertion**, Pint dan Larastan lulus.

## Status terhadap fase

| Fase | Status aktual | Sisa sebelum ditutup |
| --- | --- | --- |
| F00 | Rancangan dan pemisahan keputusan tersedia; development memakai data sintetis | Pengesahan K-01–K-12 oleh pemilik kampus |
| F01 | Lokal berfungsi, konfigurasi/queue/scheduler/CI tersedia | Jalankan CI MySQL dan lingkungan staging nyata; monitoring/backup infrastruktur |
| F02 | Layout Bootstrap dan pola UI prototipe diterapkan pada fitur yang dibangun | QA visual, keyboard, drawer, picker, konfirmasi, dan cetak pada browser |
| F03 | Skema inti/constraint, policy/audit, versi konfigurasi relasional immutable dan rujukan per submission; locking diuji pada MariaDB | Pengesahan konfigurasi institusi dan bukti engine MySQL 8.4 pada CI/staging |
| F04 | Login, Google, role, sandi, aktivasi, pengaitan Google dan pemulihan nomor hilang terawasi bekerja pada tes | Callback Google riil, pengesahan SOP pembuktian identitas, penggabungan akun kompleks |
| F05 | Periode/jadwal, referensi lokal, naskah, informasi publik bekerja | Data referensi dan naskah resmi; pengesahan jadwal; editor FAQ/jadwal kegiatan lebih rinci bila dibutuhkan |
| F06 | Template, parse, staging, validasi, commit, reimport, riwayat, laporan kesalahan XLSX dan retensi otomatis bekerja | Pengujian batas workbook dan beban produksi lebih luas; pengesahan masa retensi |
| F07 | Pembagian awal, pratinjau, transfer tunggal/massal, penyeimbangan dengan perubahan minimum, scope dan histori bekerja | Uji dua admin bersamaan pada MySQL dan QA rincian pratinjau berhalaman |
| F08 | Klaim, triase/delegasi, tanggapan, pembatalan, bukti manual, approval, akses sekali tampil dan koreksi kepemilikan kedua jalur bekerja | Pengesahan SOP koreksi dan QA alur admin; tantangan kode jika metode tersebut disahkan |
| F09 | Lima tahap, draf, lookup berhalaman, validasi, consent, kirim/revisi/idempotensi bekerja | QA interaksi browser, fokus error dan ergonomi ponsel; peningkatan simpan saat pindah tahap bila disahkan |
| F10 | Review revisi, perbaikan, accepted guard, reopen, hold/withdrawal dan koreksi pemilik dengan batas histori pribadi bekerja | UAT kasus nyata, pengesahan SOP dan penggabungan dua aplikasi kompleks yang tetap tidak otomatis |
| F11 | Dashboard, bukti browser-print, audit, riwayat dan ekspor privat bekerja | Pemeriksaan visual cetak; pengesahan kolom ekspor dan konten resmi |
| F12 | Suite SQLite/MariaDB, enam skenario dua proses dan pengukuran kernel 400/4.000 peserta tersedia; validasi impor dioptimalkan berdasarkan hasil | MySQL 8.4 CI, perluasan interleaving/race administratif, 50 sesi HTTP serentak, queue, browser/console/network dan security review staging |
| F13 | Skenario/panduan tersedia | UAT, latihan restore, pilot dengan penanggung jawab kampus |
| F14 | Belum dijalankan | Deployment produksi, smoke test dan penerimaan operasional |
| F15 | Tetap nonaktif sesuai batas R1/R2 | Instance/kontrak Neo, ID/NIM/pemetaan, adapter, rekonsiliasi, uji dan rilis R2 |

## Keputusan implementasi yang perlu dipahami

- **Vite standar** dipakai menggantikan Vite Plus. Build lokal berhasil dan temuan npm dari dependensi awal hilang. Bootstrap/Icons dikunci mengikuti prototipe; Tailwind tidak menjadi dependency runtime.
- **Livewire SFC** dipakai pada pengelolaan periode. Form/controller HTTP dipakai untuk batas autentikasi, unggah, formulir penuh, download, dan secret sekali tampil. Domain tetap berada di action/service dan policy. Tidak ada REST API duplikat untuk aksi Livewire.
- **Draf disimpan eksplisit** melalui tombol Simpan Draf; perpindahan tab tahap memakai Alpine dan tidak mengklaim sudah menyimpan ke server. Waktu simpan hanya berasal dari hasil server.
- **Referensi R1 berasal dari dataset lokal yang disahkan**, diaktifkan atomik setelah validasi. Refresh referensi otomatis Neo belum tersedia. Versi lama disimpan agar data dan bukti historis terbaca.
- **Snapshot PMB** dan `applications.config_version_id` dibekukan pada submit pertama. Setiap `application_submissions.config_version_id` menunjuk konfigurasi efektif revisi tersebut. Versi dideduplikasi dengan digest per periode dan dinomori di bawah lock periode; tidak berubah karena assignment/lock_version saja. Rekaman bersifat immutable pada model, memakai FK restrict dan tidak memiliki endpoint ubah/hapus. Setiap pengiriman tetap mempunyai consent, digest dan snapshot biodata terenkripsi. Kolom pengesahan institusi tetap kosong pada capture otomatis; riwayat sebelum migrasi tidak difabrikasi. Pemetaan/validasi Neo belum dibangun.
- **Pemulihan nomor hilang** tersedia hanya bagi admin pada klaim WhatsApp disetujui dengan pemilik yang sama. Versi klaim/aplikasi/workspace diperiksa ulang, identitas dan balasan nomor baru harus dikonfirmasi dengan rujukan bukti. Nomor akun lain/kasus terbuka/akun disabled/SK hold atau withdrawn/periode arsip menolak proses. Penyimpanan atomik mencatat kasus, mengganti nomor formulir, membatalkan bukti lama, mencabut seluruh sesi/issuance/sandi lama, dan mewajibkan aktivasi ulang melalui issuance baru. Pengiriman terdahulu kembali perbaikan tanpa mengubah tanggal/snapshot/revisi pertama. Tidak memindahkan kepemilikan atau menggabungkan akun.
- **Verifikasi WhatsApp** saat ini mencatat bukti balasan manual pada nomor tertentu. Tidak ada pengiriman pesan API atau pemeriksaan keaktifan hanya dari format nomor. Konfigurasi tantangan kode masih cadangan rancangan, bukan bukti bahwa OTP sudah berjalan.
- **Kondisi SK** dan kasus administratif tidak menambah status ketujuh aplikasi. Keputusan lama tetap menjadi histori; penahanan/penarikan memberi blok dan peringatan pada alur/bukti terkait.
- **Koreksi kepemilikan** tersedia hanya melalui aksi admin dengan penahanan awal, klaim/consent tujuan, pemeriksaan kedua pihak dan alasan berbukti. Akun tujuan memakai workspace miliknya, bukan salinan biodata lama. `ownership_epoch` membatasi review yang terlihat pendaftar; bukti diblokir sampai pemilik saat ini mengirim formulir. PMB/tanggal/snapshot pertama dan consent historis tetap. Akun WhatsApp baru memerlukan proof nomor dan aktivasi sekali pakai. Koreksi dua aplikasi menjadi satu tetap tidak otomatis. Jangan mengubah FK langsung; rollback yang menghapus batas histori ditolak bila perpindahan sudah tercatat.
- **Penugasan massal** harus dipratinjau dan dikonfirmasi admin dengan alasan. Token berlaku 15 menit dan terikat akun admin. Konfirmasi menghitung ulang fingerprint seluruh peserta aktif, versi periode dan kelayakan operator. Transfer mencakup tugas aktif operator sumber; penyeimbangan mencakup seluruh peserta aktif dan mempertahankan sebanyak mungkin tugas lama sesuai kuota. Peserta ditarik tidak dipindahkan. Rincian ditampilkan 50 peserta per halaman; setiap perpindahan mempunyai assignment event.
- **Klaim tanpa kandidat** dapat didelegasikan admin hanya kepada operator aktif yang terdaftar pada periode. Pencocokan kandidat tetap aksi admin; delegasi tidak memberi hak melihat roster di luar penugasan. Pencabutan keanggotaan periode menghilangkan klaim delegasi dari daftar dan detail operator.
- **Tanggapan pemohon** hanya oleh pemilik klaim berstatus perlu informasi; pesan tersimpan sebagai riwayat, status kembali pending dan versi bertambah. Pesan tidak mengubah identitas, kandidat atau kepemilikan. Pembatalan sendiri hanya untuk pending/perlu informasi dengan konfirmasi dan versi terkini. Klaim batal tidak dapat dihidupkan melalui catatan pemeriksa atau disetujui.
- **Retensi impor** memakai usulan awal 30 hari sejak perubahan terakhir run, dapat diatur melalui `PMB_IMPORT_RETENTION_DAYS` (minimum efektif satu hari). Scheduler harian pukul 02.00 WIB membersihkan berkas privat serta baris staging yang tidak sedang diantre/divalidasi. Ringkasan, hash sumber, metadata run, audit dan roster dipertahankan. Pratinjau siap berubah menjadi expired; run committed tetap committed. Unduhan laporan yang melewati retensi mengembalikan 410. Pengesahan retensi dan backup masih diperlukan sebelum produksi.
- **CSV adalah salinan pada waktu penyiapan**. Field yang menyerupai formula dan pengenal dengan nol awal diberi prefix teks untuk penggunaan spreadsheet. Ekspor bukan template impor SK. Scope diperiksa kembali sebelum unduh.

## Prioritas pekerjaan berikutnya

1. Tinjau antarmuka pada browser 360/768/1280 px, termasuk pemilihan referensi induk-anak, ringkasan input belum tersimpan, drawer/fokus, serta bukti A4.
2. Jalankan matriks CI MySQL 8.4 dan enam tes dua proses yang sudah tersedia. Perluas race pemulihan/deaktivasi/bulk assignment pada staging; keenam skenario telah lulus di MariaDB 11.8.2.
3. Lanjutkan [pengukuran lokal 400/4.000 peserta](pengujian-performa.md) menjadi 50 sesi HTTP serentak pada staging, ukur queue/CPU/RSS dan throughput; tinjau query konfirmasi impor yang masih dominan.
4. Sahkan SOP pemulihan nomor dan koreksi kepemilikan yang sudah diimplementasikan; selesaikan keputusan kampus dan penggabungan akun dengan konflik aplikasi bila diperlukan.
5. Latih operator, laksanakan UAT dan restore/pilot, lalu tentukan kelayakan rilis R1.
6. Kerjakan R2 setelah kontrak Neo dan instance uji tersedia. Jangan menyalin UUID atau nilai contoh rancangan ke konfigurasi produksi.
