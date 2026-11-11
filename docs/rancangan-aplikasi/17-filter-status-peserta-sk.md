# Rancangan filter status akun dan antrean peserta SK

Status: **rancangan, belum diimplementasikan**. Cakupan: halaman `/peserta-sk` dan ekspor Excel peserta SK. Rancangan ini disusun berdasarkan kode lokal; tidak menyatakan kondisi deployment production.

## Tujuan dan rekomendasi

Pengelola dapat menemukan peserta berdasarkan status akun portal dan status pendaftaran, lalu mengekspor seluruh hasil yang memenuhi kombinasi filter. Gunakan dua pilihan terpisah di area filter yang ada, terapkan secara otomatis, dan pertahankan label yang sama dengan kolom tabel. Filter default adalah semua status.

Contoh: **Akun Terhubung + Draf** menemukan peserta yang telah memiliki akun dan data pendaftaran berstatus draf. Kombinasi **Belum Ada Akun + Belum Masuk Antrean** membantu mencari peserta yang belum memiliki akun terhubung maupun data pendaftaran.

## Kondisi aplikasi saat telaah

- Halaman aktif menggunakan `resources/views/pages/portal/⚡participants.blade.php`; filter saat ini mencakup pencarian nama/PEG ID, operator, periode dan asal wilayah, dengan pagination Livewire dan parameter URL.
- Kolom akun memakai `claimed_user_id` serta `claimedUser.account_state`. Kolom antrean memakai relasi `application` dan enum `ApplicationStatus`. Tampilan membedakan tidak ada aplikasi dari aplikasi berstatus draf, meskipun sebagian teks panduan lama menyebut keduanya bersama.
- Ekspor `ParticipantsController::export` membuat query terpisah dan menerima filter lama melalui URL. Implementasi harus menyelaraskan query daftar dan ekspor, bukan hanya menambah pilihan di layar.
- Hak akses halaman saat ini mengizinkan administrator dan operator aktif yang memenuhi syarat akun; daftar dipakai untuk memantau peserta seluruh operator. Perubahan filter tidak memperluas atau mempersempit hak tersebut.
- Halaman belum mempunyai tombol reset seluruh filter. Penambahan tombol tersebut adalah bagian dari usulan ini.

## Pilihan dan arti filter

### Status Akun

| Label | Nilai URL usulan | Kriteria sesuai tampilan saat ini |
| --- | --- | --- |
| Semua Status Akun | kosong | Tidak menambah pembatas akun. |
| Belum Ada Akun | `no_account` | `claimed_user_id` kosong. Termasuk permohonan klaim yang masih ditinjau dan belum terhubung. |
| Menunggu Aktivasi | `pending_activation` | Ada akun terhubung dengan `account_state = pending_activation`. |
| Akun Terhubung | `connected` | `claimed_user_id` terisi dan status yang ditampilkan bukan Nonaktif atau Menunggu Aktivasi. Untuk data normal berarti akun aktif. |
| Nonaktif | `disabled` | Ada akun terhubung dengan `account_state = disabled`. |

Jangan menyamakan **Belum Ada Akun** dengan klaim ditolak atau belum pernah mengajukan klaim; status ini hanya menunjukkan akun belum terhubung. Bila ditemukan referensi akun yang terisi tetapi relasinya tidak dapat dibaca, tampilan sekarang masuk ke cabang **Akun Terhubung**. Filter harus konsisten dengan tampilan dan ekspor pada data tersebut; catat kasus integritas ini untuk diperiksa, jangan diam-diam mengategorikannya sebagai Belum Ada Akun.

### Status Antrean

| Label | Nilai URL usulan | Kriteria |
| --- | --- | --- |
| Semua Status Antrean | kosong | Tidak menambah pembatas pendaftaran. |
| Belum Masuk Antrean | `not_started` | Relasi `application` tidak ada. |
| Draf | `draf` | Relasi ada dan `status = draf`. |
| Terkirim | `terkirim` | Relasi ada dan `status = terkirim`. |
| Diverifikasi | `diverifikasi` | Relasi ada dan `status = diverifikasi`. |
| Perlu Perbaikan | `perbaikan` | Relasi ada dan `status = perbaikan`. |
| Diterima Lengkap | `diterima_lengkap` | Relasi ada dan `status = diterima_lengkap`. |

Gunakan enum `ApplicationStatus` untuk nilai dan label status aplikasi, serta nilai eksplisit untuk `not_started`. **Draf dan Belum Masuk Antrean adalah dua kelompok berbeda.** Perbarui teks panduan status agar sesuai dengan perbedaan ini. Jika dikemudian hari ada status baru, label dan opsi perlu mengikuti enum; jangan otomatis memasukkan status tak dikenal ke salah satu kelompok lain.

## Perilaku antarmuka

