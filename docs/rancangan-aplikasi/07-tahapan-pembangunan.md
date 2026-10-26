# 07 — Tahapan pembangunan terperinci

## 1. Aturan pelaksanaan

Daftar di bawah adalah baseline tiket rancangan. Implementasi telah dimulai; hasil dan sisa pekerjaan dicatat pada [kemajuan implementasi](../implementasi/kemajuan.md). Kotak fase tidak dicentang massal hanya karena sebagian fitur telah dibuat. Satu tiket dapat menjadi satu atau beberapa commit yang koheren. Setiap tiket memuat perubahan domain beserta tes relevan, bukan menunda semua pengujian sampai akhir.

Urutan utama R1: **F00 → F01 → F02 → F03 → F04 → F05 → F06 → F07 → F08 → F09 → F10 → F11 → F12 → F13 → F14**. Ini urutan aman untuk satu pelaksana. F02 dapat menyiapkan layout sambil skema dibangun; jangan menghubungkan tombol ke data palsu yang kemudian dianggap fitur selesai. R2 F15 dimulai sesudah fondasi R1 stabil dan kontrak Neo tersedia.

Definisi selesai setiap tiket: implementasi tersimpan, validasi/policy sesuai kebutuhan, skenario positif dan kegagalan bernilai diuji, UI loading/error/empty relevan tersedia, tidak ada secret/data contoh produksi, serta dokumen terdampak diperbarui. Demo yang bisa diklik saja tidak memenuhi definisi ini.

## 2. Estimasi dan titik kendali

Estimasi adalah **hari-orang kerja efektif**, bukan janji tanggal selesai. Asumsi satu pengembang yang menguasai Laravel/Livewire, akses keputusan/akun uji tersedia, UI mengikuti prototipe, tanpa tambahan scope dokumen unggahan/pembayaran. Waktu menunggu persetujuan pengelola/provider tidak termasuk.

| Fase | Fokus | Estimasi | Dependensi / titik kendali |
| --- | --- | --- | --- |
| F00 | Keputusan awal dan kriteria penerimaan | 2–3 | Gate G0: scope dan keputusan minimum dicatat |
| F01 | Lingkungan, konfigurasi, CI | 2–3 | G0 |
| F02 | Shell UI dan komponen bersama | 4–6 | F01 |
| F03 | Skema domain dan policy dasar | 4–6 | F01; Gate G1: struktur data dapat dimigrasi |
| F04 | Autentikasi, staff, keamanan sesi | 5–7 | F03 |
| F05 | Periode, referensi lokal, konten publik | 3–4 | F02–F04 |
| F06 | Impor Excel SK | 5–7 | F03–F05 |
| F07 | Penugasan dan scope operator | 4–6 | F06; Gate G2: SK dan tugas nyata siap uji |
| F08 | Klaim, WA, penyerahan akses | 5–7 | F04, F07 |
| F09 | Formulir dan pengiriman | 5–7 | F05, F08 |
| F10 | Pemeriksaan dan perbaikan | 3–4 | F09; Gate G3: alur ujung ke ujung lulus |
| F11 | Dashboard, bukti, audit, ekspor | 3–5 | F07–F10 |
| F12 | Penguatan, keamanan, performa | 4–6 | F11 |
| F13 | UAT, restore, pilot | 3–5 | F12; Gate G4: siap rilis |
| F14 | Deployment R1 dan pendampingan awal | 2–3 | G4; Gate G5: penerimaan operasional |
| F15 | Neo Feeder R2 | 12–20 | R1 stabil, kontrak dan akun uji Neo sah |

Jumlah R1 **54–79 hari-orang**, kira-kira 11–16 minggu kerja satu pengembang, sebelum cadangan risiko. Tambahkan cadangan perencanaan sekitar 20% menjadi **65–95 hari-orang**. Penambahan tenaga tidak membagi durasi secara linear karena keputusan, integrasi, dan gate tetap berurutan. Re-estimasi setelah proof of concept parser Excel, Google, dan engine database.

## 3. F00 — Menetapkan dasar implementasi

**Tujuan:** tidak mengubah data contoh menjadi kebijakan kampus.

