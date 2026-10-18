# 09 — Operasional, risiko, dan keputusan pengelola

## 1. Penanggung jawab dan lingkungan

| Tanggung jawab | Pemilik peran yang diusulkan |
| --- | --- |
| Scope/kebijakan PMB, SK dan keputusan kasus | Penanggung jawab PPG kampus |
| Jadwal, operator, data referensi, penugasan dan rilis periode | Administrator PMB |
| Klaim/WA, pengiriman kredensial manual, pemeriksaan formulir | Operator sesuai tugas |
| Aplikasi, database, secret, deployment, monitoring, backup | Pengelola infrastruktur bersama pengembang |
| UAT dan penerimaan | Wakil pengelola, operator, dan pendaftar |
| Pemetaan/NIM/kontrak Neo | Administrator akademik/Neo yang ditetapkan kampus |

Nama orang, pengganti, dan kanal eskalasi harus diisi sebelum pilot. Development memakai data sintetis. Staging mempunyai database, OAuth redirect, secret, dan kanal uji sendiri. Produksi memakai domain resmi, HTTPS, akses DB terbatas, penyimpanan privat, queue worker dan scheduler yang diawasi.

Topologi awal cukup satu deployment aplikasi, database relasional terpisah secara akses, storage privat dan backup di lokasi berbeda. Pemilihan ukuran server mengikuti hasil beban T33 dan kapasitas penyimpanan/retensi yang disetujui; tidak dianggap teruji hanya karena target awal 400 peserta. Tidak memerlukan Kubernetes atau layanan tambahan untuk sekadar memenuhi prototipe.

## 2. Konfigurasi produksi minimum

| Kelompok | Isi dan pengaman |
| --- | --- |
| Aplikasi | APP_ENV produksi, debug off, URL/domain resmi, APP_KEY terkelola, locale Indonesia, waktu server tersinkron |
| Database | Driver/versi yang ditetapkan, user aplikasi, credential rahasia, backup, charset/index/constraint yang diuji |
| Sesi/cache | Backend konsisten antarworker; cookie aman; invalidasi user nonaktif; lock store bila digunakan pada job |
| Queue/scheduler | Worker sebagai service dengan restart otomatis; jadwal cleanup/backup/monitor; job failure alert |
| File | Import/export privat; root web hanya `public`; unduh berotorisasi; ruang disk dan expiry dipantau |
| OAuth | Client ID/secret server, redirect URI persis, akun pengelola terikat sah, alur kegagalan |
| PMB | Versi config, periode, deadline revisi, consent aktif, referensi sah, kontak/jam layanan |
| Neo R2 | Flag off pada R1; endpoint/kredensial server, kontrak/mapping terverifikasi sebelum enable |

Jangan menyalin `.env` antarlingkungan atau mengganti `APP_KEY` sembarangan: data terenkripsi dan sesi dapat tidak terbaca. Rotasi kunci memerlukan inventaris data, strategi pembacaan kunci lama, cadangan, dan pengujian restore. Secret tidak masuk repositori, dokumen publik, screenshot panduan, atau pesan audit.

## 3. Runbook deployment

1. Tentukan release ID, changelog, migration plan, hasil CI/UAT dan penanggung jawab. Siapkan artefak kode/vendor/build dari lock yang sama.
2. Periksa antrean aktif, terutama impor/ekspor/Neo. Jangan memutus proses write eksternal tanpa pencatatan tahapnya. Jadwalkan maintenance bila perubahan tidak aman berjalan bersama versi lama.
3. Buat backup database, file privat yang perlu, dan konfigurasi/kunci melalui jalur aman; catat backup ID dan bukti dapat dibaca.
4. Pasang release, secret/config lingkungan, permission storage/cache; jalankan migration forward yang sudah diuji. Jangan memakai `migrate:fresh`, `db:wipe`, atau seeder demo pada produksi.
5. Bangun/aktifkan cache konfigurasi dan view sesuai kompatibilitas aplikasi; gunakan artefak frontend yang telah dibuild. Restart worker agar tidak memproses job memakai kode lama.
6. Jalankan smoke test health/login staf/role/periode/dashboard/akses file privat; periksa log aman, queue/scheduler dan error rate. Data uji produksi harus terbatas dan disetujui pengelola.
7. Buka layanan, pantau, dan catat waktu/release. Pembukaan periode PMB adalah aksi admin tersendiri sesudah roster/tugas/konten siap.

