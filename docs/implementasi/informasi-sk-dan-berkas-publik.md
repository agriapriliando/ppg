# Informasi SK dan berkas periode untuk peserta/publik

Peserta membuka menu **Informasi SK** dari sidebar atau tombol pada ruang kerja. Halaman memuat data resmi SK yang terhubung ke akun: nama, Peg ID, kota/kabupaten dan provinsi asal, nomor/tanggal/urutan SK, status kepesertaan, dan operator pendamping. Jika memiliki lebih dari satu periode, peserta memilih periode kepesertaan yang ingin dilihat. Informasi jadwal menggunakan WIB. Catatan internal pengelola tidak ditampilkan.

Halaman juga menampilkan informasi periode dan berkas yang dibagikan kepada peserta. Data bersumber dari kepesertaan SK dengan `claimed_user_id` milik pengguna yang sedang masuk. Penggantian parameter periode tidak dapat membuka data milik orang lain. Peserta tanpa kepesertaan yang terhubung memperoleh penjelasan dan tautan ke permohonannya.

## Pengaturan visibilitas berkas

Pada panel **Berkas** di `/admin/periode`, setiap file tersedia pilihan:

- **Internal pengelola**: hanya administrator/operator; merupakan nilai awal untuk semua file lama dan unggahan baru.
- **Peserta periode ini**: dibaca dan diunduh melalui Informasi SK oleh peserta yang terhubung ke periode tersebut.
- **Publik & Peserta**: juga muncul melalui tombol **Lihat Berkas Periode** pada bagian periode homepage, jika periode dipublikasikan dan ditampilkan kepada publik.

Halaman publik menampilkan informasi periode dan judul/format/ukuran dokumen. Halaman ini tidak memuat identitas peserta, Peg ID, catatan internal, nama asli file, atau nama pengunggah. Penutupan pendaftaran tidak menyembunyikan panduan publik selama periode tetap dipublikasikan. Periode draft, tidak ditampilkan ke publik, atau arsip ditolak pada URL publik, termasuk URL file langsung. Pemilik SK tetap dapat membaca berkas bersama pada periode arsipnya.

Pencabutan visibilitas atau status file yang sedang dihapus langsung berlaku pada endpoint lihat/unduh. File tetap berada pada penyimpanan privat; publikasi tidak membuat salinan pada direktori publik. Perubahan visibilitas memakai pemeriksaan versi dokumen, izin pengelola, dan audit.

Rekomendasi: gunakan **Publik & Peserta** untuk panduan umum, jadwal, atau pengumuman yang memang ditujukan kepada calon peserta. Gunakan **Peserta periode ini** untuk dokumen khusus peserta dan **Internal pengelola** untuk arsip kerja pengelola. Periksa judul dan isi file sebelum memilih visibilitas publik.