- [ ] **F00.1** Buat catatan keputusan K-01 s.d. K-12 pada 09: pemilik, tenggat, pilihan yang disahkan, bukti. Dahulukan sumber SK, pembeda identitas, periode, WA resmi, field wajib, dan database target.
- [ ] **F00.2** Review seluruh menu pada 03 bersama wakil admin/operator/pendaftar. Catat fitur R1/R2 dan yang di luar scope. Pastikan dokumen unggahan/sekolah tempat mengajar tidak diam-diam menjadi persyaratan.
- [ ] **F00.3** Tetapkan contoh SK sintetis: 400 entri, empat operator, nama kembar, nomor urut/pengenal nol awal, baris error, peserta ditarik, dan hasil 403/4. Jangan memakai identitas asli untuk development.
- [ ] **F00.4** Setujui state machine normal dan pengecualian admin; definisi diterima lengkap, tanggal pertama, deadline revisi, nomor PMB vs NIM, dan bukti cetak.
- [ ] **F00.5** Turunkan kebutuhan ID pada 01 menjadi tiket dan UAT pada 08; tetapkan pengambil keputusan dan penanggung jawab rilis.

**Keluaran:** register keputusan terisi sebagian dengan blocker jelas, scope R1 disepakati, dataset uji, daftar penerimaan. **Selesai bila:** tidak ada kebijakan yang belum diputus tetapi disamarkan sebagai default produksi. Fitur yang belum bergantung pada keputusan tertunda tetap dapat dibangun.

## 4. F01 — Fondasi lingkungan dan CI

**Berkas sasaran:** `.env.example`, `config/pmb.php`, `config/services.php`, `bootstrap/app.php`, `.github/workflows/tests.yml`, konfigurasi test; tanpa menulis secret ke Git.

- [ ] **F01.1** Catat versi PHP/Laravel/Livewire/node dan driver database target dari lock/runtime. Jalankan baseline check pada checkout implementasi dan simpan hasil agar kegagalan bawaan dapat dibedakan.
- [ ] **F01.2** Siapkan database development/test/staging terpisah, user DB dengan hak sesuai kebutuhan, disk privat, queue worker, locale Indonesia, app timezone UTC dan business timezone WIB.
- [ ] **F01.3** Tambah konfigurasi batas file, limiter, masa kredensial/kode/sesi aktivasi, serta feature flag Neo nonaktif. `.env.example` hanya placeholder; dokumentasikan daftar secret yang diperlukan.
- [ ] **F01.4** Sesuaikan CI: instal via lock, build frontend, Pint check, Larastan, Pest. Tambahkan job integrasi engine target untuk transaksi/constraint; jangan hanya SQLite.
- [ ] **F01.5** Verifikasi root web menuju `public`, health endpoint, permissions storage/cache, dan proses worker/scheduler; gunakan nama aplikasi/lingkungan yang jelas.

**Uji/exit:** instalasi bersih dapat dimigrasi dan dibuild; tes dasar berjalan; staging tidak memakai database produksi. Keluaran gate belum berarti fitur PMB ada.

## 5. F02 — Shell visual dan komponen

**Berkas sasaran:** `package.json` dan lock frontend, `vite.config.js`, `resources/views/layouts/`, `components/`, `pages/`, `resources/css/app.css`, `resources/js/app.js`, konfigurasi/view pagination Laravel dan Livewire.

- [ ] **F02.1** Tetapkan Bootstrap 5 dan Bootstrap Icons sebagai fondasi seluruh tampilan. Ganti dependensi/plugin/import Tailwind starter pada `package.json`, lock, `vite.config.js`, dan CSS; audit halaman Blade bawaan yang tetap dipakai. Pertahankan Vite untuk build aset lokal Bootstrap dan CSS khusus. Bentuk layout publik/portal mengikuti warna ungu/emas, tipografi, ornamen, navbar/sidebar/topbar/breadcrumb prototipe.
- [ ] **F02.2** Buat komponen Bootstrap untuk badge lima status tersimpan + belum_ada turunan, status WA/Neo terpisah, metric card, table, pagination, input/select/error, dialog konfirmasi, timeline. Atur pagination Laravel dan tema/view pagination Livewire agar sama-sama memakai Bootstrap.
- [ ] **F02.3** Buat shell seluruh halaman UI-P/D/O/A pada 03. Tiap route yang belum mempunyai backend diberi keadaan belum tersedia pada development; tidak memakai aksi simulasi sukses.
- [ ] **F02.4** Implementasikan drawer ponsel, fokus modal, keyboard/Escape, loading, toast error/sukses, empty/error state, dan gaya cetak awal. Tetapkan satu pengendali state per komponen Bootstrap/Livewire; uji inisialisasi ulang, disposal instance, backdrop, dan scroll lock saat rerender/navigasi.
- [ ] **F02.5** Hapus ketergantungan role switch, reset demo, fixed 400/100, localStorage biodata, dan Alpine vendor terpisah dari target produksi. Prototipe di `docs/ppgui` tetap sebagai referensi.

