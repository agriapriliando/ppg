<?php

namespace App\Jobs;

use App\Actions\Imports\ValidateSkImport;
use App\Exceptions\InvalidSkImportWorkbook;
use App\Models\SkImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Throwable;

class ValidateSkImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $importId) {}

    public function handle(ValidateSkImport $validate): void
    {
        $import = SkImport::findOrFail($this->importId);
        if ($import->state !== 'uploaded') {
            return;
        }
        $import->state = 'validating';
        $import->save();
        try {
            $validate->handle($import);
        } catch (\RuntimeException|Exception $exception) {
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $import = SkImport::find($this->importId);
        if ($import !== null && $import->state !== 'committed') {
            $import->state = 'failed';
            $import->safe_summary = $exception instanceof InvalidSkImportWorkbook
                ? $exception->getMessage()
                : 'Berkas tidak dapat divalidasi. Periksa struktur template, ukuran, dan isi workbook lalu unggah ulang.';
            $import->save();
        }
    }
}
