# Rancangan MVP aplikasi PMB PPG IAKN Palangka Raya

**Status:** Rancangan utama proyek baru; belum diimplementasikan.  
**Tujuan rilis pertama:** Memproses pendaftar yang namanya tercantum dalam SK PPG Bimas Kristen Kementerian Agama: aktivasi akses, pengisian biodata, verifikasi operator, perbaikan, dan keputusan **diterima lengkap**.  
**Sistem:** Aplikasi baru yang terpisah dari Feederdash. Kode Feederdash hanya acuan pemetaan data Neo Feeder; jangan memakai tabel `mahasiswa_exports` atau database produksinya sebagai penyimpanan PMB.  
**Zona waktu bisnis:** `Asia/Jakarta` untuk tanggal pendaftaran dan jadwal PMB. Simpan timestamp database dalam UTC bila memungkinkan.

## 1. Keputusan produk dan batas MVP

Daftar calon pendaftar **sudah ditentukan oleh SK**. Administrator mengimpor daftar itu dari Excel, menugaskan operator, lalu pendaftar mengklaim satu entri SK untuk mengisi formulir. Ada dua jalur akses pendaftar:

1. **Masuk/daftar dengan akun Google (Gmail) melalui Laravel Socialite.** Setelah callback Google berhasil, akun pendaftar aktif dan dapat langsung mengakses dashboard serta mengelola data pribadi PPG miliknya. Ia tetap harus dicocokkan dengan entri SK sebelum mengirim pendaftaran. Nama/email Google tidak otomatis membuktikan identitas orang dalam SK.
2. **Daftar dengan nama sesuai SK dan nomor WhatsApp tanpa Gmail.** Sistem menerima permohonan klaim, tetapi belum memberikan akses dashboard atau mengunci entri SK. Operator memeriksa kecocokan SK dan memastikan nomor WhatsApp aktif melalui balasan pesan/kode. Setelah klaim disetujui, operator mengirim kredensial sementara **secara manual melalui WhatsApp**. Pendaftar masuk dengan identitas login yang diberikan dan wajib mengganti kata sandi pada login pertama; sesudah aktivasi ia dapat memakai dashboard yang sama.

Nomor WhatsApp diwajibkan pada **kedua jalur** dan menjadi bagian formulir. Keaktifan WhatsApp adalah status verifikasi tersendiri, bukan salah satu dari enam status pendaftaran. Perubahan nomor membatalkan verifikasi lama sampai nomor baru dibuktikan aktif. Tidak ada pengiriman otomatis melalui WhatsApp pada MVP.

Dokumen ini memakai **tiga peran** karena kebutuhan menyebut tiga jenis pengguna: `pendaftar_ppg`, `operator`, dan `administrator`. Administrator memiliki seluruh kemampuan operator dalam akun yang sama dan dapat membuka tampilan/antrean operator; **tidak perlu menyamar sebagai akun operator lain**. Hanya administrator yang dapat memulai, mengulang, atau mengatur sinkronisasi ke Neo Feeder.

**Termasuk MVP:** impor SK dan penugasan, kedua jalur akses, pemeriksaan WhatsApp, dashboard pendaftar, formulir/draf, kirim dan perbaikan, antrean verifikasi per operator, keputusan diterima lengkap, serta audit dasar. **Tahap setelah MVP:** pengiriman langsung ke Neo Feeder. Skema dan pemetaan data disiapkan sejak awal; jika koneksi Neo sudah siap pada rilis pertama, fitur itu tetap hanya tersedia bagi administrator. Pembayaran, seleksi, KRS, nilai, kelas, dan pengelolaan perkuliahan berada di luar cakupan.

## 2. Alur ujung ke ujung

1. Administrator membuat periode/gelombang, mencatat nomor dan tanggal SK, mengimpor daftar nama resmi, membuat empat akun operator, lalu menjalankan pembagian otomatis atau memakai penugasan dari Excel. Daftar SK diperiksa sebelum periode dibuka.
2. Pendaftar memilih jalur Google atau WhatsApp. Sistem menerima permohonan klaim dan mencocokkannya dengan **satu** baris SK yang belum diklaim. Jika nama sama lebih dari sekali atau berbeda ejaan, operator menyelesaikan pencocokan; aplikasi tidak memilih berdasarkan kemiripan nama saja. Permohonan yang belum disetujui tidak mengunci baris SK.
3. Pada jalur Google, pendaftar langsung masuk ke dashboard setelah autentikasi. Ia mengisi nomor WhatsApp dan formulir. Pada jalur WhatsApp, klaim awal berisi nama SK dan nomor; operator menghubungi nomor itu, menerima balasan/kode, lalu menyerahkan kredensial sementara. Setelah login pertama dan ganti sandi, pendaftar mengisi formulir.
4. Pendaftar menyimpan draf, meninjau ringkasan, menyetujui pernyataan kebenaran data, lalu menekan **Kirim Pendaftaran**. Server menetapkan `submitted_at` dan `tanggal_daftar` hanya pada kirim pertama.
5. Operator yang ditugaskan melihat pendaftaran tersebut, memeriksa identitas, SK, WhatsApp, biodata, dan pendidikan asal. Operator memindahkan status ke `diverifikasi`, atau meminta `perbaikan` dengan catatan spesifik.
6. Setelah data lengkap dan valid, operator menetapkan `diterima_lengkap`. Jika perlu Neo Feeder, administrator meninjau kesiapan dan menjalankan sinkronisasi terpisah. Keberhasilan Neo tidak mengubah keputusan PMB.