Jika gagal: hentikan operasi tulis yang berisiko, kembali ke release aplikasi sebelumnya hanya bila skema masih kompatibel, atau lakukan forward fix yang telah ditinjau. Restore database hanya setelah menilai write sejak backup; catat potensi kehilangan data dan rekonsiliasi. Jangan menjalankan rollback migrasi destruktif sebagai respons otomatis.

## 4. Backup, restore, dan retensi

Usulan target awal: **RPO 24 jam, RTO 4 jam**; pengelola harus menyetujui atau menyesuaikan. Backup setidaknya harian selama penerimaan, tambahan sebelum migrasi/impor massal penting. Jika RPO yang disetujui lebih kecil, jadwal/teknologi backup perlu disesuaikan.

Backup mencakup database, file privat yang masih diperlukan, versi release/konfigurasi, serta kemampuan memperoleh kunci enkripsi melalui penyimpanan secret yang terpisah. Backup terenkripsi, akses dibatasi, dan memiliki salinan di lokasi berbeda. Riwayat sukses job backup tanpa restore belum membuktikan data dapat dipulihkan.

Latihan restore sebelum go-live dan setelah perubahan besar: pulihkan ke lingkungan terisolasi, cegah outbound Google/Neo/WA otomatis, uji integritas jumlah SK/aplikasi, relasi owner/tugas, login terkontrol, dekripsi field, file privat, audit, dan tanggal pertama. Catat waktu aktual serta kekurangan terhadap RPO/RTO.

| Data | Usulan pengelolaan; keputusan final K-09 |
| --- | --- |
| File/staging impor | Simpan selama koreksi/sengketa impor diperlukan; usulan pembersihan file sementara 30 hari sesudah commit/expired, ringkasan run tetap sesuai retensi |
| Export privat | Usulan expiry 24 jam dan cleanup file; metadata audit mengikuti retensi audit |
| Kode tantangan/sandi sementara | Hanya hash; expiry sesuai SOP, cabut segera saat nomor berubah/salah kirim; tidak menahan plaintext |
| Biodata/aplikasi/consent/review | Retensi institusi harus disahkan; bukan otomatis selamanya dan bukan dihapus setelah tutup periode |
| Audit/log/backup | Lama simpan, siapa boleh membaca, rotasi, dan penghapusan harus disahkan bersama pengelola |
| Kasus administratif | Simpan bukti minimum yang diperlukan, dengan visibilitas terbatas; jangan menyalin isi chat/identitas penuh tanpa kebutuhan |

Angka usulan bukan penetapan kepatuhan hukum. Kebijakan data kampus menjadi dasar keputusan; rancangan ini tidak mengklaim sertifikasi atau pemenuhan regulasi yang belum ditelaah/disahkan.

## 5. Monitoring dan SOP pekerjaan harian

| Indikator | Tindakan dan pemilik |
| --- | --- |
| Klaim pending/needs_information menua | Admin/operator menindaklanjuti menurut SLA yang disepakati; pisahkan klaim tanpa penanggung jawab |
| SK belum ditugaskan/operator nonaktif | Admin memeriksa beban, preview transfer, alasan dan konfirmasi |
| WA belum sah atau nomor berubah | Operator memverifikasi ulang; accepted tidak boleh dipaksakan |
| Issuance belum delivered/expired | Operator verifikasi tujuan, reissue bila perlu, catat delivery; tidak membuka ulang secret lama |
| Formulir terkirim/perbaikan melewati tenggat | Operator/admin menghubungi melalui kanal resmi sesuai kebijakan; perubahan deadline dicatat |
| Import/reference/export gagal atau worker berhenti | Infrastruktur meninjau run/log aman dan melakukan retry yang tepat; tidak mengubah state menjadi sukses manual |
| Backup gagal/ruang disk hampir habis | Infrastruktur memulihkan jadwal dan kapasitas; eskalasi sebelum data baru tidak dapat dicadangkan |
| Error auth/OAuth meningkat, 5xx/latensi tinggi | Tinjau callback, sesi, DB/queue/dependensi; hindari log secret/PII |
| Neo perlu_verifikasi atau lama diproses | Admin Neo melakukan readback/rekonsiliasi; bukan blind insert ulang |

