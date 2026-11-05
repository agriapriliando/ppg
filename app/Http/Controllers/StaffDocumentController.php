<?php

namespace App\Http\Controllers;

use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Policies\ApplicantDocumentPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffDocumentController extends Controller
{
    public function show(Request $request, Application $application): View
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        Gate::authorize('review', $application);

        $application->load(['participant.assignedOperator', 'period', 'workspace.biodata', 'submissions.documents.document']);
        $workspace = $application->workspace;
        abort_unless($workspace instanceof ApplicantWorkspace, 404);

        abort_unless(app(ApplicantDocumentPolicy::class)->canAccessWorkspace($user, $workspace, false), 403);

        return view('portal.staff-documents', [
            'application' => $application,
            'workspace' => $workspace,
            'period' => $application->period,
            'participant' => $application->participant,
        ]);
    }

    public function preview(Request $request, Application $application, ApplicantDocument $document): BinaryFileResponse
    {
        abort_if($document->workspace_id !== $application->workspace_id, 404);
        Gate::authorize('view', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->object_key), 404);

        $path = $disk->path($document->object_key);

        return response()->file($path, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $request, Application $application, ApplicantDocument $document): BinaryFileResponse
    {
        abort_if($document->workspace_id !== $application->workspace_id, 404);
        Gate::authorize('download', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->object_key), 404);

        $path = $disk->path($document->object_key);

        return response()->download($path, $document->sanitized_download_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