**Uji/exit:** review pada 360/768/1280 px dan keyboard; build Bootstrap berhasil; pagination Laravel/Livewire konsisten; modal/offcanvas tidak menggandakan instance/backdrop sesudah rerender/navigasi; tidak ada dependensi/plugin/import/utilitas Tailwind aktif pada tampilan produksi; tidak ada kontrol demo yang memberi kesan autentikasi nyata.

## 6. F03 — Skema domain dan policy

**Berkas sasaran:** migration baru, `app/Models`, `app/Enums`, `app/Policies`, factory sintetis.

- [ ] **F03.1** Buat migrasi sesuai urutan bagian 10 dokumen 04; ubah users melalui migrasi tambahan. Catat FK siklus yang ditambahkan belakangan.
- [ ] **F03.2** Definisikan enum role/account/application/claim/period, casts tanggal/JSON/enkripsi, relationships dan query scope periode/penugasan. Pertahankan pola model Laravel 13 yang sudah dipakai.
- [ ] **F03.3** Terapkan unique aplikasi-SK, user-periode, workspace, nomor PMB, provider identity, business key SK, serta relasi referensi. Jangan memakai unique nama/WA.
- [ ] **F03.4** Buat policy dasar dan action pemeriksaan konsistensi owner/periode. Gunakan actor server; tidak ada assign `role/status/operator_id` dari request massal.
- [ ] **F03.5** Buat factory user per role, periode, SK, klaim, workspace, aplikasi per status. Buat command bootstrap admin tanpa sandi tetap di source/seeder produksi.
- [ ] **F03.6** Buat layanan audit terpusat dengan allowlist safe_changes dan request_id; peristiwa penting ditulis dalam transaksi domain.

**Uji/exit G1:** migrate fresh/upgrade; constraint benar-benar menolak duplikasi melalui operasi domain; owner/periode salah ditolak; audit tidak bocor field sensitif. Acuan T01, T06–T08, T21, T28 pada 08.

## 7. F04 — Autentikasi dan akun pengelola

**Berkas sasaran:** controller/requests Auth, action Auth, middleware account state, social/invitation/credential models, halaman masuk/akun.

- [ ] **F04.1** Pasang Socialite kompatibel, atur provider di config, buat redirect/callback dengan state, resolver provider ID, email verified, error/cancel, dan benturan identitas. Akun baru publik selalu pendaftar.
- [ ] **F04.2** Buat undangan pengelola sekali pakai atau pengaitan melalui login lokal sah. Admin dapat membuat/menonaktifkan staff serta operator periode; lindungi admin aktif terakhir.
- [ ] **F04.3** Buat login lokal POST, limit, hash, pesan generik, logout POST, rotasi sesi. Rancang konsumsi temporary issuance terpisah dari sandi permanen.
- [ ] **F04.4** Implementasikan state pending_activation, sesi aktivasi terbatas, ganti sandi awal, timeout dan revoke; dashboard tidak terbuka sebelum selesai.
- [ ] **F04.5** Implementasikan profil nama tampilan, ganti sandi dengan autentikasi ulang, pengaitan Google terverifikasi, pemulihan manual WA, dan pencabutan sesi.
- [ ] **F04.6** Uji semua route pribadi dengan guest, role salah, akun nonaktif, sesi aktivasi, serta request Livewire langsung. Jangan hanya menguji menu tersembunyi.

**Uji/exit:** T02–T05, T26–T27. OAuth staging valid; identity callback palsu/state salah ditolak; user lokal tanpa email bekerja; disabled user kehilangan sesi; staff tidak bisa diprovisikan dari payload publik.

## 8. F05 — Periode, referensi lokal, dan portal publik

**Berkas sasaran:** halaman admin periode/referensi/pengaturan, action config, dataset loaders, halaman publik.

