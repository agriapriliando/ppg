<?php

namespace App\Support;

use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SkReimportExport
{
    /** @param array<string, int|string|null> $filters */
    public static function validateSelection(User $actor, array $filters): void
    {
        $period = AdmissionPeriod::query()->whereKey($filters['period_id'] ?? null)->first();
        if ($period === null || $period->state === PeriodState::Archived) {
            throw ValidationException::withMessages(['period_id' => 'Pilih satu periode yang belum diarsipkan untuk ekspor impor ulang SK.']);
        }
        $query = ParticipantExport::query($actor, $filters);
        $count = (clone $query)->count();
        if ($count < 1 || $count > config('pmb.import_max_rows')) {
            throw ValidationException::withMessages(['format' => 'Ekspor impor ulang SK harus berisi 1–'.config('pmb.import_max_rows').' peserta. Sesuaikan filter periode, operator, atau status.']);
        }
        if ((clone $query)->where(fn ($query) => $query->whereNull('official_participant_id')->orWhere('official_participant_id', ''))->exists()) {
            throw ValidationException::withMessages(['format' => 'Ada peserta tanpa ID peserta SK (PEG ID). Lengkapi ID peserta terlebih dahulu agar file dapat digunakan untuk impor ulang.']);
        }
    }

    /** @param array<string, int|string|null> $filters */
    public static function write(User $actor, array $filters, string $path): int
    {
        self::validateSelection($actor, $filters);
        $book = SkImportWorkbook::make();
        try {
            $sheet = $book->getSheetByName('Peserta_SK');
            if ($sheet === null) {
                throw new \RuntimeException('Sheet template impor SK tidak tersedia.');
            }
            $sheet->setCellValueExplicit('J1', 'Kota/Kab', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('K1', 'Provinsi', DataType::TYPE_STRING);
            $sheet->getStyle('J:K')->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('J1:K1')->getFont()->setBold(true);
            $sheet->getColumnDimension('J')->setWidth(35);
            $sheet->getColumnDimension('K')->setWidth(35);
            $regionNames = [];
            $reference = $book->getSheetByName('Referensi_Wilayah');
            if ($reference !== null && $reference->getHighestDataRow() >= 2) {
                foreach ($reference->rangeToArray('A2:C'.$reference->getHighestDataRow()) as $region) {
                    $regionNames[(string) $region[0]] = [$region[1], $region[2]];
                }
            }
            $index = 2;
            foreach (ParticipantExport::query($actor, $filters)->with('assignedOperator')->orderBy('sk_number')->orderBy('sk_order')->orderBy('id')->lazy(250) as $participant) {
                $operator = $participant->assignedOperator;
                $operatorId = $operator?->role === UserRole::Operator && $operator->account_state === AccountState::Active ? $operator->login_id : '';
                $names = $participant->origin_city_code ? ($regionNames[$participant->origin_city_code] ?? [$participant->origin_city_name, $participant->origin_province_name]) : ['', ''];
                $values = [$participant->sk_number, $participant->sk_date->format('Y-m-d'), (string) $participant->sk_order,
                    $participant->name_on_sk, $participant->official_participant_id, $operatorId,
                    $participant->internal_note, $participant->origin_city_code, $participant->phone_number, ...$names];
                foreach ($values as $column => $value) {
                    $sheet->setCellValueExplicit([$column + 1, $index], (string) ($value ?? ''), DataType::TYPE_STRING);
                }
                $index++;
            }
            $sheet->setAutoFilter('A1:K'.($index - 1));
            $period = AdmissionPeriod::query()->whereKey($filters['period_id'])->firstOrFail();
            $source = $book->createSheet();
            $source->setTitle('Periode_Sumber');
            foreach ([['id_periode', (string) $period->id], ['kode_periode', $period->code]] as $row => $values) {
                foreach ($values as $column => $value) {
                    $source->setCellValueExplicit([$column + 1, $row + 1], $value, DataType::TYPE_STRING);
                }
            }
            $source->getColumnDimension('A')->setWidth(25);
            $source->getColumnDimension('B')->setWidth(45);
            $guide = $book->getSheetByName('Panduan');
            if ($guide !== null) {
                $row = $guide->getHighestDataRow() + 1;
                foreach ([
                    'Ekspor data peserta SK: pilih periode tujuan yang sama dengan periode sumber saat impor ulang. Gunakan mode Perbarui saja.',
                    'Periode sumber: '.$period->name.' (ID '.$period->id.'). Jangan mengubah atau menghapus sheet Periode_Sumber; digunakan untuk mencegah salah periode tujuan.',
                    'ID peserta SK adalah PEG ID resmi, bukan ID internal database. Jangan mengubah ID jika ingin memperbarui peserta yang sama.',
                    'ID operator dikosongkan bila akun operator tidak aktif/tidak tersedia; isian kosong mempertahankan penugasan lama.',
                    'Referensi wilayah mengikuti dataset aktif. Kode lama yang tidak lagi berlaku dapat ditandai untuk perbaikan saat pratinjau.',
                    'Kolom J Kota/Kab dan K Provinsi adalah informasi nama wilayah berdasarkan kode asal. Impor hanya memakai kode_kota_kabupaten_asal; perubahan pada kedua kolom nama tidak mengubah wilayah peserta.',
                    'Peserta terikat klaim dan perubahan penugasan tetap mengikuti perlindungan impor. File ekspor tidak melewati pemeriksaan konflik.',
                ] as $text) {
                    $guide->setCellValueExplicit('A'.$row++, $text, DataType::TYPE_STRING);
                }
            }
            (new Xlsx($book))->save($path);
            if (filesize($path) > config('pmb.import_max_kilobytes') * 1024) {
                throw new \RuntimeException('File melebihi batas unggahan impor. Persempit filter ekspor.');
            }

            return $index - 2;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
