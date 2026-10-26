# Koreksi kepemilikan pendaftaran

Tahap F08.5/F10.5, implementasi lokal 18 Oktober 2026. Tampilan memakai layout dan komponen Bootstrap yang sama dengan halaman pemeriksaan. SOP pembuktian identitas tetap memerlukan pengesahan pengelola kampus.

## Tujuan dan batas

Fitur dipakai ketika satu pendaftaran/SK sudah terikat pada akun yang keliru. Hanya administrator dapat memprosesnya. Ini berbeda dari penggantian nomor milik orang yang sama dan bukan penggabungan dua pendaftaran.

Nomor PMB, tanggal kirim pertama, snapshot konfigurasi awal, revisi lama dan consent lama dipertahankan. Biodata/draf akun lama tidak dipindahkan atau ditimpa. Akun tujuan memakai workspace pribadinya; jika belum ada, workspace dibuat dari nama/nomor permohonan tujuan. Identitas sensitif pada formulir lama tidak disalin.

## Tahapan admin

1. Buka **Antrean Verifikasi → Periksa → Koreksi Kepemilikan Akun**. Tinjau nomor PMB, periode, SK dan akun yang terikat.
2. Gunakan **Periksa / Tahan Kepesertaan SK** untuk menahan SK sementara dengan alasan. Proses koreksi menolak SK yang belum ditahan, SK ditarik dan periode arsip.
3. Pemilik sah mengajukan klaim pada periode tersebut. Akun yang sudah terdaftar harus aktif dan dapat membuktikan aksesnya. Pemohon tanpa akun dapat menggunakan klaim publik WhatsApp; petugas wajib mencatat bukti balasan dari nomor tersebut sebelum koreksi.
4. Pilih klaim akun tujuan dan buka pratinjau. Kandidat SK lain tidak dapat dipindahkan. Klaim yang belum mempunyai kandidat dapat dicocokkan admin ke SK pada pratinjau setelah identitas diperiksa; keputusan pencocokan tersimpan bersama koreksi.
5. Periksa identitas kedua pihak dan sumber SK melalui prosedur resmi. Catat alasan serta rujukan/waktu/hasil pemeriksaan tanpa sandi, kode akses, NIK lengkap atau percakapan sensitif. Konfirmasi seluruh checklist.
6. Konfirmasikan koreksi. Sistem mengunci periode dan data terkait, memeriksa versi aplikasi/SK/klaim/kedua draf serta konflik akun. Jika data berubah, muat ulang dan tinjau kembali; tidak ada pemaksaan penulisan versi lama.
7. Kasus administratif dan catatan perpindahan dibuat atomik. Klaim lama dibatalkan dengan catatan netral, klaim tujuan disetujui, penahanan SK dilepas, keputusan accepted dibatalkan dan status kembali draf/perbaikan sesuai riwayat pengiriman.
8. Semua sesi, remember token dan kredensial sementara kedua akun dicabut. Akun aktif masuk kembali memakai identitas pribadinya. Akun WhatsApp baru berstatus **menunggu aktivasi**: petugas membuka klaim tujuan, menerbitkan akses sekali tampil, menyerahkannya manual ke nomor tujuan, mencatat penyerahan, lalu pemilik membuat sandi pribadi.
9. Pemilik tujuan meninjau formulir miliknya dan mengirimkan dengan persetujuan terbaru. Jadwal pengiriman/perbaikan tetap berlaku; koreksi tidak memperpanjang deadline otomatis. Operator kemudian memeriksa revisi baru sebagaimana alur biasa.

## Pengamanan data dan bukti

- Akun lama kehilangan akses aplikasi dan bukti melalui scope `user_id`; workspace lamanya tetap pribadi. Akun lama tidak memperoleh biodata baru akun tujuan.
- Akun tujuan tidak memperoleh catatan pemeriksaan pemilik sebelumnya. Setiap review dan submission mempunyai `ownership_epoch`; halaman status pendaftar hanya memuat review pada kepemilikan saat ini.
- Detail staf tetap memperlihatkan histori yang diperlukan untuk pemeriksaan, dengan penanda submission pemilik saat ini/sebelumnya. Snapshot submission lama tetap terenkripsi dan tidak mempunyai endpoint unduhan untuk pendaftar.
- `current_owner_submitted_at` kosong setelah koreksi. Halaman/URL bukti menolak akses sampai pemilik saat ini mengirimkan formulir; sesudah itu bukti memakai biodata tujuannya dan menampilkan waktu pengiriman akun tersebut.
- Consent lama tetap menunjuk pemberi persetujuan semula; consent publik yang belum memiliki user tetap menjadi catatan persetujuan publik. Pengiriman akun tujuan membuat consent baru. Tidak ada pengatasnamaan consent lama.
- Bukti WhatsApp akun lama dibatalkan. Bukti klaim WhatsApp tujuan hanya ditautkan ke workspace tujuan jika nomornya sama. Format nomor saja tidak dianggap verifikasi.
- Audit umum hanya mencatat ID pihak/kasus dan urutan kepemilikan; alasan dan rujukan bukti berada pada kasus administratif yang hanya dapat dilihat admin.
- Koreksi yang sama tidak dapat diterapkan dua kali dengan versi lama. Dua admin bersamaan menghasilkan satu perpindahan; permintaan lainnya mendapat konflik.

## Kondisi yang ditolak

- Pelaksana operator atau pendaftar; akun tujuan staf/nonaktif/belum menyelesaikan kewajiban sandi.
- Klaim tujuan sudah final, berasal dari periode lain, menunjuk SK lain, atau tidak memiliki consent yang sesuai.
- Pemohon WhatsApp belum memberikan bukti balasan nomor; klaim tanpa akun yang bukan jalur WhatsApp.
- Akun tujuan sudah memiliki pendaftaran pada periode yang sama. Penyatuan dua aplikasi tidak dilakukan otomatis.
- Kasus administratif lain pada aplikasi/klaim terkait masih terbuka.
- Versi salah satu data berubah, kepesertaan belum ditahan atau sudah ditarik, atau periode diarsipkan.

## Skema dan pemulihan teknis

Migrasi `2026_10_18_081739_add_application_ownership_history` menambah `applications.ownership_epoch`, `current_owner_submitted_at`, penanda kepemilikan pada reviews/submissions serta tabel `application_ownership_transfers`. Seluruh rujukan histori memakai FK restrict. Data lama berada pada epoch 0; bukti lama yang belum mengalami koreksi tetap berfungsi tanpa backfill tanggal rekaan.

Jangan memperbaiki kepemilikan dengan SQL/FK manual atau `migrate:fresh`. Setelah catatan perpindahan terisi, `down()` migrasi menolak penghapusan batas privasi histori. Gunakan forward fix atau restore tervalidasi dengan kode/skema yang sesuai, bukan rollback yang menghilangkan pembatas catatan pemilik lama.

Tes fitur tersedia di `tests/Feature/Admissions/OwnershipTransferTest.php`; uji dua admin/proses berada di `tests/Concurrency/AdmissionsRaceTest.php`. Pengujian otomatis tidak menggantikan UAT identitas nyata, QA browser dan pengesahan SOP kampus.