- [ ] **F05.1** Buat CRUD periode dan versi konfigurasi PMB, jadwal/deadline revisi, publikasi, tutup/buka beralasan, arsip, pemilihan periode publik.
- [ ] **F05.2** Buat satu evaluator waktu server yang dipakai semua aksi klaim/submit/revisi. Konversi input jadwal WIB ke UTC dan date bisnis tanpa pergeseran.
- [ ] **F05.3** Muat dataset lokal sah untuk wilayah dan opsi biodata/PT/prodi melalui proses admin/perintah tervalidasi. Dataset staging tidak menjadi aktif sebelum lengkap.
- [ ] **F05.4** Buat editor konten aman: identitas, kontak/jam layanan, FAQ, jadwal kegiatan dan panduan yang disahkan; versi consent klaim/submission.
- [ ] **F05.5** Hubungkan beranda/panduan dan cek SK generik ke server; pasang throttle, validasi, keadaan sebelum buka/tutup, tanpa nama kandidat hasil pencarian publik.

**Uji/exit:** T10, T16, T23–T24, T30. Waktu tepat batas tutup ditolak untuk kirim pertama; ketergantungan Neo nonaktif tidak menghalangi referensi lokal sah; tidak ada kontak/nomor contoh.

## 9. F06 — Impor Excel resmi

**Berkas sasaran:** template exporter, parser adapter, requests/upload controller, import jobs/actions, staging tables, halaman impor.

- [ ] **F06.1** Proof of concept parser `.xlsx` dengan file valid/error, tanggal serial/ISO, nol awal, formula, dan batas ukuran/ekspansi. Pilih dependency berdasarkan hasil kompatibilitas, lalu commit lock.
- [ ] **F06.2** Buat template dua sheet versi 1 dengan format kolom teks dan panduan sesuai 06. Template tidak berisi identitas asli.
- [ ] **F06.3** Buat upload privat, hash, staging, validasi seluruh sheet/baris, pesan kesalahan bernomor baris, dan run status. Tidak ada write roster pada validasi.
- [ ] **F06.4** Buat diff baru/identik/perubahan/konflik, pemeriksaan identitas/operator/periode dan preview hash/expiry. Jelaskan peserta hilang dari file tidak otomatis dihapus.
- [ ] **F06.5** Buat commit atomik dengan lock periode/cek ulang versi, audit, dan idempotensi. Simpan sumber terakhir tanpa mereset klaim/status/WA/penugasan.
- [ ] **F06.6** Buat halaman riwayat/detail run dan laporan kesalahan privat; tangani worker mati/gagal parsing tanpa label selesai palsu.

**Uji/exit:** T11–T13. File 400 baris menghasilkan 400 roster nyata; satu row salah menghasilkan nol perubahan roster; reimport identik no-op; stale preview harus diulang.

## 10. F07 — Pembagian tugas dan isolasi operator

**Berkas sasaran:** assignment actions/batches/events, policy scope, halaman admin penugasan dan operator peserta.

- [ ] **F07.1** Implementasikan pratinjau round-robin deterministik untuk SK belum ditugaskan, termasuk beban awal/tambahan/akhir. Penugasan eksplisit Excel dikecualikan.
- [ ] **F07.2** Buat apply batch dengan lock periode dan hash; operator nonaktif atau batch berubah membatalkan apply. Tidak membagi ulang tugas yang sudah ada.
- [ ] **F07.3** Buat transfer satu/massal dan rebalancing eksplisit, alasan wajib, daftar perpindahan sebelum konfirmasi, serta riwayat aktor/tugas.
- [ ] **F07.4** Hubungkan scope kini ke seluruh query operator, detail, klaim, dashboard, dan ekspor; tangani klaim ambigu memakai review_owner terpisah.
- [ ] **F07.5** Buat alur operator nonaktif: tolak sesi, tampilkan beban tertinggal pada admin, transfer melalui prosedur beralasan.

**Uji/exit G2:** T14–T15 dan T26. Distribusi 400/4, 403/4, peserta < operator benar; dua admin tidak double-apply; ID URL/Livewire di luar tugas ditolak, termasuk setelah transfer.

## 11. F08 — Klaim, WhatsApp, dan akses manual

**Berkas sasaran:** klaim publik/Google, triase admin, verifikasi WA, action approval/credential, controller secret sekali tampil.

