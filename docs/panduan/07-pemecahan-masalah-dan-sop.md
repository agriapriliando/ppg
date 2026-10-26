# 07 — Pemecahan masalah dan SOP penggunaan

[Daftar panduan](README.md)

## 1. Pemeriksaan awal saat ada kendala

1. Catat peran akun, alamat halaman, periode, nomor PMB/referensi klaim, dan waktu kejadian WIB.
2. Baca pesan aplikasi secara utuh. Bedakan isian yang belum sah, jadwal tutup, batas akses, dan gangguan server.
3. Simpan catatan isian yang belum tersimpan sebelum memuat ulang jika memungkinkan.
4. Hindari mengulang tombol persetujuan, penerbitan akses, impor, atau transfer berkali-kali tanpa memeriksa hasil pertama.
5. Jika muncul pesan data berubah, muat ulang lalu periksa nilai terbaru sebelum mengambil tindakan.
6. Eskalasikan dengan informasi yang cukup kepada operator, administrator, atau petugas teknis sesuai tabel berikut.

## 2. Kendala akun dan klaim

| Gejala/pesan | Pemeriksaan dan langkah berikutnya | Penanggung jawab |
| --- | --- | --- |
| ID/email atau sandi tidak cocok | Periksa akses yang digunakan, karakter sandi, dan apakah sandi sementara sudah diganti | Pengguna; operator untuk pemulihan pendaftar WhatsApp |
| Akses Google belum tersedia | Coba akses lokal yang sah; minta pemeriksaan konfigurasi Google | Administrator/petugas teknis |
| Sesi Google tidak dapat diverifikasi | Mulai lagi dari halaman masuk, pilih akun pribadi, dan hindari memakai callback lama | Pengguna |
| Identitas Google berbenturan | Masuk dengan akun lokal yang benar lalu kaitkan dari Akun & Keamanan bila memungkinkan | Pengguna/administrator |
| Sandi sementara gagal meski disalin benar | Pastikan penyerahan sudah dicatat, belum dipakai, belum kedaluwarsa, dan tidak dicabut penerbitan baru | Operator |
| Sesi aktivasi berakhir | Minta akses baru; sandi sementara yang sudah digunakan tidak dapat dipakai ulang | Operator |
| Akun tidak aktif | Periksa status akun; jangan terus mencoba sandi lain | Administrator |
| Klaim tidak muncul pada akun | Pastikan akun benar; pengajuan publik tanpa akun belum otomatis memberi dashboard | Operator memakai referensi klaim |
| Nama ambigu/tidak ada kandidat | Cocokkan pengenal, nomor SK dan urut; minta penetapan kandidat administratif | Administrator |
| Kandidat sudah dimiliki pihak lain | Periksa bukti kedua pihak; jika salah pemilik, gunakan prosedur koreksi kepemilikan | Administrator |
| Perlu informasi tambahan | Jawab catatan dari akun sendiri bila formulir tanggapan tersedia; pengguna tanpa akun menghubungi kanal resmi | Pendaftar/operator |
| Nomor WhatsApp dipakai beberapa peserta | Telusuri penggunaan nomor dan kasus yang tercatat sebelum persetujuan/keputusan | Administrator |
| Klaim sudah berubah | Muat ulang detail dan tinjau status, kandidat, serta catatan terbaru | Pemeriksa |

## 3. Kendala formulir dan keputusan

| Gejala/pesan | Langkah penanganan |
| --- | --- |
| Pilihan wilayah/PT/prodi tidak tersimpan | Klik hasil pencarian, bukan hanya mengetik. Pilih induk terlebih dahulu dan pilih ulang turunannya setelah induk berubah |
| Referensi belum tersedia | Hubungi administrator untuk menerbitkan dataset; simpan draf yang dapat divalidasi sambil menunggu |
| NIK/NISN ditolak | NIK tepat 16 digit; NISN bila diisi tepat 10 digit. Hapus pemisah dan jangan menebak nomor |
| Nomor KPS wajib | Isi nomor jika memilih Ya, atau pilih Tidak jika memang bukan penerima |
| Tanggal lahir tidak sah | Pilih tanggal sebenarnya sebelum hari ini |
| Draf berubah di tab/perangkat lain | Muat ulang, periksa isian terakhir di server, lalu terapkan lagi perubahan yang masih diperlukan |
| Sudah pindah tahap tetapi isian hilang | Perpindahan tahap tidak menyimpan; gunakan Simpan Draf dan tunggu pesan berhasil |
| Tombol kirim belum tersedia/aktif | Periksa persetujuan klaim, dataset, naskah pernyataan, kondisi formulir, dan kelengkapan |
| Klaim belum disetujui atau waktu berakhir | Periksa status klaim serta jadwal pengiriman pertama/perbaikan bersama operator |
| Formulir terkunci | Status Terkirim, Diverifikasi, atau Diterima lengkap tidak dapat diubah biasa; tunggu tindak lanjut pemeriksa |
| Naskah pernyataan telah berubah | Muat ulang dan baca serta setujui versi terbaru |
| Kepesertaan memerlukan pemeriksaan administratif | Administrator perlu memeriksa kondisi aktif/ditarik, tahan sementara, dan pemilik; bukti lama bukan penyelesaian konflik |
| Sudah diperbaiki tetapi operator belum melihat kiriman baru | Pastikan klik Kirim Pendaftaran setelah perbaikan dan status kembali Terkirim, bukan hanya Simpan Draf |
| Diterima lengkap belum dapat ditetapkan | Pastikan status Diverifikasi, pemeriksaan revisi terbaru, WhatsApp nomor saat ini sah, kepesertaan sesuai, dan semua kasus terbuka terselesaikan |
| Nomor WhatsApp baru belum terverifikasi | Catat pemeriksaan nomor baru; perubahan nomor membatalkan verifikasi sebelumnya |
| Bukti belum tersedia | Kirim formulir dahulu; setelah koreksi pemilik, pemilik saat ini harus mengirim formulirnya sendiri |
| Tombol cetak tidak membuat berkas langsung | Pilih Simpan PDF pada dialog cetak peramban, lalu tentukan nama dan lokasi |

