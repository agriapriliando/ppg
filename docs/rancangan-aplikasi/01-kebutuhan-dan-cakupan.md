# 01 — Kebutuhan, cakupan, dan hak akses

## 1. Hasil yang dituju

Pengelola dapat menelusuri setiap peserta dari sumber SK hingga keputusan akhir, mengetahui siapa yang bertanggung jawab, melihat hambatan yang belum selesai, dan mempertanggungjawabkan perubahan. Pendaftar dapat menyelesaikan administrasi melalui ponsel tanpa kehilangan draf atau mengubah tanggal kirim pertama saat perbaikan.

Istilah utama: **SK** adalah daftar penetapan resmi; **klaim** adalah permohonan mengaitkan akun dengan satu baris SK; **workspace** adalah draf pribadi sebelum/sesudah klaim; **aplikasi** adalah pendaftaran resmi setelah klaim sah; **diterima lengkap** adalah keputusan administrasi PMB; **NIM** adalah identitas akademik resmi yang berbeda dari nomor PMB.

## 2. Cakupan rilis

| Rilis | Termasuk | Kriteria batas |
| --- | --- | --- |
| R1 / MVP operasional | Portal publik, Google, akun lokal WhatsApp, periode, pengguna, impor SK, penugasan, klaim, formulir, verifikasi, perbaikan, bukti cetak, audit, ekspor terbatas, referensi lokal, backup | Dapat menerima dan menyelesaikan PMB tanpa menulis ke Neo |
| R2 / integrasi Neo | Pemutakhiran referensi otomatis, pengesahan pemetaan, NIM, pratinjau, biodata/riwayat, rekonsiliasi, retry aman | Diaktifkan setelah kontrak nyata dan uji staging lulus |
| Di luar cakupan | Pembayaran, tes seleksi/ranking, KRS, nilai, kelas, perkuliahan, chatbot, WhatsApp API otomatis, aplikasi seluler native | Penambahan memerlukan rancangan dan estimasi tersendiri |

Dokumen unggahan identitas/ijazah **belum** merupakan fitur formulir prototipe. Verifikasi dapat mencatat hasil pemeriksaan sesuai SOP kampus; jangan menambahkan kewajiban unggah, kategori dokumen, atau retensinya tanpa keputusan pengelola. Unduh panduan resmi dapat berupa aset publik yang disetujui; daftar peserta SK lengkap tidak otomatis dipublikasikan.

## 3. Peran dan batas data

Peran database: `pendaftar_ppg`, `operator`, `administrator`; label demo `pendaftar`/`admin` hanya dipakai pada sumber UI. Satu akun memiliki satu peran utama. Administrator menjalankan kemampuan operator sebagai administrator; tidak menyamar atau mengganti ID aktor.

| Kemampuan | Publik | Pendaftar | Operator | Administrator |
| --- | --- | --- | --- | --- |
| Informasi periode/FAQ/panduan | Ya | Ya | Ya | Ya |
| Ajukan klaim WhatsApp | Ya, dibatasi | Gunakan alur akun | Tidak perlu | Dapat membantu melalui aksi tercatat |
| Draf dan formulir | Tidak | Milik sendiri | Baca tugas; tidak mengisi atas nama peserta | Baca semua; koreksi administratif khusus dan beralasan |
| Kirim/kirim ulang | Tidak | Milik sendiri sesuai status/waktu | Tidak | Tidak mengganti persetujuan pendaftar |
| Periksa klaim/WA/status | Tidak | Lihat milik sendiri | Hanya penugasan saat ini | Semua |
| Klaim tidak cocok/ambigu | Tidak | Lihat tanggapan aman | Hanya jika ditugaskan admin secara eksplisit | Triase, tetapkan penanggung jawab, selesaikan konflik |
| SK/periode/pengguna/penugasan | Tidak | Tidak | Baca bagian tugas | Kelola |
| Ekspor peserta | Tidak | Bukti milik sendiri | Dinonaktifkan secara baku; jika diizinkan hanya scope tugas | Sesuai kolom yang disetujui |
| Riwayat | Tidak | Riwayat publik milik sendiri | Aktivitas sendiri pada tugas; tanpa secret | Audit semua yang diizinkan |
| Neo/pemetaan/secret | Tidak | Tidak | Ringkasan hambatan PMB bila perlu | Kelola, secret tidak ditampilkan kembali |

