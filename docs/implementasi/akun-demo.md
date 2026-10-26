# Akun pemeriksaan lokal

Ketiga peran akun sudah dibuat melalui `DatabaseSeeder` → `DemoAccountSeeder` pada database lokal `db_ppg`. Tidak perlu membuat akun melalui terminal untuk masuk dan memeriksa aplikasi ini.

Halaman masuk: <http://127.0.0.1:8018/masuk>. Jika menggunakan virtual host Laragon, buka `/masuk` pada alamat proyek tersebut.

| Peran | ID login | Email | Password awal |
| --- | --- | --- | --- |
| Administrator | `admin` | `admin@ppg.test` | `PpgDemo2026!` |
| Operator | `operator` | `operator@ppg.test` | `PpgDemo2026!` |
| Pendaftar | `pendaftar` | `pendaftar@ppg.test` | `PpgDemo2026!` |

Masuk dapat menggunakan ID login atau email. Semua akun contoh aktif dan tidak dipaksa mengganti password pada login pertama agar setiap tampilan dapat langsung diperiksa. Hak akses tetap berasal dari peran tersimpan, bukan pemilih peran simulasi. Akun pendaftar contoh memakai login lokal; akun Google tetap harus melalui OAuth yang sebenarnya.

Seeder ini hanya menyiapkan akun, bukan SK, penugasan, referensi resmi, klaim atau pendaftaran fiktif. Tampilan kosong tetap ditampilkan jika data operasional belum diisi. Operator memerlukan penugasan periode/SK untuk melihat peserta; pendaftar tetap mengikuti proses klaim dan pengisian formulir.

Untuk pengembang: `php artisan db:seed` memanggil seeder akun secara otomatis; tidak memakai `migrate:fresh` dan tidak menghapus data. Seeder hanya berjalan di lingkungan `local`/`testing`, menyimpan hash password, dan mencatat audit. Akun yang sudah ada tidak direset. Benturan identitas dengan peran lain menghentikan transaksi. Password di atas tidak boleh dipakai sebagai kredensial produksi.