## 4. Kendala administrator

| Gejala/pesan | Langkah penanganan |
| --- | --- |
| Impor menunggu terlalu lama | Perbarui hasil; petugas teknis memeriksa worker antrean dan pekerjaan gagal |
| Header atau sheet tidak sesuai | Unduh template baru; gunakan sheet Peserta_SK serta header persis dalam urutan A–H |
| Pengenal resmi harus teks | Isi ulang dari sumber setelah sel diformat teks; pastikan nol awal dipertahankan |
| Impor invalid/failed | Unduh laporan kesalahan, perbaiki sumber, lalu unggah ulang |
| Perubahan terikat klaim/penugasan | Jangan memaksa melalui impor; gunakan pemeriksaan administratif atau transfer operator |
| Pratinjau tidak valid/data berubah | Unggah ulang impor atau buat ulang pratinjau penugasan setelah membaca keadaan terbaru |
| Operator tujuan tidak tersedia | Periksa status aktif, keanggotaan periode, dan kewajiban ganti sandi; daftar massal memiliki syarat tersebut |
| Beban akhir belum merata setelah pembagian awal | Pembagian awal hanya membagi peserta belum ditugaskan; gunakan penyeimbangan seluruh peserta aktif bila diperlukan |
| Operator dinonaktifkan tetapi tugas masih ada | Lakukan transfer tugas; perubahan status akun tidak otomatis membagi ulang |
| Arsip ditolak | Selesaikan klaim terbuka dan pendaftaran peserta aktif yang belum Diterima lengkap |
| Ekspor belum siap/kedaluwarsa/cakupan berubah | Tunggu antrean atau ajukan ekspor baru sesuai kondisi dan filter terbaru |
| Rincian impor telah dibersihkan | Gunakan berkas sumber yang diarsipkan dan unggah ulang untuk validasi baru |
| Pengaturan/periode berubah oleh pengguna lain | Muat ulang dan ulangi perubahan yang masih diperlukan berdasarkan versi terbaru |
| Tautan peserta menampilkan 404/akses ditolak | Periksa peran, keanggotaan periode, serta transfer penugasan. Jangan menganggap data terhapus sebelum administrator memeriksa |

## 5. Gangguan server `filemtime()` pada halaman periode

Bagian ini untuk petugas teknis. Pesan seperti `filemtime(): stat failed ... storage/framework/views/livewire/views/...blade.php` berarti sistem mencoba membaca file view hasil kompilasi yang tidak ditemukan. Cache view yang tidak sinkron merupakan salah satu kemungkinan penyebab; trace dan log tetap diperlukan jika masalah berulang.

Untuk proyek lokal ini, buka PowerShell dan jalankan dari folder aplikasi yang benar:

```powershell
Set-Location -LiteralPath 'D:\project\ppg'
php artisan config:clear
php artisan view:clear
```

Kemudian restart layanan PHP/web server yang melayani domain aplikasi dan buka kembali `/admin/periode`. Jika aplikasi yang diakses ternyata berasal dari `C:\laragon\www\ppg`, jalankan pada folder tersebut; membersihkan cache proyek di D: tidak memperbaiki instance lain secara otomatis.

Jika masalah berlanjut, petugas teknis memeriksa:

- Exception trace dan log `storage/logs` pada instance yang benar.
- Lokasi aplikasi yang dipakai virtual host/domain dan lokasi cache view efektif.
- Akses tulis proses PHP terhadap folder penyimpanan view.
- Proses pembersihan cache yang berjalan bersamaan dengan permintaan pengguna.
- Kesesuaian dependensi dan hasil pemasangan aplikasi.