1. Tambahkan dropdown **Status Akun** dan **Status Antrean** di area filter yang sudah ada. Gunakan gaya Bootstrap yang sama; susun bertumpuk pada layar sempit tanpa memotong label.
2. Saat pilihan berubah, filter digabung dengan filter lain menggunakan logika **DAN**, daftar diperbarui dan pagination kembali ke halaman pertama. Contoh: periode 4 + operator A + Draf hanya menampilkan peserta yang memenuhi ketiganya.
3. Gunakan parameter URL `account_status` dan `queue_status`; pilihan kosong tidak perlu dicantumkan. Muat ulang dan tautan yang dibagikan mempertahankan filter. Jangan memasukkan data pribadi ke URL selain filter pencarian yang sudah ada.
4. Tambahkan **Reset filter** untuk mengosongkan pencarian, operator, periode, asal wilayah, status akun dan status antrean, serta kembali ke halaman pertama. Pilihan jumlah per halaman tetap dipertahankan. Tampilkan pesan yang jelas jika tidak ada peserta yang sesuai.
5. Tombol Excel membawa semua filter aktif. Ekspor mencakup **seluruh hasil yang cocok**, bukan hanya halaman yang sedang terlihat. Jumlah hasil pada halaman dan ekspor seharusnya sesuai bila data belum berubah di antara kedua permintaan; tampilkan hasil kosong secara wajar.
6. Setelah menambah peserta, pastikan filter yang tersisa tidak menyembunyikan hasil tanpa penjelasan. Pertahankan perilaku pemilihan periode setelah pembuatan dan putuskan secara eksplisit reset atau pesan yang mengarahkan pengguna mengubah filter status.

## Rancangan implementasi

- Buat penerapan filter bersama yang menerima nilai yang telah dinormalisasi dan membangun kondisi pada query `SkParticipant`; gunakan pada komponen Livewire dan `ParticipantsController::export`. Pertahankan urutan, eager loading, pagination, cakupan akses dan semua filter lama. Hindari menggandakan aturan filter status di dua tempat.
- Status akun menggunakan `claimed_user_id`, relasi `claimedUser` dan nilai enum `AccountState`; status antrean menggunakan `whereHas`/`whereDoesntHave` pada relasi `application`. Kelompok harus saling lepas dan sesuai label di tabel. Jangan gunakan `visibleTo()` sebagai perubahan terselubung terhadap cakupan halaman/ekspor saat ini.
- Validasi nilai filter menggunakan daftar pilihan yang dikenal pada permintaan ekspor dan saat nilai URL Livewire dipulihkan. Pilihan tidak dikenal tidak boleh berubah diam-diam menjadi ekspor semua peserta: tampilkan kesalahan validasi yang jelas atau keadaan aman yang konsisten. Tidak ada input pengguna yang menjadi nama kolom/SQL mentah.
- Tambahkan kedua properti Livewire dengan alias URL, handler perubahan untuk `resetPage()`, kontrol reset, serta parameter pada tautan ekspor. Pastikan nilai query dinormalisasi dengan cara yang sama antara daftar dan ekspor, termasuk whitespace pencarian lama.
- Perbarui panduan peserta SK dan daftar pilihan filter. Status akun yang ditampilkan dan diekspor harus menggunakan pemetaan yang sama; pertimbangkan helper bersama agar aturan tampilan, filter dan label Excel tidak menyimpang.
- Tidak diperlukan migrasi: gunakan relasi dan kolom yang sudah tersedia. Evaluasi performa query pada data nyata sebelum menambah indeks; jangan memuat seluruh peserta ke memori untuk memfilter halaman.

## Pengujian dan penerimaan

1. Buat data untuk semua empat status akun dan enam keadaan antrean, termasuk **tidak ada aplikasi** dan **draf**. Cocokkan hasil filter dengan label tabel serta Excel; pastikan kelompok yang berbeda tidak tertukar.
2. Uji kombinasi status dengan periode, operator, asal wilayah, kata pencarian dan hasil kosong; peserta di luar kombinasi tidak tampil dan tidak ikut ekspor. Uji admin dan operator yang berwenang serta akses ditolak bagi pendaftar.
3. Uji perubahan filter ketika berada di halaman berikutnya, reset seluruh filter, mempertahankan jumlah per halaman, URL/muat ulang, dan nilai URL tidak sah.
4. Uji ekspor lebih dari satu halaman; bandingkan identitas peserta hasil query daftar dan Excel, bukan hanya jumlah. Pastikan data yang berubah di antara dua permintaan tidak dianggap sebagai jaminan snapshot bersama.
5. Uji akun belum aktif, nonaktif, tanpa akun dan kasus referensi akun tak terbaca jika dapat direpresentasikan pada basis data target. Periksa bahwa aturan filter sesuai dengan label tampilan dan ekspor.
6. Uji tampilan ponsel dan desktop, keyboard/label, pesan hasil kosong, penggunaan tombol reset serta tautan ekspor; tidak boleh ada tumpang tindih atau filter terpotong.
7. Verifikasi bahwa filter tidak mengubah data, hak akses, status peserta, penugasan, jumlah total data, atau isi riwayat. Jalankan tes terkait dan analisis kode sesuai prosedur proyek.

Rancangan ini siap sebagai acuan implementasi. Hasil pengujian dan kesiapan rilis baru dicatat setelah perubahan dibuat dan diuji.