Pendaftar Gmail boleh memiliki akun dan dashboard sebelum klaim SK disetujui, tetapi hanya dapat membuat/mengirim **satu pendaftaran yang terkait entri SK sah** pada periode yang berlaku. Pendaftar tanpa Gmail yang belum menerima kredensial hanya mendapat tanda bahwa klaimnya diterima; jangan tampilkan data orang lain atau nomor WhatsApp lengkap pada halaman publik.

## 3. Peran dan matriks hak akses

| Kemampuan | Pendaftar PPG | Operator | Administrator |
| --- | --- | --- | --- |
| Masuk dengan Google | Ya | Ya, setelah akun operator diprovisikan | Ya, setelah akun admin diprovisikan |
| Masuk dengan kredensial lokal sementara/permanen | Ya untuk jalur WhatsApp | Opsional bila disetujui admin | Opsional untuk pemulihan akun |
| Membaca/mengubah data pendaftaran | Milik sendiri dan sesuai status | Hanya entri yang ditugaskan | Semua entri |
| Verifikasi WhatsApp, klaim SK, dan formulir | Melihat status sendiri | Untuk entri yang ditugaskan | Semua entri |
| Mengubah status `diverifikasi`, `perbaikan`, `diterima_lengkap` | Tidak | Untuk entri yang ditugaskan | Ya melalui fungsi operator |
| Impor SK, bagi tugas, pindah operator, kelola akun/periode | Tidak | Tidak | Ya |
| Pratinjau, kirim, retry, dan konfigurasi Neo | Tidak | Tidak | Ya |
| Audit dan ekspor massal | Tidak | Terbatas pada tugasnya bila diaktifkan | Ya |

Hak akses dicek pada server untuk setiap aksi dan setiap baris. Akun Google tidak otomatis menjadi operator atau administrator walaupun alamatnya ada dalam domain tertentu; administrator harus membuat/menyetujui peran terlebih dahulu dan mengikatnya ke Google ID yang benar. Administrator tidak mengubah identitas aktor ketika membuka tampilan operator; audit tetap menuliskan akun administrator tersebut.

## 4. Identitas, klaim SK, dan autentikasi

### 4.1 Impor daftar SK sebagai sumber peserta

Satu baris `sk_participants` mewakili satu calon peserta resmi dalam satu SK/periode. Simpan nomor SK, tanggal SK, nomor urut/baris, nama persis dari SK, nama normalisasi untuk pencarian, dan pengenal tambahan **jika memang tersedia pada SK/sumber resmi** (misalnya nomor peserta). Jangan mengarang NIK dari nama. Nama resmi SK tidak boleh diam-diam ditimpa oleh nama akun Google; biodata pendaftar menyimpan nama isian tersendiri, sementara operator memutuskan bila ada perbedaan.

Pendaftar tidak boleh melihat atau memilih seluruh daftar nama SK secara terbuka. Ia memasukkan nama sesuai SK dan, bila tersedia, pengenal SK tambahan. Pencarian server mengembalikan hasil terbatas. Permohonan klaim yang tidak cocok, ambigu, atau sudah diklaim ditahan untuk pemeriksaan administrator/operator; jangan membuat baris SK baru dari input publik. Simpan permohonan di `sk_claims` tanpa memesan baris SK. Sesudah identitas diverifikasi, operator menyetujui klaim dalam transaksi yang mengunci baris SK, memastikan belum ada klaim/aplikasi sah, lalu membuat aplikasi `draf`. Klaim ganda oleh dua akun tidak boleh menghasilkan dua pendaftaran. Pemindahan klaim sah ke akun lain hanya oleh administrator dengan alasan audit.

### 4.2 Jalur Google/Gmail

Gunakan Laravel Socialite dengan provider Google. Callback harus memeriksa identitas dari Google, menyimpan `provider=google` dan ID pengguna Google yang unik, lalu menghubungkan ke akun lokal. Email Google disimpan sebagai kontak, tetapi kunci hubungan berikutnya adalah **provider ID**, bukan nama atau email yang dapat berubah. Hanya akun Google dengan email yang tersedia dan sesuai kebijakan aplikasi yang boleh melanjutkan; jika data callback tidak memadai, tampilkan masalah yang dapat ditindaklanjuti. Jangan membuat akun baru tiap kali pengguna masuk.