Ambang alert usulan: worker/scheduler tidak memberi heartbeat lima menit, backup harian melewati satu jadwal, error 5xx > 1% selama lima menit. Sesuaikan volume agar traffic kecil tidak menghasilkan alert menyesatkan. SLA tanggapan klaim/verifikasi adalah kebijakan staf, bukan angka yang diasumsikan dari jam layanan contoh 08.00–16.00.

## 6. SOP insiden dan koreksi

**Salah kirim akses:** cabut issuance dan sesi terkait → amankan akun → verifikasi nomor/identitas yang benar → terbitkan ulang → catat insiden tanpa secret → tindak lanjut pengelola bila data terungkap.

**Dugaan kebocoran/akses lintas peserta:** batasi fitur/akun terdampak → simpan bukti log aman/release/request ID → tentukan data dan pengguna terdampak → perbaiki policy/scope → uji ulang matriks → pengelola menentukan komunikasi dan tindak lanjut sesuai kebijakan institusi. Jangan menghapus audit untuk menyembunyikan kejadian.

**Klaim sah ternyata keliru:** admin membuat kasus/hold → cabut akses relevan → periksa bukti kedua identitas → tetapkan pemilik yang benar secara atomik → periksa ulang WA/review/consent yang terdampak → catat resolusi dan komunikasikan melalui kanal resmi. Tidak membuat aplikasi kedua untuk menghindari constraint.

**Neo timeout:** simpan tahap/ID/outcome → tandai perlu_verifikasi → baca balik dengan kunci yang diuji → reuse hasil bila sudah ada → retry hanya tahap yang dipastikan belum terjadi. Keputusan PMB tetap.

**Penutupan periode:** umumkan deadline → pastikan waktu server/jadwal → larang klaim/kirim pertama sesuai evaluator → proses backlog/revisi sesuai deadline → selesaikan kasus atau beri keputusan administratif → ekspor/backup sesuai izin → arsip dengan akses baca.

## 7. Daftar keputusan pengelola

Status awal semua keputusan di bawah: **belum disahkan**, kecuali informasi dasar yang memang berasal dari sumber. Isi tanggal dan bukti saat benar-benar diputus; tidak memblokir pekerjaan yang tidak bergantung padanya.

| ID | Keputusan yang dibutuhkan | Pemilik | Paling lambat / dampak jika belum ada |
| --- | --- | --- | --- |
| K-01 | SK resmi, nomor/tanggal, format, pengenal pembeda, aturan tambah/cabut dan nama kembar | Pengelola PMB | Sebelum validasi template/UAT klaim; jangan mengarang identifier/NIK |
| K-02 | Nomor/perangkat WA resmi, siapa boleh mengirim, metode bukti, SLA layanan dan pemulihan nomor | Pengelola/operator | Sebelum F08 pilot; akses manual belum boleh dipakai untuk peserta nyata |
| K-03 | Masa kode 15 menit/lima percobaan; sandi sementara 24 jam; sesi aktivasi 10 menit; sandi permanen minimum 12 karakter — setujui/ubah usulan | Pengelola dan keamanan sistem | Sebelum F04/F08 rilis; parameter disimpan config |
| K-04 | Jadwal/gelombang, periode publik, draf pascatutup, izin/deadline revisi dan kewenangan buka kembali | Pengelola PMB | Sebelum F05 UAT; tidak memakai tanggal contoh |
| K-05 | Field wajib PMB, NISN/KPS/NPWP/kebutuhan khusus, batas panjang/nilai, apakah unggahan dokumen benar-benar diperlukan | Pengelola akademik | Sebelum F09 final; scope unggahan baru memerlukan revisi rancangan |
| K-06 | Sumber/versi referensi wilayah, biodata, PT/prodi dan masa berlaku cache; data yang belum tersedia | Pengelola data/Neo | Sebelum submit produksi; dataset lokal sah dapat mendukung R1 |
| K-07 | Format/urutan nomor PMB, kapan diterbitkan; format/kewenangan NIM dan lingkup uniknya | PMB dan akademik | PMB sebelum F08; NIM sebelum F15. Usulan nomor PMB `PMB-{kode_periode}-{urut}` dengan sequence atomik |
| K-08 | Instance/kontrak Neo, endpoint readback, ID referensi tujuan, pembiayaan/biaya/SKS dan koreksi sesudah sinkron | Admin Neo | Sebelum R2 write; tidak menghalangi R1 |
| K-09 | Retensi, hak baca/ekspor, naskah consent, pencatatan IP, backup, penghapusan dan permintaan koreksi data | Pengelola data kampus | Sebelum go-live; jangan menyatakan kebijakan telah disahkan |
| K-10 | Engine/versi DB produksi, hosting/domain/HTTPS, storage, queue/lock store, penanggung jawab secret dan backup | Infrastruktur | Sebelum F01/F03 dikunci; menentukan uji integrasi/constraint |
| K-11 | Kolom ekspor, apakah operator boleh ekspor, format/keterangan bukti dan aset panduan/identitas resmi | Pengelola PMB | Sebelum F11 UAT; default operator ekspor nonaktif |
| K-12 | RPO/RTO, SLA klaim/verifikasi, penanggung jawab insiden, syarat pilot/rilis serta penerimaan akhir | Pengelola dan infrastruktur | Sebelum F13; gunakan target usulan hanya untuk perencanaan |