Akun nonaktif tidak dapat memakai sesi lama. Pengubahan tugas mencabut akses operator lama pada permintaan berikutnya. Halaman tanpa objek mengembalikan keadaan kosong; objek di luar scope merespons 404; kemampuan yang tidak dimiliki peran merespons 403.

## 4. Daftar kebutuhan fungsional

| ID | Kebutuhan | Bukti penerimaan yang diharapkan |
| --- | --- | --- |
| PUB-01 | Beranda menampilkan periode, jadwal, alur, FAQ, kanal resmi | Konten berasal dari konfigurasi yang disahkan; tidak menampilkan data contoh |
| PUB-02 | Cek kepesertaan terbatas dan klaim publik | Tidak dapat mengunduh/enumerasi roster; respons tidak membuka data pihak lain |
| AUTH-01 | Masuk Google dan akun berulang | Satu provider ID kembali ke akun yang sama; akun baru hanya pendaftar |
| AUTH-02 | Login lokal dan aktivasi pertama | Hanya kredensial yang diserahkan dan belum kedaluwarsa diterima; dashboard tertutup sebelum ganti sandi |
| AUTH-03 | Ganti sandi, logout, pemulihan, pengaitan Google | Kepemilikan diverifikasi; sesi/kredensial lama dapat dicabut; tidak ada penggabungan otomatis |
| ADM-01 | Kelola periode/gelombang dan jadwal | Validasi rentang waktu, alasan tutup manual, aturan revisi tersedia |
| ADM-02 | Kelola akun pengelola dan operator periode | Hanya admin; peran tidak dapat dinaikkan melalui payload publik |
| SK-01 | Template, unggah, validasi, pratinjau, commit, riwayat | Berkas benar-benar dibaca; file salah tidak menyimpan sebagian peserta |
| SK-02 | Impor ulang dan perubahan resmi | Baris identik tidak mengganda; perubahan peserta terikat klaim menjadi konflik |
| ASN-01 | Pembagian otomatis dan eksplisit | 400/4 menjadi 100 masing-masing; tugas lama tidak diam-diam berpindah |
| ASN-02 | Transfer, rebalancing, operator nonaktif | Pratinjau, alasan, versi, audit, dan hak akses baru konsisten |
| CLM-01 | Pengajuan Google dan WhatsApp | Pending tidak memesan entri; nama kembar tidak dipilih otomatis |
| CLM-02 | Persetujuan/pengembalian klaim | Satu SK dan satu akun/periode hanya menghasilkan satu aplikasi sah |
| WA-01 | Bukti balasan nomor aktif dan konflik nomor | Bukti mengacu nomor spesifik; format valid saja tidak dianggap terverifikasi |
| WA-02 | Serah akses manual | Sandi acak tampil sekali, hash tersimpan, aktor/waktu penyerahan tercatat |
| FRM-01 | Lima tahap formulir dengan simpan draf | Data tersimpan di server; pra-klaim memakai workspace; nomor nol awal utuh |
| FRM-02 | Validasi final dan persetujuan | Semua kelompok divalidasi server; versi pernyataan dan tanggal daftar dicatat |
| REV-01 | Antrean, checklist, diverifikasi/perbaikan/diterima lengkap | Transisi dan scope dijaga pada server; catatan perbaikan wajib |
| REV-02 | Perbaikan berulang dan penanganan pengecualian | Tanggal kirim pertama tetap; riwayat revisi utuh; alasan administratif terpisah |
| OUT-01 | Bukti pendaftaran cetak setelah kirim pertama | Milik sendiri; status saat dicetak dan waktu cetak jelas; bukan NIM |
| OUT-02 | Ekspor dengan filter/scope/kolom | Bukan hanya halaman tabel; audit ekspor tercatat; formula CSV dinetralkan |
| REF-01 | Wilayah, pilihan biodata, PT/prodi tervalidasi | Relasi induk-anak benar; kegagalan pembaruan tidak menghapus cache aktif |
| AUD-01 | Audit aksi penting dan riwayat yang aman | Identitas aktor nyata, alasan, entitas, waktu; tanpa sandi/token/NIK penuh |
| NEO-01 | Pratinjau kesiapan dan pengesahan pemetaan R2 | Admin saja; konfigurasi lengkap dan NIM sebelum kirim |
| NEO-02 | Sinkron dua tahap, rekonsiliasi dan retry R2 | Gagal riwayat tidak menggandakan biodata; sukses dibuktikan baca balik |

