<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Models\AdmissionPeriod;
use App\Models\PeriodDocument;
use App\Support\DocumentByteRanges;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PeriodDocumentController extends Controller
{
    public function preview(AdmissionPeriod $period, PeriodDocument $document, RecordAudit $audit): BinaryFileResponse
    {
        $this->authorizeFile($period, $document);
        abort_unless($document->isPreviewable(), 415, 'Format ini hanya dapat diunduh.');
        if (! $document->isVideo() || ! request()->headers->has('Range') || str_starts_with((string) request()->header('Range'), 'bytes=0-')) {
            $audit->handle(auth()->user(), 'Melihat berkas periode', $document);
        }
        DocumentByteRanges::guard(Storage::disk($document->disk)->path($document->object_key), $document->isVideo());

        return response()->file(Storage::disk($document->disk)->path($document->object_key), [
            'Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(AdmissionPeriod $period, PeriodDocument $document, RecordAudit $audit): BinaryFileResponse
    {
        $this->authorizeFile($period, $document);
        $audit->handle(auth()->user(), 'Mengunduh berkas periode', $document);

        return response()->download(Storage::disk($document->disk)->path($document->object_key), $document->sanitized_download_name, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeFile(AdmissionPeriod $period, PeriodDocument $document): void
    {
        abort_unless($document->admission_period_id === $period->id, 404);
        Gate::authorize('view', $document);
        abort_unless(Storage::disk($document->disk)->exists($document->object_key), 404);
    }
}