Pendaftar Google segera mendapat sesi dan dashboard pribadi. Operator/admin Google hanya mendapat peran yang sudah diprovisikan. Jangan mengaitkan otomatis akun Google baru ke akun jalur WhatsApp berdasarkan nama atau nomor saja; penggabungan akun memerlukan verifikasi kepemilikan kedua akun dan keputusan administrator, dengan jejak audit. Token akses Google tidak perlu disimpan bila aplikasi hanya memakai autentikasi dan profil dasar.

### 4.3 Jalur WhatsApp tanpa Gmail

Form klaim awal meminta **nama persis menurut SK** dan nomor WhatsApp. Server menormalisasi nomor ke bentuk internasional yang konsisten (misalnya `+62...`), menolak format jelas tidak sah, dan memeriksa benturan nomor/entri SK. Pemeriksaan format **tidak** membuktikan nomor aktif.

Prosedur verifikasi MVP: operator yang ditugaskan mengirim pesan ke nomor tersebut melalui WhatsApp resmi kampus, meminta calon pendaftar membalas kode pendek sekali pakai atau informasi konfirmasi yang aman, lalu mencatat `whatsapp_verified_at`, nomor yang diverifikasi, operator, dan bukti ringkas waktu balasan. Kode kedaluwarsa, dibatasi percobaannya, dan tidak ditulis di log umum. Jangan meminta pendaftar mengirim NIK lengkap atau data sensitif lewat chat. Jika tidak ada balasan atau nomor salah, akun belum diaktifkan dan operator meminta pembetulan nomor melalui prosedur yang tercatat.

Setelah nomor aktif dan klaim SK disetujui, aplikasi membuat `login_id` unik yang tidak bergantung pada nomor telepon serta sandi sementara acak, sekali pakai, dan berumur pendek. Operator mengirim `login_id`, sandi sementara, dan alamat situs **secara manual** ke percakapan WhatsApp yang telah diverifikasi. Aplikasi menyimpan hanya hash sandi, mencatat waktu/aktor penyerahan, membatasi percobaan login, memaksa ganti sandi pada login pertama, dan membatalkan sandi sementara setelah dipakai/kedaluwarsa. Bila pesan salah kirim atau nomor berubah, administrator mencabut kredensial dan mengulangi verifikasi. Jangan menampilkan ulang sandi sementara setelah halaman penyerahan ditutup.

Pendaftar yang kemudian memiliki Gmail dapat meminta pengaitan Google ke akun yang sama setelah masuk dan membuktikan kepemilikan akun; pendaftaran dan tugas operator tetap satu. Prosedur pemulihan akun WhatsApp memakai verifikasi ulang nomor oleh operator/admin, bukan penebakan nama SK.

### 4.4 Nomor WhatsApp pada kedua jalur

Kolom `whatsapp_number` wajib pada formulir semua pendaftar. Simpan nomor ternormalisasi serta nilai tampilan; jangan simpan sebagai integer. Untuk pengguna Google, operator juga meminta balasan/kode ke nomor tersebut sebelum menandai verifikasi lengkap. Pendaftar dapat mengirim formulir setelah nomor diisi, tetapi status `diterima_lengkap` **mensyaratkan** `whatsapp_verified_at` untuk nomor yang masih berlaku. Jika nomor diganti, kosongkan bukti verifikasi dan tampilkan pekerjaan baru pada antrean operator. Satu nomor dipakai beberapa pendaftar harus ditandai sebagai potensi konflik untuk ditinjau, bukan otomatis dianggap identitas yang sama.

## 5. Impor Excel SK dan pembagian otomatis operator

Administrator mengunduh template `.xlsx` dengan sheet `Peserta_SK` dan `Panduan`. Kolom minimum: `nomor_sk`, `tanggal_sk`, `periode`, `nomor_urut_sk`, `nama_sesuai_sk`. Kolom opsional: `id_peserta_sk` jika ada pada sumber resmi, `operator_email` untuk penugasan eksplisit, dan catatan internal yang tidak tampil kepada pendaftar. Daftar operator dapat dikelola pada sheet terpisah atau sebelumnya pada panel admin. Jangan memasukkan nomor WhatsApp rekaan bila SK hanya memuat nama; nomor datang dari pendaftar.

Alur impor: unggah → pratinjau jumlah baru/identik/berubah/konflik serta kesalahan per baris → konfirmasi administrator → simpan dalam transaksi. Validasi file `.xlsx`, header, periode, nomor SK, tanggal, nama kosong, duplikasi kunci peserta, operator yang tidak aktif, dan pengenal yang bertabrakan. Baris tanpa pengenal resmi memakai kombinasi periode + nomor SK + nomor urut sebagai kunci lokal; nama bukan kunci unik. Satu file dengan kesalahan tidak boleh menyimpan sebagian baris. Impor ulang identik tidak menambah peserta atau mereset klaim, status, dan penugasan. Perubahan nama resmi atau penarikan peserta yang sudah diklaim harus menjadi konflik untuk keputusan administrator, bukan pembaruan diam-diam.