## 5. Kebutuhan nonfungsional dan definisi metrik

Angka berikut adalah **target rancangan untuk diuji di staging**, bukan hasil ukur yang telah tercapai.

| ID | Target | Cara memeriksa |
| --- | --- | --- |
| NF-01 | 400 SK dan 4 operator pada skenario awal; uji pula 4.000 baris sintetis | Impor, filter, dashboard, dan paginasi tanpa angka tetap |
| NF-02 | p95 halaman tabel/draf < 2 detik pada 50 sesi aktif di lingkungan uji yang dicatat | Catat perangkat, jaringan, konfigurasi server, volume, error rate; impor/Neo dipantau sebagai job |
| NF-03 | 100% aksi tulis domain memeriksa policy, periode, versi, dan invariant terkait | Matriks feature test dan pengujian request langsung |
| NF-04 | Simpan draf eksplisit; kehilangan koneksi tidak menampilkan sukses palsu | Waktu simpan terakhir hanya berubah sesudah server berhasil |
| NF-05 | Tampilan bisa dipakai lebar 360 px dan keyboard | UAT ponsel, fokus, label, kontras, error inline, tidak bergantung warna saja |
| NF-06 | Timestamp UTC; jadwal dan tanggal bisnis WIB; tanggal murni tidak digeser timezone | Uji batas hari dan waktu tutup |
| NF-07 | Target pemulihan: RPO 24 jam/RTO 4 jam, menunggu persetujuan pengelola | Latihan restore dan catatan waktu aktual |

Definisi dashboard: `total_sk` menghitung entri SK periode; `belum_ada` adalah entri tanpa aplikasi; tiap lima status lain menghitung aplikasi aktif terkait SK. Jumlah enam kategori harus sama dengan total entri SK yang masuk filter. Tampilkan pengecualian/penarikan sebagai filter/badge tambahan dengan definisi denominator yang jelas. `terkirim_saat_ini` berbeda dari `pernah_mengirim` (`submitted_at` terisi). `klaim_pending` menghitung permohonan, bukan peserta. `wa_belum_sah` menghitung aplikasi yang nomor saat ini belum terverifikasi. Operator selalu memakai scope penugasan yang sama pada kartu, tabel, dan ekspor.

## 6. Invariant yang tidak boleh dilanggar

1. Satu baris SK mempunyai paling banyak satu aplikasi; satu akun mempunyai paling banyak satu aplikasi per periode.
2. Nama dan nomor WhatsApp tidak menjadi kunci identitas tunggal; nomor bersama ditinjau sebagai konflik.
3. Google membuktikan kepemilikan akun Google; persetujuan klaim membuktikan kaitan SK.
4. Hanya draf/perbaikan yang dapat diubah pendaftar; accepted tidak dapat dibuka lewat payload browser.
5. `submitted_at` dan `tanggal_daftar` ditetapkan sekali oleh server, termasuk jika submit diulang karena timeout.
6. `diterima_lengkap` memerlukan SK sah, data lengkap, pemeriksaan terbaru, nomor WA yang masih sama terverifikasi, dan tidak ada blok administratif.
7. Penugasan sekarang adalah sumber otorisasi; histori penugasan tidak memberi akses aktif.
8. Perubahan status PMB dan hasil Neo independen; keberhasilan salah satunya tidak dipalsukan oleh yang lain.