Format catatan keputusan:

```text
ID keputusan:
Tanggal (WIB):
Pemilik / pihak yang mengesahkan:
Keputusan dan alasan:
Nilai konfigurasi / versi yang berlaku:
Bukti atau rujukan internal:
Dokumen, tiket, dan tes yang harus diperbarui:
```

## 8. Register risiko

| Risiko | Peluang / dampak awal | Mitigasi dan pemilik |
| --- | --- | --- |
| SK hanya nama sehingga identitas ambigu | Tinggi / tinggi | Pengenal sah/SOP pembeda, triase dan tanpa auto-approve; pengelola |
| Dua klaim menguasai SK yang sama | Sedang / tinggi | Constraint, lock, transaksi, uji dua proses; backend |
| Scope operator hanya dibatasi UI | Sedang / tinggi | Policy/query/action/job dan uji IDOR; backend/QA |
| Salah kirim kredensial manual | Sedang / tinggi | Nomor terverifikasi, tampil sekali, expiry, revoke dan latihan SOP; operator |
| Dataset referensi/kontrak Neo belum tersedia | Tinggi / sedang R1, tinggi R2 | Dataset lokal sah R1, feature flag R2, contract test; pengelola data |
| Data dari pratinjau berubah sebelum commit | Sedang / tinggi | Hash/versi, lock periode, revalidasi; backend |
| Neo insert sukses tetapi jaringan timeout | Sedang / tinggi | Simpan checkpoint, perlu_verifikasi, readback, tanpa blind retry; integrasi |
| Kebocoran melalui log/ekspor/snapshot browser | Sedang / tinggi | Minimasi, enkripsi, allowlist, masking dan pengujian response; keamanan/QA |
| Backup tersedia tetapi kunci/file hilang | Sedang / tinggi | Latihan restore seluruh komponen, secret recovery; infrastruktur |
| Kebijakan tambahan memperbesar scope | Tinggi / sedang | Register keputusan, revisi tiket/estimasi, gate R1/R2; penanggung jawab proyek |

Peluang/dampak di atas penilaian awal rancangan, bukan hasil audit risiko institusi. Perbarui setelah pilot dengan bukti aktual.

## 9. Checklist siap membuka periode produksi

- [ ] SK resmi dan dataset referensi sah tersedia; tidak ada contoh yang dianggap resmi.
- [ ] Akun admin/operator aktif dan seluruh SK mempunyai penanggung jawab yang sah.
- [ ] Kebijakan klaim/WA/credential/jadwal/revisi/consent/retensi yang diperlukan telah disahkan.
- [ ] Peran/scope, konkurensi, draf/submit/revisi/accepted, bukti/ekspor telah lulus uji dan UAT.
- [ ] Google produksi, domain/HTTPS, file privat, queue/scheduler, secret, log dan monitoring berfungsi.
- [ ] Backup dan restore berhasil; pemilik operasional serta kanal insiden diketahui.
- [ ] Tidak ada blocker/temuan tinggi; risiko sisa mempunyai pemilik dan tindak lanjut.
- [ ] Tombol demo/reset/role switch/angka tetap tidak ada; Neo write nonaktif jika gate R2 belum lulus.
- [ ] Pengelola menyetujui rilis dan membuka periode melalui aksi tercatat.

Setelah dibuka, pembaruan status dokumentasi harus menyebut release, fitur yang benar-benar tersedia, bukti pengujian, dan keterbatasan yang masih ada. Jangan mengubah seluruh paket menjadi “selesai” hanya karena halaman telah tampil.
