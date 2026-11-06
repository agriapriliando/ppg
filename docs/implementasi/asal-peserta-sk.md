# Kota/kabupaten dan provinsi asal peserta SK

## Pengisian manual

Pada **Tambah Peserta SK**, ketik nama kota/kabupaten, nama provinsi, atau kode wilayah pada **Kota/Kabupaten Asal**, lalu pilih hasil pencarian. Hasil menampilkan nama kota/kabupaten dan provinsi untuk membedakan nama yang serupa. Provinsi asal terisi otomatis dan tidak dapat diketik terpisah. Tidak ada input kecamatan. Pola pencarian memakai komponen yang sama dengan pencarian kecamatan pada formulir.

Kolom asal bersifat opsional agar peserta lama serta SK yang belum memuat data asal tetap dapat dikelola. Data disimpan terpisah dari alamat tempat tinggal biodata peserta. Pengelola juga dapat memperbaiki atau mengosongkan asal dari **Edit Data SK**. Daftar, detail peserta, cetak biodata, dan ekspor Excel menampilkan informasi asal SK.

Server memeriksa bahwa pilihan adalah kota/kabupaten pada dataset referensi dan memiliki induk provinsi yang sah. Nilai provinsi dari browser tidak dipercaya. Kode, nama kota/kabupaten, nama provinsi, serta versi dataset disimpan sebagai snapshot agar nama tetap terbaca jika dataset aktif diganti.

## Filter Asal Wilayah

Daftar peserta menyediakan filter **Asal Wilayah** berdasarkan kota/kabupaten asal yang telah tersimpan pada peserta SK. Pilihan diurutkan menurut nama kota/kabupaten, disertai provinsi, dan tidak berulang per kode. Pilihan **Asal wilayah belum diisi** menampilkan peserta tanpa data asal. Filter dapat digabungkan dengan nama/Peg ID, periode, dan operator, tersimpan pada URL `origin_city`, dan ikut diterapkan pada ekspor Excel. Mengubah asal wilayah mengembalikan pagination ke halaman pertama.

## Impor Excel versi 2

Unduh template terbaru pada `/admin/impor`. Sheet **Peserta_SK** menambahkan kolom I, `kode_kota_kabupaten_asal`. Sheet **Referensi_Wilayah** berisi kode teks, nama kota/kabupaten, dan provinsi dari dataset aktif. Salin kode yang sesuai sebagai teks; jangan mengisi nama kota atau kode kecamatan. Provinsi ditentukan otomatis saat validasi. Jika referensi kosong, pengelola perlu memuat dan mengaktifkan dataset wilayah, atau mengosongkan kolom asal.

Pratinjau menampilkan kota/kabupaten serta provinsi. Kode salah menyebabkan baris ditandai sebagai kesalahan dan seluruh impor ditahan. Laporan kesalahan Excel menyertakan kode asal untuk mempermudah koreksi. Konfirmasi impor memeriksa ulang snapshot referensi sebelum menyimpan, dengan cache per kode untuk menghindari query berulang pada berkas besar.

Template lama dengan delapan kolom tetap diterima. Kolom asal yang tidak ada atau kosong mempertahankan informasi asal peserta yang sudah tersimpan; untuk sengaja menghapus data asal, gunakan **Edit Data SK**. Impor identik tetap tidak menggandakan peserta. Perubahan peserta yang sudah terikat klaim tetap mengikuti pemeriksaan administratif yang ada.

Rekomendasi operasional: gunakan kode wilayah dari sheet referensi, jangan menebak kode atau mengetik provinsi terpisah. Gunakan satu dataset referensi wilayah yang telah diperiksa agar tambah manual, impor, dan formulir tetap konsisten.