**Mode pembagian MVP:** bila `operator_email` terisi pada baris, tugaskan ke operator itu; bila kosong, administrator memilih operator aktif untuk periode lalu menekan **Bagi Otomatis**. Sistem menghitung peserta yang belum ditugaskan, mengurutkan secara stabil berdasarkan nomor SK, nomor urut, lalu ID lokal, dan membaginya merata dengan metode round-robin. Untuk **400 pendaftar dan 4 operator**, masing-masing mendapat **100 entri SK**. Jika jumlah tidak habis dibagi, selisih beban maksimum satu; untuk 403 dan 4 operator hasilnya 101, 101, 101, 100. Pembagian dilakukan **berdasarkan seluruh peserta SK**, termasuk yang belum membuat akun, supaya antrean operator sudah jelas dari awal.

Penugasan disimpan pada `sk_participants.assigned_operator_id` dan diwarisi oleh pendaftaran yang mengklaim baris itu. Jalankan pembagian dalam transaksi dengan kunci yang mencegah dua admin membagi bersamaan. Impor ulang atau perubahan jumlah operator **tidak membagi ulang** entri yang sudah ditugaskan. Admin dapat menjalankan pembagian hanya untuk entri baru/belum ditugaskan, atau melakukan **rebalancing eksplisit** dengan pratinjau perpindahan, alasan, dan audit. Entri yang sudah diproses operator tidak dipindahkan otomatis. Bila operator dinonaktifkan, admin memindahkan sisa tugasnya dan mencatat riwayat penugasan. Semua hitungan dashboard menjelaskan apakah menghitung peserta SK, klaim, formulir terkirim, atau keputusan.

Operator hanya melihat peserta yang ditugaskan, termasuk entri SK yang belum mendaftar. Administrator melihat semua dan dapat menyaring berdasarkan operator, status, sumber jalur akses, serta kebutuhan tindak lanjut. Tidak ada operator yang bisa mengambil peserta operator lain hanya dengan mengganti ID pada URL.

## 6. Status pendaftaran yang berlaku

Status utama **hanya**: `belum_ada`, `draf`, `terkirim`, `diverifikasi`, `perbaikan`, `diterima_lengkap`. `belum_ada` adalah keadaan entri SK yang belum mempunyai `applications`; ia boleh dihitung pada daftar SK tanpa membuat baris aplikasi kosong. Status WhatsApp (`belum_diperiksa`, `terverifikasi`, `perlu_verifikasi_ulang`), akses akun, dan sinkron Neo adalah dimensi terpisah.

| Dari | Aksi dan aktor | Ke | Syarat/efek |
| --- | --- | --- | --- |
| `belum_ada` | Klaim SK disetujui operator | `draf` | Buat aplikasi tunggal untuk baris SK/periode setelah identitas diperiksa. Jalur WhatsApp boleh memiliki draf sebelum akun aktif, tetapi belum dapat mengeditnya sampai kredensial diterima. |
| `draf` | Kirim oleh pendaftar | `terkirim` | Semua field submit valid, persetujuan dicatat, `submitted_at` pertama dan `tanggal_daftar` ditetapkan server. |
| `terkirim` | Lolos pemeriksaan awal oleh operator | `diverifikasi` | Operator mencatat pemeriksaan identitas/SK/data dan waktu. WhatsApp yang belum terverifikasi tetap menjadi pekerjaan terbuka. |
| `terkirim`/`diverifikasi` | Minta koreksi oleh operator | `perbaikan` | Catatan spesifik wajib, pendaftar mendapat daftar field yang perlu diperbaiki. |
| `perbaikan` | Kirim ulang oleh pendaftar | `terkirim` | Validasi ulang; tanggal daftar pertama tetap, `last_resubmitted_at` diperbarui. |
| `diverifikasi` | Selesaikan oleh operator | `diterima_lengkap` | Semua field wajib lengkap, klaim SK sah, WhatsApp nomor saat ini terverifikasi, konflik selesai, dan keputusan dicatat. |

Tidak ada status `ditolak` pada MVP ini. Jika klaim keliru atau peserta tidak memenuhi syarat, administrator menandainya sebagai **pengecualian administratif** dengan alasan dan menahan akses/perubahan sesuai SOP; penambahan status penolakan menunggu keputusan kebijakan kampus. Jangan menyalahgunakan `perbaikan` untuk kasus identitas yang terbukti bukan peserta SK. Setelah `diterima_lengkap`, pendaftar dapat membaca datanya; koreksi berikutnya hanya lewat prosedur admin/operator yang diaudit, terutama bila data sudah dikirim ke Neo.