- [ ] **F08.1** Buat pengajuan publik dan Google dengan consent, nomor ternormalisasi, request_key, respons aman, deduplikasi pending identik, dan pembatasan percobaan.
- [ ] **F08.2** Buat kandidat internal exact/normalized yang tidak otomatis approve; nama kembar/tidak cocok masuk triase admin; penanggung jawab klaim eksplisit.
- [ ] **F08.3** Buat catatan pemeriksaan, needs_information/declined/cancelled, public_note aman dan internal_note terpisah. Tambahan informasi tidak mengunci SK.
- [ ] **F08.4** Buat tantangan manual, hash/expiry/attempt, bukti balasan per nomor, invalidasi nomor berubah, dan penanganan nomor bersama.
- [ ] **F08.5** Buat `ApproveClaim` atomik: user/workspace/aplikasi/nomor PMB, satu SK dan akun-periode, audit. Pindah pemilik hanya admin melalui kasus.
- [ ] **F08.6** Buat generate temporary credential, respons no-store sekali tampil, catat delivery, revoke/reissue, dan hubungkan ke aktivasi F04. Tidak ada API pengiriman pesan.
- [ ] **F08.7** Buat SOP pemulihan dan salah kirim yang dapat dicatat; verifikasi operator dan akun tujuan sebelum akses baru.

**Uji/exit:** T06–T09, T17, T27. Dua approval bersamaan menghasilkan satu aplikasi; Google sudah bisa dashboard tetapi belum submit tanpa SK; WA belum delivery/aktivasi tidak bisa dashboard; kode/sandi tidak muncul di log atau snapshot Livewire.

## 12. F09 — Formulir, draf, dan pengiriman

**Berkas sasaran:** workspace/biodata/education, validation rules, SFC formulir lima tahap, submission/consent actions.

- [ ] **F09.1** Buat workspace per akun/periode sebelum klaim; semua data berasal server. Sesudah approval aplikasi menunjuk workspace yang sama tanpa menyalin dua sumber formulir.
- [ ] **F09.2** Implementasikan identitas/kontak, alamat berantai, KPS bersyarat, PT/prodi berantai, dan ringkasan. Lihat seluruh field pada 04; tidak mengubah NIK/NISN/nomor menjadi integer.
- [ ] **F09.3** Buat validasi draf vs final terpusat; validasi pasangan referensi dan data yang sudah diisi; error tetap pada field/tahap terkait.
- [ ] **F09.4** Simpan draft versioned, saved_at dari server, konflik dua tab dan retry jaringan; tidak menyimpan biodata pada localStorage. Ganti nomor WA membatalkan verifikasi.
- [ ] **F09.5** Buat submit dengan policy, full validation, consent version, snapshot awal dan tanggal WIB; resubmit menambah revisi tanpa mengganti tanggal pertama.
- [ ] **F09.6** Kunci seluruh edit pada terkirim/diverifikasi/accepted, termasuk request langsung; kirim ganda/idempotent retry tidak menambah aplikasi atau revision tak sengaja.

**Uji/exit:** T16–T20, T22–T24. Draf pra-klaim tidak menambah jumlah aplikasi dashboard; field nol awal utuh; consent hilang ditolak; first submit dekat tengah malam menghasilkan tanggal WIB benar.

## 13. F10 — Pemeriksaan, perbaikan, dan keputusan

**Berkas sasaran:** antrean/detail operator/admin, review actions, administrative cases, status timeline.

- [ ] **F10.1** Buat antrean query terkirim/diverifikasi/perbaikan, sorting stabil, pencarian/filter dan detail lengkap dalam scope; bandingkan nama SK/biodata tanpa menganggap beda sebagai salah otomatis.
- [ ] **F10.2** Simpan checklist pada revisi yang diperiksa; state machine server menolak transisi lompat. Cegah perubahan versi lama oleh dua pemeriksa.
- [ ] **F10.3** Buat request perbaikan dengan kelompok/field dan catatan wajib; pendaftar melihat catatan aman/deadline, memperbaiki, lalu kirim ulang.
- [ ] **F10.4** Buat accepted guard lengkap: seluruh data valid, klaim/roster sah, WA nomor kini sah, konflik/hold selesai, checklist revisi terbaru. Tidak menuntut Neo sukses.
- [ ] **F10.5** Buat kasus admin untuk withdrawn, hold, pindah kepemilikan, dan reopen accepted → perbaikan; pertahankan tanggal/histori dan invalidasi pemeriksaan yang terdampak.

