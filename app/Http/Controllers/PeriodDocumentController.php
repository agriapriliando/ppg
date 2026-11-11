<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\PeriodDocument;
use App\Support\DocumentByteRanges;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PeriodDocumentController extends Controller
{
    public function __construct(private RecordAudit $audit) {}

    public function index(AdmissionPeriod $period): View
    {
        return view('public.period-documents', ['period' => $period, 'documents' => $period->periodDocuments()->publicFiles()->orderBy('display_name')->orderBy('id')->get()]);
    }

    public function publicPreview(AdmissionPeriod $period, PeriodDocument $document): BinaryFileResponse
    {
        return $this->file($period, $document, true, true);
    }

    public function publicDownload(AdmissionPeriod $period, PeriodDocument $document): BinaryFileResponse
    {
        return $this->file($period, $document, false, true);
    }

    public function applicantPreview(AdmissionPeriod $period, PeriodDocument $document): BinaryFileResponse
    {
        return $this->file($period, $document, true, false);
    }

    public function applicantDownload(AdmissionPeriod $period, PeriodDocument $document): BinaryFileResponse
    {
        return $this->file($period, $document, false, false);
    }

    private function file(AdmissionPeriod $period, PeriodDocument $document, bool $preview, bool $public): BinaryFileResponse
    {
        abort_unless($document->admission_period_id === $period->id, 404);
        if ($public) {
            abort_unless(Gate::allows('viewPublic', $document), 404);
        } else {
            Gate::authorize('view', $document);
        }
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->object_key), 404);
        abort_if($preview && ! $document->isPreviewable(), 415, 'Format ini hanya dapat diunduh.');
        if (! $document->isVideo() || ! request()->headers->has('Range') || str_starts_with((string) request()->header('Range'), 'bytes=0-')) {
            $this->audit->handle(auth()->user(), $preview ? 'Melihat berkas informasi periode' : 'Mengunduh berkas informasi periode', $document, ['public' => $public, 'period_id' => $period->id]);
        }
        DocumentByteRanges::guard($disk->path($document->object_key), $document->isVideo());
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];

        return $preview ? response()->file($disk->path($document->object_key), ['Content-Type' => $document->mime_type, ...$headers])
            : response()->download($disk->path($document->object_key), $document->sanitized_download_name, $headers);
    }
}
