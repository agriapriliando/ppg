# Dokumentasi Perintah Artisan: Reset Data Operasional & Berkas Unggahan

Dokumen ini menjelaskan perintah console Artisan `pmb:reset-data` yang digunakan untuk mereset seluruh data operasional, proses pendaftaran, kepesertaan SK, dan berkas fisik unggahan dengan **tetap mempertahankan seluruh data referensi dan akun staf pengelola**.

---

## 1. Ringkasan Perintah

| Parameter | Keterangan |
| :--- | :--- |
| **Signature** | `php artisan pmb:reset-data` |
| **Deskripsi** | Mereset seluruh data operasional pendaftaran, kepesertaan SK, dan file unggahan kecuali data referensi |
| **Action Class** | [`App\Actions\System\ResetOperationalData`](file:///d:/project/ppg/app/Actions/System/ResetOperationalData.php) |
| **Command Class** | [`App\Console\Commands\ResetOperationalDataCommand`](file:///d:/project/ppg/app/Console/Commands/ResetOperationalDataCommand.php) |
| **Test Suite** | [`tests/Feature/Console/Commands/ResetOperationalDataCommandTest.php`](file:///d:/project/ppg/tests/Feature/Console/Commands/ResetOperationalDataCommandTest.php) |

---

## 2. Klasifikasi Data

### A. Data yang DIPERTAHANKAN (Tidak Dihapus)
Data referensi dan konfigurasi master dijaga integritasnya agar sistem tetap siap digunakan tanpa perlu setup ulang wilayah atau impor referensi:

1. **Dataset Referensi & Master**:
   * Wilayah Administrasi (`reference_regions` — 7.800+ baris data provinsi, kabupaten/kota, dan kecamatan se-Indonesia).
   * Opsi Biodata (`reference_options` — agama, jenis kelamin, kewarganegaraan, jenis tinggal, transportasi, kebutuhan khusus).
   * Perguruan Tinggi Asal (`reference_pt`).
   * Program Studi Asal (`reference_prodi`).
   * Master Dataset Versioning (`reference_datasets`).
2. **Klausul Regulasi & Portal**:
   * Naskah Persetujuan Pendaftaran & Klaim (`consent_texts`).
   * Tautan Cepat Portal Mahasiswa (`portal_quick_links` — tautan LMS PPG, UKIN, dsb.).
   * Pengaturan Sistem Aplikasi (`application_settings`).
3. **Akun Staf Internal**:
   * Akun pengguna dengan role **Administrator** (`admin`) dan **Operator** (`operator`, dsb.) tetap aktif agar staf kampus tidak kehilangan akses ke panel administrasi.
4. **Skema Basis Data**:
   * Catatan migrasi Laravel (`migrations`).

### B. Data yang DIRESET (Dibersihkan)
Semua data transaksional yang dihasilkan dari aktivitas pendaftar atau alur operasional:

1. **Berkas Fisik Unggahan Server**:
   * `storage/app/private/applicant-documents/` (dokumen persyaratan yang diunggah calon mahasiswa).
   * `storage/app/private/sk-imports/` (file excel import SK peserta).
   * `storage/app/private/livewire-tmp/` (berkas sementara saat upload Livewire).
   * `storage/app/private/exports/` (arsip ekspor data pendaftar/peserta).
   * File unggahan non-sistem pada direktori `storage/app/public/`.
2. **Pendaftaran Calon Mahasiswa**:
   * `applications` (entitas pendaftaran PMB).
   * `application_submissions` (snapshot riwayat kirim berkas).
   * `application_reviews` (catatan pemeriksaan berkas oleh operator).
   * `application_ownership_transfers` (riwayat transfer kepemilikan berkas).
   * `applicant_documents` & `document_deletion_tasks` (metadata dokumen pendaftar).
   * `applicant_workspaces`, `applicant_biodata`, `origin_education` (formulir ruang kerja pendaftar).
   * `whatsapp_verifications` (riwayat verifikasi nomor aktif WhatsApp).
3. **Klaim SK & Kredensial**:
   * `sk_claims` (permohonan klaim kepesertaan SK).
   * `claim_responses` (balasan/tanggapan pemohon klaim).
   * `credential_issuances` (riwayat pembuatan/penerbitan password akun manual).
   * `consent_records` (rekaman persetujuan klausul pendaftar).
   * `administrative_cases` (kasus administratif pembagian klaim/pendaftaran).
4. **Kepesertaan SK & Impor**:
   * `sk_participants` (daftar peserta resmi SK yang siap diklaim).
   * `sk_imports` & `sk_import_rows` (riwayat dan baris data impor file SK).
   * `assignment_events` (riwayat pembagian/penugasan operator ke peserta SK).
   * `export_runs` (antrean dan riwayat ekspor data).
5. **Periode & Antrean Transaksional**:
   * `admission_sequences` (nomor urut pendaftaran PMB).
   * `admission_periods`, `period_operators`, `document_policy_versions`, `admission_config_versions` *(dihapus secara default, atau dipertahankan jika memakai opsi `--keep-periods`)*.
6. **Akun Pengguna Pendaftar & Sesi**:
   * Akun pengguna dengan role `pendaftar_ppg` dihapus.
   * `social_accounts` (tautan akun Google pendaftar).
   * `sessions`, `password_reset_tokens`.
   * `jobs`, `failed_jobs`, `job_batches`.
   * Cache aplikasi (`Cache::flush()`).
   * `audit_events` *(dibersihkan, lalu sistem otomatis mencatat 1 audit event baru terkait eksekusi reset data)*.

---

## 3. Opsi Perintah

| Opsi | Tipe | Penjelasan |
| :--- | :--- | :--- |
| *(tanpa opsi)* | Interaktif | Menampilkan peringatan detail dan meminta konfirmasi `yes/no`. |
| `--force` | Boolean | Menjalankan proses reset secara langsung tanpa meminta konfirmasi interaktif. |
| `--keep-periods` | Boolean | Mempertahankan periode penerimaan (`admission_periods`), konfigurasi dokumen, dan operator periode. Hanya mengosongkan peserta, klaim, formulir, berkas, serta mereset sequence nomor pendaftaran ke 0. |
| `--keep-users` | Boolean | Mempertahankan seluruh akun pendaftar yang ada di database. |
| `--keep-audit` | Boolean | Menjaga riwayat log audit terdahulu agar tidak dihapus. |
| `--seed-demo` | Boolean | Menyiapkan ulang akun demo pendaftar (`pendaftar`) setelah reset selesai *(khusus environment `local` atau `testing`)*. |

---

## 4. Contoh Penggunaan

### 1. Menjalankan Reset Standar (Interaktif)
Perintah akan menampilkan peringatan dan meminta persetujuan:
```bash
php artisan pmb:reset-data
```
Contoh alur dialog:
```text
PERINGATAN: Tindakan ini akan mereset data operasional dan file fisik unggahan.
  - Data yang DIHAPUS: Pendaftaran, biodata, verifikasi berkas, klaim SK, peserta SK, impor SK, dan dokumen fisik unggahan.
  - Data yang DIPERTAHANKAN: Data referensi wilayah (7.800+ baris), opsi biodata, PT, prodi, klausul persetujuan, tautan cepat, pengaturan sistem, dan akun staf (Admin/Operator).

 Apakah Anda yakin ingin melanjutkan proses reset ini? (yes/no) [no]:
 > yes
```

### 2. Reset Otomatis / Non-Interaktif (Script Deployment / CI)
```bash
php artisan pmb:reset-data --force
```

### 3. Reset Tanpa Menghapus Periode Penerimaan
Jika periode penerimaan (misalnya `2026PPG1`) sudah diatur dan Anda hanya ingin mengosongkan berkas, peserta, dan pendaftar di dalamnya:
```bash
php artisan pmb:reset-data --keep-periods --force
```

### 4. Reset dan Buat Ulang Akun Demo (Local Development)
```bash
php artisan pmb:reset-data --seed-demo --force
```

---

## 5. Keamanan & Integritas Data

1. **Foreign Key Protection**:
   * Penonaktifan foreign key checks disesuaikan dengan driver basis data aktif (`SET FOREIGN_KEY_CHECKS=0` untuk MySQL/MariaDB dan `PRAGMA foreign_keys = OFF` untuk SQLite).
   * Seluruh tabel child dan dependensi relasi dibersihkan dengan urutan terstruktur sehingga tidak menimbulkan orphan foreign key.
2. **Pembersihan File Fisik Terisolasi**:
   * File `.gitignore` pada direktori root penyimpanan tetap terjaga agar repositori git tidak kotor.
   * Hanya subfolder unggahan (`applicant-documents`, `sk-imports`, `livewire-tmp`, `exports`) yang dihapus.
3. **Audit Trail**:
   * Setiap eksekusi reset menghasilkan satu entri catatan pada tabel `audit_events` dengan action:
     `Mereset seluruh data operasional dan file unggahan sistem` beserta rincian jumlah tabel dan file yang dibersihkan.