Transisi status memakai pemeriksaan versi baris agar dua operator tidak saling menimpa. Catat aktor, status lama/baru, alasan, waktu, dan perubahan data. Operator/admin tidak mengubah `submitted_at` atau `tanggal_daftar` saat verifikasi atau perbaikan.

## 7. Formulir dan aturan data

Form bertahap dan dapat disimpan sebagai draf. Nama tampilan harus mudah dipahami; kode teknis referensi dipilih melalui daftar, bukan diketik bebas.

| Tahap | Isi | Aturan MVP |
| --- | --- | --- |
| Identitas & kontak | Nama sesuai SK (read-only dari roster), nama biodata bila perlu, jenis kelamin, tempat/tanggal lahir, NIK, NISN, agama, kewarganegaraan, nama ibu, **nomor WhatsApp**, telepon lain/email bila ada | NIK teks 16 digit; NISN/nomor teks agar nol awal utuh; tanggal lahir `YYYY-MM-DD`; perbedaan nama SK dan biodata ditandai untuk operator. Email tidak wajib bagi jalur WhatsApp. |
| Alamat | Jalan, dusun, RT/RW, kelurahan, kode pos, wilayah, jenis tinggal, alat transportasi | Kode wilayah dan pilihan lainnya berasal dari referensi yang dipelihara aplikasi. |
| Tambahan | Penerima KPS, nomor KPS bila berlaku, NPWP, kebutuhan khusus bila relevan | Jangan mengisi nilai palsu untuk field kosong. |
| Pendidikan asal | PT asal dan prodi asal | Cari dari cache `GetAllPT`/`GetAllProdi`; prodi harus berada di PT yang dipilih. Simpan ID dan snapshot nama dari server. |
| Tinjau & kirim | Ringkasan, pernyataan kebenaran data, pemberitahuan pemrosesan data | Simpan versi naskah persetujuan, waktu, identitas akun, dan hasil validasi. |

`tanggal_daftar` adalah tanggal Jakarta dari submit pertama, bukan tanggal impor SK, akun dibuat, verifikasi WhatsApp, keputusan operator, atau pengiriman Neo. Selama `draf`, tanggal ini kosong. Form tidak meminta NIM, UUID Neo, jenis/jalur daftar, periode masuk, pembiayaan, biaya masuk, atau prodi tujuan; nilai itu berasal dari operator/admin dan konfigurasi periode.

Pada `draf`, field dapat disimpan sebagian. Pada `terkirim`, semua field yang diwajibkan kebijakan PMB harus valid. Pada `diterima_lengkap`, tambah syarat WhatsApp aktif, klaim SK sah, tidak ada konflik, serta seluruh data yang dibutuhkan PMB lengkap. Kesiapan Neo diperiksa terpisah, sehingga label **diterima lengkap** tidak berarti **sudah tersinkron ke Neo**.

## 8. Model data MVP

| Tabel | Kolom penting | Aturan |
| --- | --- | --- |
| `users` | `id`, `role`, `name`, `email` nullable, `login_id` nullable, `password` nullable, `must_change_password`, `is_active`, waktu login | Peran tepat tiga nilai. Email/Google untuk akun Google; `login_id` dan hash sandi untuk jalur WhatsApp. |
| `social_accounts` | `user_id`, `provider`, `provider_user_id`, `provider_email`, waktu hubungan | Unik `(provider, provider_user_id)`; satu akun lokal dapat dihubungkan kemudian secara terkendali. |
| `admission_periods` | `id`, `code`, `opens_at`, `closes_at`, `status`, `config_version`, nilai baku Neo | Periode/gelombang serta konfigurasi versi. |
| `sk_imports` | `id`, periode, nomor/tanggal SK, nama file, hash file, jumlah baris, hasil, pengunggah, waktu | Jejak impor tanpa menyimpan file publik. |
| `sk_participants` | `id`, periode, nomor/tanggal SK, nomor urut, pengenal resmi bila ada, `name_on_sk`, nama normalisasi, `assigned_operator_id`, `claimed_user_id` nullable | Kunci unik periode + SK + nomor urut; satu klaim aktif; nama bukan kunci unik. |
| `sk_claims` | `id`, periode, baris SK kandidat, akun pemohon bila ada, nama yang dikirim, WhatsApp, jalur, status pemeriksaan, operator, waktu | Permohonan belum mengunci SK; batasi permohonan berulang dan audit keputusan. |
| `assignment_events` | `sk_participant_id`, operator lama/baru, alasan, aktor, waktu | Riwayat penugasan dan rebalancing. |
| `applications` | `id`, `application_number`, `sk_participant_id`, `user_id`, `status`, `submitted_at`, `tanggal_daftar`, `last_resubmitted_at`, `config_snapshot`, `consent_version`, `lock_version`, `nim` nullable | Satu aplikasi per peserta SK/periode; nomor pendaftaran berbeda dari NIM. |
| `applicant_biodata` | `application_id`, seluruh field biodata, `whatsapp_number`, `whatsapp_verified_number`, `whatsapp_verified_at`, `whatsapp_verified_by` | Satu biodata per aplikasi; nomor/tanggal/kode memakai tipe yang tepat. |
| `origin_education` | `application_id`, ID/nama PT asal dan prodi asal | Satu pasangan asal untuk riwayat pendidikan PPG. |
| `application_reviews` | aplikasi, aktor, hasil pemeriksaan, catatan perbaikan, waktu | Catatan yang ditampilkan ke pendaftar ditandai eksplisit. |
| `reference_pt`, `reference_prodi`, `reference_sync_runs` | ID Neo, kode, nama, hubungan PT, status, waktu sinkron | Cache dan jejak sinkron referensi. |
| `neo_syncs`, `neo_sync_attempts` | aplikasi, ID Neo, status biodata/riwayat, hasil percobaan, waktu verifikasi | Dibuat/diaktifkan saat tahap Neo; status terpisah dari PMB. |
| `audit_events`, `consent_records` | aktor, tindakan, entitas, ringkasan perubahan, naskah/versi persetujuan, waktu | Jangan menyimpan token, sandi, kode WhatsApp, atau NIK penuh pada log umum. |

