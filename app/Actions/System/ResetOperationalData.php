<?php

namespace App\Actions\System;

use App\Enums\UserRole;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResetOperationalData
{
    /**
     * Daftar tabel data operasional / transaksional yang akan dibersihkan.
     * Diurutkan dari tabel anak ke tabel induk.
     *
     * @var list<string>
     */
    protected array $operationalTables = [
        'application_submission_documents',
        'applicant_draft_operations',
        'applicant_upload_batches',
        'application_reviews',
        'application_submissions',
        'application_ownership_transfers',
        'applications',
        'applicant_documents',
        'document_deletion_tasks',
        'origin_education',
        'applicant_biodata',
        'applicant_workspaces',
        'whatsapp_verifications',
        'claim_responses',
        'credential_issuances',
        'consent_records',
        'administrative_cases',
        'sk_claims',
        'assignment_events',
        'sk_import_rows',
        'sk_participants',
        'sk_imports',
        'export_runs',
        'social_accounts',
        'sessions',
        'password_reset_tokens',
        'failed_jobs',
        'job_batches',
        'jobs',
    ];

    /**
     * Daftar tabel periode penerimaan dan konfigurasi turunannya.
     *
     * @var list<string>
     */
    protected array $periodTables = [
        'admission_sequences',
        'period_operators',
        'document_policy_versions',
        'admission_config_versions',
        'admission_periods',
    ];

    /**
     * Direktori file unggahan di disk local (storage/app/private).
     *
     * @var list<string>
     */
    protected array $storageDirectories = [
        'applicant-documents',
        'sk-imports',
        'livewire-tmp',
        'exports',
    ];

    /**
     * Menjalankan pembersihan seluruh data operasional dan file unggahan,
     * dengan tetap mempertahankan data referensi secara utuh.
     *
     * @param array{
     *     keep_periods?: bool,
     *     keep_users?: bool,
     *     keep_audit?: bool,
     * } $options
     * @return array{
     *     deleted_records: array<string, int>,
     *     deleted_files: array<string, int>,
     *     retained_references: array<string, int>,
     * }
     */
    public function handle(array $options = []): array
    {
        $keepPeriods = (bool) ($options['keep_periods'] ?? false);
        $keepUsers = (bool) ($options['keep_users'] ?? false);
        $keepAudit = (bool) ($options['keep_audit'] ?? false);

        $deletedRecords = [];
        $deletedFiles = [];

        // 1. Bersihkan file unggahan fisik
        $deletedFiles = $this->clearUploadedFiles();

        // 2. Bersihkan tabel database
        $this->disableForeignKeyChecks();

        try {
            // 1. Bersihkan data operasional pendaftaran dan kepesertaan
            foreach ($this->operationalTables as $table) {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    DB::table($table)->delete();
                    $deletedRecords[$table] = $count;
                }
            }

            // 2. Bersihkan data periode jika tidak dipilih untuk dipertahankan
            if (! $keepPeriods) {
                foreach ($this->periodTables as $table) {
                    if (Schema::hasTable($table)) {
                        $count = DB::table($table)->count();
                        DB::table($table)->delete();
                        $deletedRecords[$table] = $count;
                    }
                }
            } else {
                // Jika periode dipertahankan, reset sequence dan status pengunciannya
                if (Schema::hasTable('admission_sequences')) {
                    DB::table('admission_sequences')->update(['last_number' => 0]);
                }
                if (Schema::hasTable('admission_periods')) {
                    DB::table('admission_periods')->update([
                        'lock_version' => 0,
                        'manual_closed_at' => null,
                    ]);
                }
            }

            // 3. Tangani riwayat audit sebelum menghapus pengguna
            if (Schema::hasTable('audit_events')) {
                if (! $keepAudit) {
                    $count = DB::table('audit_events')->count();
                    DB::table('audit_events')->delete();
                    $deletedRecords['audit_events'] = $count;
                } else {
                    // Jika audit dipertahankan, null-kan relasi actor_id pendaftar agar foreign key tidak terkendala
                    $applicantIds = DB::table('users')->where('role', UserRole::Applicant->value)->pluck('id');
                    DB::table('audit_events')->whereIn('actor_id', $applicantIds)->update(['actor_id' => null]);
                }
            }

            // 4. Bersihkan akun pendaftar (hanya role pendaftar_ppg, akun staf Admin & Operator tetap aman)
            if (! $keepUsers && Schema::hasTable('users')) {
                $count = DB::table('users')->where('role', UserRole::Applicant->value)->count();
                DB::table('users')->where('role', UserRole::Applicant->value)->delete();
                $deletedRecords['users_applicant'] = $count;
            }
        } finally {
            $this->enableForeignKeyChecks();
        }

        // 3. Bersihkan cache aplikasi
        Cache::flush();

        // 4. Catat riwayat audit bahwa reset sistem telah dijalankan
        if (Schema::hasTable('audit_events')) {
            AuditEvent::create([
                'actor_id' => null,
                'actor_role_snapshot' => 'system_console',
                'action' => 'Mereset seluruh data operasional dan file unggahan sistem',
                'subject_type' => 'system',
                'subject_id' => 'pmb_reset',
                'safe_changes' => [
                    'reset_at' => now()->toIso8601String(),
                    'keep_periods' => $keepPeriods,
                    'keep_users' => $keepUsers,
                    'deleted_tables_count' => count($deletedRecords),
                    'total_deleted_records' => array_sum($deletedRecords),
                    'total_deleted_files' => array_sum($deletedFiles),
                ],
                'reason' => 'Perintah reset data operasional dijalankan melalui sistem',
                'request_id' => (string) Str::uuid(),
            ]);
        }

        // 5. Kumpulkan ringkasan data referensi yang dipertahankan
        $retainedReferences = $this->collectRetainedReferences();

        return [
            'deleted_records' => $deletedRecords,
            'deleted_files' => $deletedFiles,
            'retained_references' => $retainedReferences,
        ];
    }

    /**
     * Menghapus seluruh file fisik di direktori unggahan server.
     *
     * @return array<string, int>
     */
    protected function clearUploadedFiles(): array
    {
        $deletedFiles = [];

        foreach ($this->storageDirectories as $directory) {
            $count = 0;
            if (Storage::disk('local')->exists($directory)) {
                $files = Storage::disk('local')->allFiles($directory);
                $count = count($files);
                Storage::disk('local')->deleteDirectory($directory);
            }
            $deletedFiles[$directory] = $count;
        }

        // Pastikan juga membersihkan file unggahan publik jika ada
        if (Storage::disk('public')->exists('')) {
            $publicFiles = Storage::disk('public')->allFiles('');
            $nonGitignoreFiles = array_filter($publicFiles, fn ($f) => basename($f) !== '.gitignore');
            if (! empty($nonGitignoreFiles)) {
                Storage::disk('public')->delete($nonGitignoreFiles);
                $deletedFiles['public_uploads'] = count($nonGitignoreFiles);
            }
        }

        return $deletedFiles;
    }

    /**
     * Mengumpulkan statistik data referensi yang tetap utuh.
     *
     * @return array<string, int>
     */
    protected function collectRetainedReferences(): array
    {
        $references = [
            'reference_regions' => 'Wilayah Administrasi (Provinsi/Kab/Kec)',
            'reference_options' => 'Opsi Biodata (Agama/Gender/dll)',
            'reference_pt' => 'Perguruan Tinggi',
            'reference_prodi' => 'Program Studi',
            'reference_datasets' => 'Dataset Induk Referensi',
            'consent_texts' => 'Naskah Klausul Persetujuan',
            'portal_quick_links' => 'Tautan Cepat Portal Mahasiswa',
            'application_settings' => 'Pengaturan Konfigurasi Aplikasi',
        ];

        $result = [];
        foreach ($references as $table => $label) {
            if (Schema::hasTable($table)) {
                $result[$label] = DB::table($table)->count();
            }
        }

        if (Schema::hasTable('users')) {
            $staffCount = DB::table('users')
                ->whereIn('role', [UserRole::Administrator->value, UserRole::Operator->value])
                ->count();
            $result['Akun Staf (Administrator & Operator)'] = $staffCount;
        }

        return $result;
    }

    /**
     * Nonaktifkan foreign key checks sesuai DBMS.
     */
    protected function disableForeignKeyChecks(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
    }

    /**
     * Aktifkan kembali foreign key checks sesuai DBMS.
     */
    protected function enableForeignKeyChecks(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }
}