**Uji/exit G3:** T18–T22, T25. Jalankan alur klaim → draf → kirim → perbaikan → kirim ulang → diverifikasi → accepted, untuk kedua jalur akun. Perbaikan berulang tetap memakai tanggal awal.

## 14. F11 — Pelaporan dan keluaran

**Berkas sasaran:** dashboard queries, print Blade, export actions/jobs, audit pages, akun/bantuan final.

- [ ] **F11.1** Hubungkan metric SK/aplikasi/klaim/WA sesuai definisi 01; jumlah enam kategori cocok total SK; semua filter dan scope operator konsisten.
- [ ] **F11.2** Buat bukti setelah submit pertama, status saat cetak, nomor PMB ≠ NIM, revisi/waktu, A4 dan nama panjang. Bukti accepted tidak menjadi klaim bahwa sudah Neo.
- [ ] **F11.3** Buat ekspor server seluruh filter, paket kolom disetujui, CSV formula protection, file privat/expiry, audit dan pemeriksaan ulang scope saat unduh.
- [ ] **F11.4** Buat halaman audit admin dan riwayat operator/pendaftar yang berbeda visibilitas, filter tanggal/aktor/aksi, serta safe_changes tanpa data sensitif penuh.
- [ ] **F11.5** Finalkan akun, bantuan, kanal operator, kontak/jam layanan, dan seluruh empty/error state dari inventaris layar; periksa tidak ada menu tanpa fungsi.

**Uji/exit:** T28–T31. Agregat bukan angka sampel; ekspor tidak bocor scope/secret; cetak telah ditinjau visual dan dapat disimpan PDF lewat browser.

## 15. F12 — Penguatan dan pemeriksaan lintas modul

- [ ] **F12.1** Jalankan matriks policy dan request langsung untuk IDOR, eskalasi peran, CSRF, akun nonaktif, Livewire property tampering, dan batas unduh.
- [ ] **F12.2** Jalankan uji dua koneksi/worker pada engine target: klaim, assignment, submit, keputusan, issuance/revoke; catat hasil konsistensi database, bukan hanya respons UI.
- [ ] **F12.3** Jalankan beban sintetis sesuai NF-01/NF-02, periksa N+1 dan query plan, lakukan pagination/index tuning berdasarkan hasil; ukur ulang bagian yang diubah.
- [ ] **F12.4** Periksa secret/PII pada log, trace error, queue payload, snapshot Livewire, cetak/ekspor, dan source HTML. Pastikan cache tidak mempertahankan scope lama.
- [ ] **F12.5** Periksa dependency audit, konfigurasi produksi, file privat, response no-store, limit, invalidasi sesi, monitoring worker/scheduler dan cleanup retention.
- [ ] **F12.6** Selesaikan temuan blocker/tinggi dan simpan bukti; hasil yang belum lulus tidak diberi label siap rilis.

**Exit:** T26–T34 lulus sesuai scope, seluruh invariant 01 terjaga, pemeriksaan proyek dan build hijau. Risiko sisa mempunyai pemilik/mitigasi yang disetujui.

## 16. F13 — UAT, restore, dan pilot

- [ ] **F13.1** Sediakan akun staging sintetis untuk satu admin, empat operator, peserta Google dan WA; staf menguji skenario 08 tanpa role switch.
- [ ] **F13.2** Jalankan UAT berdasarkan pekerjaan nyata, termasuk file SK salah, nama kembar, nomor bersama, salah kirim, perbaikan setelah tutup, transfer dan akun nonaktif.
- [ ] **F13.3** Jalankan backup database/file/kunci dan restore ke lingkungan terpisah; ukur RPO/RTO dan uji ulang login, draf, audit, file privat.
- [ ] **F13.4** Simulasikan worker mati, jaringan terputus, sesi habis, Google gagal, dan deployment gagal; operator mengetahui SOP serta kanal eskalasi.
- [ ] **F13.5** Pilot terbatas dengan izin pengelola dan data yang sesuai lingkungan; tutup temuan, latih staf untuk bukti WA/kredensial/cek status, finalkan panduan pengguna.

**Exit G4:** berita acara UAT, latihan restore, daftar keputusan minimum selesai, tidak ada blocker, checklist pra-rilis 09 disahkan. Data pilot bukan otomatis data produksi.

