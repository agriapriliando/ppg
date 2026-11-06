# Penghapusan permanen peserta SK

Pada daftar `/peserta-sk`, administrator aktif dapat memilih **Hapus**. Dialog responsif menampilkan nama, Peg ID, dan periode peserta serta dampak penghapusan. Administrator harus mencentang persetujuan dan menekan **Hapus Permanen**. Operator dan pendaftar tidak diberi izin melakukan operasi ini, termasuk melalui pemanggilan langsung metode Livewire.

Operasi menghapus kepesertaan SK, klaim terkait dan kredensial yang diterbitkan melalui klaim tersebut, pendaftaran, biodata, riwayat pendidikan, persetujuan, riwayat pemeriksaan dan pengiriman, serta dokumen terkait. Akun pengguna, peserta lain, konfigurasi periode, dan audit tetap dipertahankan. Ruang kerja dari koreksi kepemilikan hanya dibersihkan jika tidak sedang digunakan atau menjadi riwayat peserta lain.

Perubahan database dilakukan dalam transaksi dengan penguncian periode dan peserta serta pemeriksaan versi peserta. Versi yang berubah sejak dialog dibuka ditolak sebelum data dihapus. Tidak ada penghapusan fisik file sebelum transaksi database berhasil.

Setiap file dibuatkan `document_deletion_tasks` sebelum metadata dihapus. Setelah transaksi berhasil, aplikasi mencoba menghapus objek fisik satu per satu. Jika gagal, pesan hasil menyebutkan jumlah file yang masih menunggu penghapusan. Proses `pmb:process-document-deletions` yang sudah dijadwalkan mencoba ulang; tugas baru dihapus setelah file berhasil dihapus atau dipastikan tidak ada. Aplikasi tidak menyatakan seluruh berkas berhasil dihapus jika masih ada kegagalan penyimpanan.

Pengujian terdapat pada `ParticipantDeletionTest` serta skenario penghapusan setelah koreksi kepemilikan pada `OwnershipTransferTest`. Perubahan kode perlu dirilis ke server agar tersedia pada domain produksi; pengembangan ini tidak menjalankan penghapusan terhadap data produksi.