Jangan membuat file hash Blade kosong sebagai pengganti view hilang atau mengedit vendor untuk menyembunyikan error. Langkah di atas adalah prosedur diagnosis awal, bukan klaim bahwa semua penyebab telah diperbaiki. Jangan mengirim `.env`, sandi, atau log lengkap berisi data pribadi ke kanal umum.

## 6. SOP awal hari

### Administrator

- [ ] Periode publik, jadwal buka/tutup/perbaikan, serta pengumuman sudah benar dalam WIB.
- [ ] Helpdesk dan jam layanan dapat ditemukan pada portal.
- [ ] Referensi serta dua jenis pernyataan tersedia.
- [ ] Operator aktif, terdaftar pada periode, dan penugasan sudah sesuai.
- [ ] Klaim tanpa kandidat, nomor bersama, penahanan, dan kasus terbuka sudah ditinjau.
- [ ] Tidak ada impor/ekspor tertahan yang memerlukan bantuan teknis.

### Operator

- [ ] Akun dan lingkup tugas benar.
- [ ] Klaim menunggu/perlu informasi ditinjau.
- [ ] Bukti nomor aktif dicatat sebelum persetujuan jalur WhatsApp atau penyerahan akses.
- [ ] Setiap akses yang benar-benar diserahkan sudah dicatat penyerahannya.
- [ ] Antrean Terkirim dan perbaikan yang sudah dikirim ulang diperiksa.
- [ ] Kasus di luar kewenangan disampaikan kepada administrator beserta referensinya.

## 7. SOP menjelang penutupan dan akhir hari

1. Administrator memeriksa waktu tutup dan kebijakan perbaikan, lalu memperbarui pengumuman bila diperlukan.
2. Operator mengingatkan pendaftar agar menyelesaikan **pengiriman**, bukan hanya draf.
3. Setiap permintaan perbaikan diberi catatan jelas dan mengikuti jendela kirim ulang.
4. Petugas memastikan penerbitan akses yang belum diserahkan tidak disalahartikan sebagai akses aktif bagi pendaftar.
5. Administrator meninjau kasus yang menghambat keputusan dan kebutuhan transfer pekerjaan.
6. Buat ekspor bila diperlukan, periksa filter serta jumlah baris, lalu simpan sesuai kewenangan.
7. Catat serah terima pekerjaan tertunda tanpa sandi atau NIK lengkap.
8. Keluar dari akun pada perangkat bersama.
9. Arsipkan periode hanya setelah syarat penyelesaian terpenuhi dan keputusan resmi sudah sesuai.

## 8. Skenario latihan petugas

Gunakan lingkungan latihan dengan data fiktif dan akun terpisah. Jangan mencoba skenario salah pemilik menggunakan data peserta nyata.

| Skenario | Urutan latihan | Hasil yang harus diamati |
| --- | --- | --- |
| Jalur Google | Masuk pribadi → klaim → pemeriksaan → persetujuan → isi dan kirim | Klaim dan pendaftaran terhubung ke akun yang benar |
| Jalur WhatsApp | Ajukan tanpa akun → verifikasi → setujui → terbitkan → serahkan → catat → aktivasi | Akses sementara memerlukan catatan penyerahan dan sandi pribadi baru |
| Perbaikan data | Kirim → operator meminta perbaikan → ubah → kirim ulang | Revisi meningkat, tanggal daftar pertama tetap |
| Nomor berubah | Ubah nomor saat status dapat diedit → kirim ulang → periksa nomor | Verifikasi lama tidak berlaku; nomor saat ini perlu pemeriksaan |
| Impor ulang identik | Impor dan konfirmasi sumber yang sama | Baris identik tidak membuat duplikat |
| Transfer tugas | Pindahkan peserta ke operator lain | Operator tujuan mendapat akses dan operator lama kehilangan akses terkait |
| Jendela perbaikan | Tutup pendaftaran dengan batas perbaikan yang sah | Kirim pertama tertutup; perbaikan mengikuti syaratnya sendiri |
| Koreksi pemilik | Tahan SK → klaim tujuan → pratinjau → koreksi → kirim pemilik baru | Biodata lama tidak menjadi biodata pemilik baru; bukti menunggu pengiriman pemilik saat ini |

## 9. Format laporan kendala

Gunakan format berikut agar pemeriksaan cepat dan terarah:

```text
Peran pengguna:
Nama periode:
Nomor PMB atau referensi klaim:
Alamat halaman:
Waktu kejadian (WIB):
Langkah sebelum kendala:
Pesan yang tampil:
Hasil yang diharapkan:
Apakah terjadi lagi setelah masuk ulang/muat ulang:
```

Lampirkan tangkapan layar seperlunya setelah menyamarkan data pribadi dan rahasia. Petugas teknis dapat meminta potongan trace yang relevan melalui kanal kerja yang sesuai.
