<?php

namespace App\Console\Commands;

use App\Actions\System\ResetOperationalData;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pmb:reset-data {--keep-periods : Pertahankan periode penerimaan dan konfigurasi dasarnya} {--keep-users : Pertahankan akun pendaftar yang sudah ada} {--keep-audit : Jangan bersihkan riwayat log audit lama} {--seed-demo : Buat ulang akun demo pendaftar setelah reset (khusus local/testing)} {--force : Jalankan langsung tanpa konfirmasi interaktif}')]
#[Description('Mereset seluruh data operasional pendaftaran, kepesertaan SK, dan file unggahan kecuali data referensi')]
class ResetOperationalDataCommand extends Command
{
    /**
     * Eksekusi perintah console.
     */
    public function handle(ResetOperationalData $resetOperationalData): int
    {
        $keepPeriods = (bool) $this->option('keep-periods');
        $keepUsers = (bool) $this->option('keep-users');
        $keepAudit = (bool) $this->option('keep-audit');
        $seedDemo = (bool) $this->option('seed-demo');
        $force = (bool) $this->option('force');

        $this->newLine();
        $this->components->warn('PERINGATAN: Tindakan ini akan mereset data operasional dan file fisik unggahan.');
        $this->line('  - <fg=red;options=bold>Data yang DIHAPUS:</> Pendaftaran, biodata, verifikasi berkas, klaim SK, peserta SK, impor SK, dan dokumen fisik unggahan.');
        $this->line('  - <fg=green;options=bold>Data yang DIPERTAHANKAN:</> Data referensi wilayah (7.800+ baris), opsi biodata, PT, prodi, klausul persetujuan, tautan cepat, pengaturan sistem, dan akun staf (Admin/Operator).');

        if (! $force) {
            $confirmed = $this->confirm('Apakah Anda yakin ingin melanjutkan proses reset ini?', false);
            if (! $confirmed) {
                $this->components->info('Proses reset data dibatalkan.');

                return self::SUCCESS;
            }
        }

        $this->components->info('Memulai pembersihan file unggahan dan data operasional...');

        $result = $resetOperationalData->handle([
            'keep_periods' => $keepPeriods,
            'keep_users' => $keepUsers,
            'keep_audit' => $keepAudit,
        ]);

        $this->newLine();

        // 1. Tabel File Unggahan Terhapus
        $fileRows = [];
        $totalFiles = 0;
        foreach ($result['deleted_files'] as $directory => $count) {
            $fileRows[] = [$directory, $count.' file'];
            $totalFiles += $count;
        }
        $this->components->twoColumnDetail('<fg=yellow;options=bold>File Fisik Unggahan Terhapus</>', "<fg=yellow;options=bold>{$totalFiles} file</>");
        $this->table(['Direktori Penyimpanan', 'Jumlah File Dihapus'], $fileRows);

        // 2. Tabel Data Operasional Terhapus
        $recordRows = [];
        $totalRecords = 0;
        foreach ($result['deleted_records'] as $table => $count) {
            if ($count > 0) {
                $recordRows[] = [$table, $count.' baris'];
                $totalRecords += $count;
            }
        }
        $this->newLine();
        $this->components->twoColumnDetail('<fg=red;options=bold>Data Operasional Dibersihkan</>', "<fg=red;options=bold>{$totalRecords} record</>");
        if (! empty($recordRows)) {
            $this->table(['Tabel Database', 'Jumlah Baris Dihapus'], $recordRows);
        } else {
            $this->line('  (Semua tabel operasional sudah dalam keadaan kosong)');
        }

        // 3. Tabel Data Referensi Dipertahankan
        $referenceRows = [];
        foreach ($result['retained_references'] as $label => $count) {
            $referenceRows[] = [$label, $count.' data'];
        }
        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Data Referensi Dipertahankan (Aman)</>', '<fg=green;options=bold>Utuh</>');
        $this->table(['Komponen Referensi', 'Status / Jumlah Data'], $referenceRows);

        // 4. Opsional: Seed Akun Demo jika diminta
        if ($seedDemo) {
            if (app()->environment(['local', 'testing'])) {
                $this->newLine();
                $this->components->info('Menyiapkan ulang akun demo...');
                $this->call('db:seed', ['--class' => DemoAccountSeeder::class]);
            } else {
                $this->components->warn('Opsi --seed-demo diabaikan karena hanya diizinkan pada lingkungan local atau testing.');
            }
        }

        $this->newLine();
        $this->components->info('Berhasil! Seluruh data operasional dan file unggahan telah direset.');
        $this->line('Sistem siap digunakan kembali dengan data referensi dan akun staf yang tetap utuh.');
        $this->newLine();

        return self::SUCCESS;
    }
}
