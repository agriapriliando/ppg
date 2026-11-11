<?php

namespace App\Http\Controllers;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Policies\ApplicantDocumentPolicy;
use App\Support\DocumentByteRanges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicantDocumentController extends Controller
{
    public function index(Request $request, AdmissionPeriod $period): View
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        // If period is not published and user doesn't already have a workspace, 404
        $workspace = ApplicantWorkspace::query()
            ->where('user_id', $user->id)
            ->where('admission_period_id', $period->id)
            ->first();

        if ($workspace === null) {
            abort_if($period->state !== PeriodState::Published, 404);
            $workspace = ApplicantWorkspace::create([
                'user_id' => $user->id,
                'admission_period_id' => $period->id,
            ]);
        }

        abort_unless(app(ApplicantDocumentPolicy::class)->canAccessWorkspace($user, $workspace, false), 403);

        return view('portal.applicant-documents', [
            'period' => $period,
            'workspace' => $workspace,
        ]);
    }

    public function preview(Request $request, AdmissionPeriod $period, ApplicantDocument $document): BinaryFileResponse
    {
        abort_if($document->admission_period_id !== $period->id, 404);
        Gate::authorize('view', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->object_key), 404);

        $path = $disk->path($document->object_key);
        DocumentByteRanges::guard($path, $document->isVideo());

        return response()->file($path, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $request, AdmissionPeriod $period, ApplicantDocument $document): BinaryFileResponse
    {
        abort_if($document->admission_period_id !== $period->id, 404);
        Gate::authorize('download', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->object_key), 404);

        $path = $disk->path($document->object_key);
        DocumentByteRanges::guard($path, $document->isVideo());

        return response()->download($path, $document->sanitized_download_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
