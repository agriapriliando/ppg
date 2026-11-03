<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ApplicantDocument;
use App\Models\ApplicantWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminWorkspaceDocumentController extends Controller
{
    public function show(Request $request, ApplicantWorkspace $workspace): View
    {
        $actor = $request->user();
        abort_unless($actor !== null && ($actor->isAdministrator() || $actor->role === UserRole::Operator), 403);

        if ($actor->role === UserRole::Operator && $workspace->application === null) {
            abort(403);
        }

        $workspace->load(['period', 'biodata', 'application.participant']);

        return view('admin.workspace-documents', [
            'workspace' => $workspace,
            'period' => $workspace->period,
            'application' => $workspace->application,
            'user' => $workspace->user ?? $workspace->user()->first(),
        ]);
    }

    public function preview(Request $request, ApplicantWorkspace $workspace, ApplicantDocument $document): BinaryFileResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null && ($actor->isAdministrator() || $actor->role === UserRole::Operator), 403);
        abort_if($document->workspace_id !== $workspace->id, 404);
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

    public function download(Request $request, ApplicantWorkspace $workspace, ApplicantDocument $document): BinaryFileResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null && ($actor->isAdministrator() || $actor->role === UserRole::Operator), 403);
        abort_if($document->workspace_id !== $workspace->id, 404);
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