## 17. F14 — Deployment dan penerimaan R1

- [ ] **F14.1** Siapkan release immutable dengan dependensi/build, backup pra-migrasi, konfigurasi secret produksi, domain/HTTPS dan callback Google resmi. Terapkan urutan deployment 09.
- [ ] **F14.2** Jalankan migrasi non-destruktif; restart worker; periksa scheduler, storage, logging, health, cache config dan akses publik/portal.
- [ ] **F14.3** Jalankan smoke test tanpa mengirim pesan/data nyata yang tidak diotorisasi: login staf, role, periode, roster count, draf uji terkontrol, file privat, halaman bantuan.
- [ ] **F14.4** Pengelola mengimpor SK resmi dan mengesahkan tugas/konten/periode; pembukaan penerimaan merupakan tindakan admin yang tercatat.
- [ ] **F14.5** Pantau antrean klaim, WA, formulir, error, backup dan beban selama masa awal; catat masalah/rilis koreksi dan serahkan runbook kepada pengelola.

**Exit G5:** proses nyata dapat berjalan, penanggung jawab menerima hasil, backup dan monitoring aktif, serta status implementasi dokumentasi diperbarui berdasarkan bukti. Belum menyatakan Neo aktif.

## 18. F15 — Neo Feeder sebagai R2

**Berkas sasaran:** `app/Services/Neo`, jobs sync/reconcile, schema Neo, admin mapping/NIM/readiness, contract tests.

- [ ] **F15.1** Peroleh instance uji dan kontrak sanitasi resmi; buktikan auth, reference pagination, field biodata/riwayat, kode error, readback, dan kunci pencarian yang tidak ambigu. Jika lookup tidak memadai, tetapkan SOP sebelum write.
- [ ] **F15.2** Buat adapter HTTP dengan host/config server, timeout, masking dan fake; tidak menaruh token pada browser/log. Buktikan kompatibilitas field terhadap dataset formulir.
- [ ] **F15.3** Buat refresh referensi atomik bertahap, tanpa menghapus versi aktif saat gagal; simpan source/version/run metrics.
- [ ] **F15.4** Buat pengesahan mapping, penetapan NIM dan readiness per aplikasi; config snapshot PMB tidak ditimpa; konfirmasi memakai hash/revisi terbaru.
- [ ] **F15.5** Buat sync job biodata → riwayat → readback, lock, simpan ID tahap sukses, outcome pasti/ambigu, resume tahap yang belum selesai, dan rekonsiliasi.
- [ ] **F15.6** Buat panel status/attempt aman, retry admin, kasus data berubah setelah sinkron dan blok insert ulang; PMB tetap usable saat Neo mati.
- [ ] **F15.7** Jalankan T35–T40 dengan fake dan instance uji terkontrol, UAT admin, backup, pilot terbatas, lalu aktifkan flag produksi hanya sesudah gate R2.

**Exit R2:** tidak ada duplikasi pada retry/timeout; hasil tersinkron terbukti readback; operator tidak punya endpoint tulis; kontrak/konfigurasi dan SOP koreksi terdokumentasi.

## 19. Pemetaan kebutuhan ke pekerjaan

| Kebutuhan pada 01 | Fase pemilik | Verifikasi utama pada 08 |
| --- | --- | --- |
| PUB-01/02 | F02, F05, F08 | T10, T30 |
| AUTH-01/02/03 | F04, F08 | T02–T05, T27 |
| ADM-01/02 | F03–F05, F07 | T01, T05, T23–T24 |
| SK-01/02 | F06 | T11–T13 |
| ASN-01/02 | F07 | T14–T15, T26 |
| CLM-01/02, WA-01/02 | F08 | T06–T09, T17, T27 |
| FRM-01/02 | F09 | T16–T20, T22–T24 |
| REV-01/02 | F10 | T18–T22, T25 |
| OUT-01/02, AUD-01 | F03, F11 | T28–T31 |
| REF-01 | F05, F15 | T16, T32, T35 |
| NEO-01/02 | F15 | T35–T40 |
| NF-01 s.d. NF-07 | F12–F14 | T26–T34 dan laporan UAT/restore |

Jika sebuah perubahan menggeser invariant, jangan hanya memperbaiki UI. Perbarui action, constraint jika terkait, tes, dan dokumen alur/skema sebelum tiket dinyatakan selesai.
