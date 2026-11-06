# Pengelolaan berkas per periode

Di `/admin/periode`, setiap kartu periode menyediakan tombol **Berkas (jumlah)**. Pilih tombol untuk membuka panel berkas periode di bawah daftar. Panel juga terbuka otomatis setelah periode baru disimpan. Judul panel menampilkan nama, kode, dan tahun akademik untuk membantu memastikan tujuan unggahan.

## Cara memakai

1. Simpan periode terlebih dahulu, lalu buka panel Berkas periode tersebut.
2. Pilih atau seret satu atau beberapa file. Unggahan dimulai otomatis dengan indikator kemajuan.
3. Berkas yang berhasil muncul pada daftar. File lain dapat ditambahkan kemudian; tidak ada batas satu berkas per periode.
4. Gunakan **Ubah Nama** untuk memperjelas judul, **Lihat** untuk PDF/gambar, atau **Unduh** untuk mengambil file. Ekstensi dan isi tidak berubah saat nama tampilan diubah.
5. Ikon informasi menampilkan nama asli, ukuran, format, waktu unggah, dan pengunggah melalui dialog responsif. Nama asli tidak ditampilkan sebagai kolom berulang.
6. Tombol **Hapus** meminta centang persetujuan. Penghapusan mencakup metadata dan file fisik. Jika penyimpanan gagal, file diberi status menunggu penghapusan dan akses unduh ditutup. Sistem mencoba ulang melalui perintah terjadwal `pmb:process-document-deletions`; pengelola juga dapat memilih **Coba Ulang Hapus**.

Format dan batas ukuran per file mengikuti **Aturan Berkas** periode. Berkas periode tersimpan secara terpisah dan tidak memakai kuota ruang kerja peserta. Jika satu file gagal dalam unggahan beberapa file, file yang sudah berhasil tetap disimpan dan pesan menyebutkan file yang perlu dipilih ulang. Dua file bernama sama tetap disimpan sebagai dua objek yang berbeda; retry permintaan unggah yang sama tidak menggandakan data.

Batas unggahan sementara Livewire diselaraskan dengan maksimum konfigurasi aturan berkas, yaitu 100 MiB per file. Validasi akhir tetap memakai batas periode yang dipilih, sehingga batas sementara ini tidak menggantikan aturan ukuran file masing-masing periode.

## Akses dan keandalan

Administrator dan operator aktif mengikuti izin pengelolaan periode yang sudah ada. Berkas disimpan pada penyimpanan privat dengan visibilitas awal **Internal pengelola**, termasuk berkas yang sudah ada sebelum fitur publikasi ditambahkan. Pengelola dapat mengubahnya menjadi **Peserta periode ini** atau **Publik & Peserta**. Berkas peserta tersedia melalui menu Informasi SK untuk pemilik kepesertaan; berkas publik tersedia pada halaman berkas periode ketika periode berstatus dipublikasikan dan ditampilkan di homepage. Setiap permintaan lihat/unduh memeriksa izin, periode, ketersediaan metadata, dan file fisik. Periode arsip tetap dapat dibaca pemilik SK untuk berkas yang dibagikan kepadanya, tetapi disembunyikan dari publik. Unggah, ubah nama, perubahan visibilitas, dan hapus baru pada arsip ditolak oleh server.

Nama file fisik memakai UUID, bukan nama dari pengguna. Ukuran, ekstensi, dan MIME diperiksa sebelum disimpan. Perubahan nama/hapus memakai versi dokumen dan penguncian database agar perubahan pengguna lain tidak tertimpa. Audit mencatat unggah, perubahan nama, permintaan hapus, lihat, dan unduh. Tugas penghapusan membedakan berkas peserta dan berkas periode agar metadata yang dibersihkan sesuai jenisnya.

Rekomendasi: gunakan PDF untuk panduan atau pengumuman yang siap dibaca, dengan judul seperti **Panduan Pendaftaran**, **Jadwal Seleksi**, dan **Daftar Berkas Persyaratan**. Gunakan nama tampilan yang jelas dan pastikan periode pada judul panel sesuai sebelum mengunggah.