Indeks minimum: `users.email` unik bila terisi, `users.login_id` unik bila terisi, `(provider, provider_user_id)` unik, `(periode, nomor_sk, nomor_urut_sk)` unik, `sk_participants.claimed_user_id` diindeks, `applications.sk_participant_id` unik, `(user_id, admission_period_id)` unik, `applications.application_number` unik, dan `applications.nim` unik bila terisi. Jika database tidak mendukung unique nullable sesuai kebutuhan, terapkan constraint yang setara. Simpan nomor WhatsApp dalam format konsisten dan indeks untuk pencarian konflik, tetapi jangan menjadikannya kunci identitas tunggal.

## 9. Antarmuka MVP

**Publik:** informasi periode, tombol **Masuk dengan Google**, pilihan **Daftar tanpa Gmail lewat WhatsApp**, form klaim SK terbatas, dan panduan bahwa operator mengirim kredensial secara manual setelah nomor terverifikasi.

**Pendaftar:** dashboard pribadi berisi nomor pendaftaran, nama SK, operator/kanal bantuan kampus bila boleh ditampilkan, status utama, status verifikasi WhatsApp, progres formulir, daftar perbaikan, tanggal kirim, dan ringkasan data. Pendaftar Google dapat langsung masuk; pendaftar WhatsApp melihat dashboard setelah aktivasi kredensial. Form tidak menampilkan data pendaftar lain.

**Operator:** antrean hanya miliknya; tab entri SK belum mengklaim, klaim WhatsApp yang harus dihubungi, draf, terkirim, diverifikasi, perbaikan, diterima lengkap; detail pendaftar; tombol catat verifikasi WhatsApp, kirim kredensial secara manual/catat penyerahan, minta perbaikan, dan tetapkan keputusan. Operator melihat jumlah beban dan progres, tetapi tidak melihat tombol Neo.

**Administrator:** semua tampilan operator ditambah impor Excel, pratinjau dan pembagian operator, perpindahan tugas, pengelolaan akun, periode, konfigurasi, audit, dan halaman Neo. Panel admin menunjukkan jumlah total SK, belum diklaim, per status, per operator, serta konflik. Semua tabel memiliki filter dan paginasi server agar sekitar 400 peserta tetap nyaman digunakan.

## 10. Konfigurasi Neo dan batas akses

Nilai berikut adalah **nilai awal dari rancangan sebelumnya**, belum boleh dianggap benar untuk koneksi Neo aktif. Administrator memverifikasi dan mengunci versi konfigurasi sebelum sinkronisasi produksi.

| Field | Nilai awal | Verifikasi |
| --- | --- | --- |
| `id_jenis_daftar` | `18` | `GetJenisPendaftaran` |
| `id_jalur_daftar` | `11` | `GetJalurMasuk` |
| `id_periode_masuk` | `20261` | `GetSemester` dan periode yang dibuka |
| `id_perguruan_tinggi` | `d7435035-8e84-4ca3-913c-4e0447fbbf90` | Profil PT IAKN pada Neo tujuan |
| `id_prodi` | `cca1be5f-de07-45df-9980-585a07f01bc9` | Prodi tujuan dan status aktif |
| `id_bidang_minat` | `null` | Kontrak endpoint/kebijakan PPG |
| `sks_diakui` | `0` | Tipe numerik kontrak aktif |
| `id_pembiayaan` | `3` sebagai maksud Beasiswa Penuh | **Wajib cek `GetPembiayaan`**; koleksi Postman historis menyebut UUID, jadi jangan mengirim `3` sebelum terbukti benar. |
| `biaya_masuk` | `0.00` | Tipe desimal kontrak aktif |

