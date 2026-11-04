<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Documents\PublishDocumentPolicy;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantDocument;
use App\Models\DocumentPolicyVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentPolicyController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor !== null && ($actor->isAdministrator() || $actor->role === UserRole::Operator), 403);

        $periods = AdmissionPeriod::query()->orderByDesc('id')->get();
        $selectedPeriodId = $request->integer('period_id') ?: $periods->first()?->id;

        $selectedPeriod = $selectedPeriodId ? $periods->firstWhere('id', $selectedPeriodId) : null;
        if ($selectedPeriod === null && $periods->isNotEmpty()) {
            $selectedPeriod = $periods->first();
        }

        if ($selectedPeriod === null) {
            return view('admin.document-policies', [
                'periods' => $periods,
                'selectedPeriod' => null,
                'currentPolicy' => null,
                'history' => collect(),
                'supportedTypes' => config('document_policy.supported_types', []),
            ]);
        }

        $currentPolicy = DocumentPolicyVersion::resolveForPeriod($selectedPeriod);
        $history = DocumentPolicyVersion::query()
            ->where('admission_period_id', $selectedPeriod->id)
            ->with('creator')
            ->orderByDesc('version')
            ->get();

        $supportedTypes = config('document_policy.supported_types', []);

        return view('admin.document-policies', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'currentPolicy' => $currentPolicy,
            'history' => $history,
            'supportedTypes' => $supportedTypes,
        ]);
    }

    public function store(Request $request, PublishDocumentPolicy $publisher): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null && ($actor->isAdministrator() || $actor->role === UserRole::Operator), 403);

        $period = AdmissionPeriod::findOrFail($request->integer('admission_period_id'));

        $input = $request->only([
            'allowed_extensions',
            'max_file_size_bytes',
            'workspace_quota_bytes',
            'general_instructions',
        ]);

        if ($request->filled('max_file_size_mb')) {
            $input['max_file_size_bytes'] = (int) round($request->float('max_file_size_mb') * 1024 * 1024);
        }
        if ($request->filled('workspace_quota_mb')) {
            $input['workspace_quota_bytes'] = (int) round($request->float('workspace_quota_mb') * 1024 * 1024);
        } else {
            $input['workspace_quota_bytes'] = null;
        }

        $newPolicy = $publisher->handle($actor, $period, $input);

        return redirect()
            ->route('admin.document-policies.index', ['period_id' => $period->id])
            ->with('status', "Aturan berkas versi {$newPolicy->version} berhasil dipublikasikan untuk periode {$period->name}.");
    }

    public function uploads(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor !== null && ($actor->isAdministrator() || $actor->role === UserRole::Operator), 403);

        $periods = AdmissionPeriod::query()->orderByDesc('id')->get();

        $query = ApplicantDocument::query()
            ->where('status', ApplicantDocument::STATUS_AVAILABLE)
            ->with(['period', 'owner', 'uploader', 'workspace.biodata', 'workspace.application.participant']);

        if ($request->filled('period_id')) {
            $query->where('admission_period_id', $request->integer('period_id'));
        }

        if ($request->filled('extension')) {
            $query->where('extension', strtolower($request->string('extension')->toString()));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($sub) use ($search): void {
                $sub->where('display_name', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('workspace.biodata', fn ($b) => $b->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('workspace.application', fn ($a) => $a->where('application_number', 'like', "%{$search}%"));
            });
        }

        $documents = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.document-uploads', [
            'periods' => $periods,
            'documents' => $documents,
            'filters' => $request->only(['period_id', 'extension', 'search']),
        ]);
    }
}
