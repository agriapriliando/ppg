# Hapus akun dan klaim WhatsApp agar dapat mengajukan ulang

Administrator dapat memilih **Hapus** pada klaim WhatsApp yang disetujui di `/klaim`. Dialog responsif meminta persetujuan dan menjelaskan bahwa hanya akses akun serta klaim WhatsApp yang dihapus dari penggunaan aktif. Peserta SK, penugasan operator, biodata, riwayat pendidikan, pendaftaran, keputusan/status, snapshot pengiriman, serta berkas fisik tetap dipertahankan.

ID login dan kata sandi lama dikosongkan, akun dinonaktifkan, sesi dihapus, remember token diganti, seluruh kredensial WhatsApp akun dicabut, dan salinan kata sandi terenkripsi dibersihkan. Referensi internal pengguna tetap ada untuk menjaga keterkaitan dan privasi riwayat pendaftaran. Klaim memakai soft delete sehingga tidak tampil di daftar atau dapat dibuka melalui URL normal, sementara relasi historis tetap sah.

Peserta SK dilepas dari akun. Pendaftaran diberi penanda internal `claim_account_reset_at`. Peserta dapat mengajukan klaim WhatsApp baru melalui formulir publik, sesuai jadwal periode. Saat disetujui, aplikasi menggunakan kembali pemilik internal, nomor pendaftaran, ruang kerja, biodata, serta berkas yang sama. ID login dan kata sandi diterbitkan kembali; kontak WhatsApp aktif diperbarui sesuai klaim baru, sedangkan snapshot pengiriman lama tetap dipertahankan.

Klaim melalui akun berbeda tidak otomatis memperoleh data lama; pengelola diminta menggunakan koreksi kepemilikan. Akun nonaktif tanpa penanda reset tidak otomatis diaktifkan oleh alur persetujuan biasa. Pemeriksaan kepesertaan aktif, hold administratif, izin pengelola, versi klaim, serta periode arsip tetap mengikuti alur yang ada.

Untuk klaim yang belum disetujui, tombol Hapus menghapus klaim dari penggunaan aktif beserta relasi operasionalnya dan tidak menghapus akun, pendaftaran, ataupun berkas. Penghapusan permanen peserta SK beserta pendaftaran/berkas tetap merupakan fitur terpisah di daftar peserta SK.