Konfigurasi dibekukan sebagai snapshot saat submit pertama. Perubahan setelahnya tidak mengubah aplikasi yang sudah terkirim. Referensi PT/prodi asal diambil server-ke-server dari `GetAllPT`/`GetAllProdi`, semua halaman disinkronkan ke cache lokal, dan pilihan prodi disaring berdasarkan PT. Run parsial tidak menimpa seluruh cache. Bila data referensi tidak tersedia, blok submit yang membutuhkan pilihan itu dan tampilkan alasan yang jelas.

### Pemetaan inti `InsertRiwayatPendidikanMahasiswa`

| Field Neo | Sumber |
| --- | --- |
| `id_mahasiswa` | Hasil `InsertBiodataMahasiswa` atau pencarian identitas Neo yang diverifikasi. |
| `nim` | NIM resmi yang ditetapkan administrator/pejabat yang diberi kewenangan; bukan nomor pendaftaran. |
| `id_jenis_daftar`, `id_jalur_daftar`, `id_periode_masuk`, `id_perguruan_tinggi`, `id_prodi`, `id_bidang_minat`, `sks_diakui`, `id_pembiayaan`, `biaya_masuk` | Snapshot konfigurasi periode. |
| `tanggal_daftar` | Tanggal Jakarta dari submit pertama, diserialisasi sesuai kontrak Neo aktif. |
| `id_perguruan_tinggi_asal`, `nama_perguruan_tinggi_asal`, `id_prodi_asal`, `nama_prodi_asal` | Pilihan dan snapshot referensi asal yang tervalidasi. Nama field prodi asal harus diuji pada endpoint aktif. |

Payload biodata memakai whitelist field dari Feederdash: nama, jenis kelamin, tempat/tanggal lahir, agama, NIK, NISN, kewarganegaraan, alamat/wilayah, kontak, nama ibu, KPS, NPWP, dan kebutuhan khusus yang didukung Neo. Nomor WhatsApp dapat dipetakan ke `handphone` setelah dipastikan format yang diterima Neo. Jangan kirim `role`, catatan SK, penugasan operator, hash sandi, atau field internal aplikasi.

**Hak Neo:** hanya administrator melihat pratinjau rinci, memulai sinkron, memverifikasi ulang, dan retry. Operator hanya dapat melihat ringkasan kesiapan bila perlu untuk perbaikan data, tanpa endpoint tulis maupun token. Pengiriman harus memakai lock per aplikasi, mengecek riwayat lebih dulu, mencari biodata yang cocok, menyisipkan biodata bila belum ada, lalu menyisipkan riwayat. Simpan `id_mahasiswa` setelah tahap biodata sukses. Jika tahap riwayat gagal, retry hanya tahap yang perlu setelah mengecek Neo. Respons ambigu/koneksi putus menjadi `perlu_verifikasi`; jangan insert ulang sebelum pembacaan balik. Status `tersinkron` hanya setelah riwayat cocok ditemukan kembali di Neo. Tokennya disimpan sebagai secret server.

## 11. Keamanan dan operasi MVP

- Pakai HTTPS, proteksi CSRF, sesi aman, rate limit untuk login/klaim SK/pencarian roster, dan otorisasi kepemilikan serta penugasan di server.
- Simpan sandi dengan hash; sandi sementara memiliki masa berlaku dan harus diganti. Catat penyerahan WhatsApp tanpa menyimpan isi chat lengkap. Operator tidak boleh membuka sandi akun yang sudah aktif.
- Batasi pengungkapan daftar SK kepada publik. Nama sama, nomor SK, dan status klaim tidak boleh memungkinkan orang menebak data peserta lain.
- NIK, tanggal lahir, nama ibu, WhatsApp, dan data SK adalah data pribadi. Mask pada daftar, batasi ekspor, enkripsi backup, dan tetapkan retensi bersama kampus. Audit menyimpan identitas aktor dan perubahan penting tanpa token/NIK penuh.
- Simpan draf server-side dan tampilkan waktu simpan terakhir. Jika koneksi terputus saat submit, pendaftar dapat memuat ulang tanpa menciptakan aplikasi kedua atau mengubah tanggal daftar pertama.
- Siapkan backup dan uji restore sebelum go-live. Pantau email/Google callback, klaim tertunda, WhatsApp belum diverifikasi, impor gagal, dan antrean per operator. Kegagalan Neo tidak boleh memblokir penggunaan MVP PMB.

## 12. Urutan pembangunan dan kriteria penerimaan

**Rilis MVP:** (1) fondasi akun Google dan akun WhatsApp, (2) impor SK dan pembagian operator, (3) klaim SK serta formulir, (4) verifikasi WhatsApp dan kredensial manual, (5) alur status dan dashboard, (6) audit, backup, serta pengujian akses. Aktivasi Neo ditunda sampai kontrak dan referensinya diuji; bila selesai lebih cepat, modul Neo dapat dirilis terpisah tanpa mengubah alur PMB.

Skenario minimum yang harus lulus:

1. Impor 400 peserta dari SK dan bagi ke empat operator menghasilkan 100 peserta per operator; impor ulang identik tidak menggandakan atau membagi ulang.
2. Pengguna Google langsung mendapat akun/dashboard, tetapi tidak dapat mengirim pendaftaran untuk nama yang tidak berhasil dikaitkan ke satu entri SK.
3. Pendaftar WhatsApp tanpa Gmail tidak mendapat akses dashboard sampai nomor aktif dibuktikan melalui balasan, klaim disetujui, dan kredensial sementara diserahkan; login pertama memaksa ganti sandi.
4. Nomor WhatsApp salah/tidak aktif tidak dapat ditandai terverifikasi. Perubahan nomor menghapus status verifikasi sebelumnya. `diterima_lengkap` ditolak sampai nomor saat ini terverifikasi.
5. Satu entri SK tidak dapat diklaim dua akun; nama kembar menghasilkan penanganan konflik, bukan pencocokan otomatis. Pendaftar/operator tidak dapat membaca entri di luar haknya.
6. Alur `belum_ada` → `draf` → `terkirim` → `diverifikasi` → `perbaikan` → `terkirim` → `diverifikasi` → `diterima_lengkap` bekerja; tanggal daftar pertama tidak berubah saat revisi.
7. Pendaftar hanya mengubah draf/perbaikan sendiri; dua operator tidak dapat menimpa keputusan bersamaan. Administrator dapat membuka tampilan operator dengan identitas admin tetap tercatat.
8. Operator tidak dapat mengakses aksi/route/job Neo walau mengirim request langsung. Administrator dapat menjalankan pratinjau; pengiriman baru tersedia setelah referensi dan kontrak diverifikasi.
9. NIK/NISN/nomor dengan nol awal, tanggal lahir, pasangan PT-prodi, dan snapshot konfigurasi disimpan serta divalidasi benar.
10. Jika Neo diaktifkan: kegagalan riwayat tidak mengulang biodata, respons ambigu diperiksa ulang, dan status sukses memerlukan baca balik riwayat.

## 13. Keputusan yang harus diisi pengelola proyek

| Topik | Keputusan yang diperlukan sebelum fitur terkait dipakai |
| --- | --- |
| Sumber SK | Nomor/tanggal SK, format data resmi, apakah ada pengenal selain nama, serta prosedur bila peserta dicabut/ditambah. |
| Klaim nama kembar | Data pembeda yang sah dan siapa yang menyetujui pencocokan. |
| WhatsApp resmi | Nomor/perangkat operator, format pesan konfirmasi, masa berlaku kode, serta SOP salah kirim dan pergantian nomor. |
| Kredensial manual | Masa berlaku sandi sementara, penanggung jawab penyerahan, dan alur pemulihan akun. |
| Periode PMB | Waktu buka/tutup dan apakah perbaikan boleh sesudah penutupan. |
| NIM | Format, waktu penetapan, dan pejabat berwenang; jangan samakan dengan nomor pendaftaran. |
| Field wajib | Kebutuhan PMB dan kontrak Neo aktif, termasuk NISN, KPS, NPWP, serta pilihan referensi biodata. |
| Neo | ID jenis/jalur/periode/PT/prodi/pembiayaan, format tanggal, nama field asal prodi, dan endpoint baca balik yang diuji. |
| Data pribadi | Retensi, hak ekspor, backup, prosedur koreksi, dan penghapusan menurut kebijakan kampus. |

Keputusan tersebut dicatat dengan tanggal, pemilik, dan bukti di proyek baru. Pengembang dapat mengerjakan bagian MVP yang tidak bergantung pada keputusan tersebut, tetapi tidak mengisi nilai kebijakan dengan tebakan.

## Acuan saat dokumen dipindahkan ke proyek baru

- `app/Models/MahasiswaExport.php` dan migrasi `database/migrations/2025_10_09_103621_create_mahasiswa_exports_table.php` pada Feederdash: daftar field biodata/riwayat historis.
- `app/Livewire/Export/ExportMahasiswa.php`: whitelist `biodataPayload()` dan `riwayatPayload()` serta urutan dua aksi Neo. Ini acuan, bukan kode runtime PMB.
- `docs/Restful Neo Feeder 2022.postman_collection.json`: contoh kontrak historis yang **harus** diverifikasi ulang; jangan menyalin token contoh dari koleksi itu.
- [Dokumentasi resmi Laravel Socialite](https://laravel.com/framework/docs/socialite): provider Google, redirect/callback, dan identitas provider. Cocokkan versi dokumentasi dengan versi Laravel proyek baru.

Ketika dokumen ini dipindahkan, sertakan keputusan yang telah disahkan, contoh template Excel tanpa identitas nyata, migrasi skema aktual, `.env.example` tanpa secret, dan catatan uji Neo yang telah disanitasi. Perbarui status dokumen hanya setelah fitur dalam cakupan benar-benar tersedia dan lulus uji.
