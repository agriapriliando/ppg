<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\ReviewApplication;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\ReferenceOption;
use App\Models\ReferenceRegion;
use App\Models\SkClaim;
use App\Models\WhatsappVerification;
use App\Support\ApplicantFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationReviewController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', 'in:draf,terkirim,diverifikasi,perbaikan,diterima_lengkap'], 'search' => ['nullable', 'string', 'max:100'], 'period_id' => ['nullable', 'integer', 'exists:admission_periods,id']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $query = Application::query()->whereHas('participant', fn ($participants) => $participants->visibleTo($actor))->with(['participant.assignedOperator', 'period'])->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']))->when(! empty($data['period_id']), fn ($query) => $query->where('admission_period_id', $data['period_id']))->when(! empty($data['search']), fn ($query) => $query->where(fn ($sub) => $sub->where('application_number', 'like', '%'.$data['search'].'%')->orWhereHas('participant', fn ($participants) => $participants->where('name_on_sk', 'like', '%'.$data['search'].'%'))));

        return view('portal.review-queue', ['applications' => $query->orderBy('submitted_at')->orderBy('id')->paginate(25)->withQueryString(), 'periods' => AdmissionPeriod::query()->orderByDesc('id')->get()]);
    }

    public function show(Application $application): View
    {
        abort_unless(Gate::allows('review', $application), 404);
        $application->load(['participant.assignedOperator', 'workspace.biodata', 'workspace.education', 'period', 'reviews', 'submissions.configVersion']);
        $datasetId = $application->workspace->education?->reference_dataset_id;
        $display = [];
        $values = ApplicantFields::fromWorkspace($application->workspace);
        $options = ReferenceOption::query()->where('dataset_id', $datasetId)->get();
        $regions = ReferenceRegion::query()->where('dataset_id', $datasetId)->whereIn('code', [$values['province_code'] ?? '', $values['city_code'] ?? '', $values['district_code'] ?? ''])->pluck('name', 'code');
        foreach (ApplicantFields::definitions() as $key => $field) {
            if ($field['type'] === 'select') {
                $display[$key] = $options->where('group', $field['reference'] ?? '')->firstWhere('code', $values[$key] ?? null)->label ?? '—';
            }
            if ($field['type'] === 'region') {
                $display[$key] = $regions[$values[$key] ?? ''] ?? '—';
            }
        }

        return view('portal.review-detail', ['application' => $application, 'fields' => ApplicantFields::definitions(), 'data' => $values, 'display' => $display, 'claim' => SkClaim::query()->where('candidate_sk_participant_id', $application->sk_participant_id)->where('status', 'approved')->first(), 'verified' => WhatsappVerification::query()->where('workspace_id', $application->workspace_id)->where('number', $application->workspace->biodata?->whatsapp_number)->whereNull('invalidated_at')->exists()]);
    }

    public function print(Application $application): View
    {
        abort_unless(Gate::allows('review', $application), 404);
        $application->load(['participant.assignedOperator', 'workspace.biodata', 'workspace.education', 'period', 'workspace.availableDocuments']);
        $datasetId = $application->workspace->education?->reference_dataset_id;
        $display = [];
        $values = ApplicantFields::fromWorkspace($application->workspace);
        $options = ReferenceOption::query()->where('dataset_id', $datasetId)->get();
        $regions = ReferenceRegion::query()->where('dataset_id', $datasetId)->whereIn('code', [$values['province_code'] ?? '', $values['city_code'] ?? '', $values['district_code'] ?? ''])->pluck('name', 'code');
        foreach (ApplicantFields::definitions() as $key => $field) {
            if ($field['type'] === 'select') {
                $display[$key] = $options->where('group', $field['reference'] ?? '')->firstWhere('code', $values[$key] ?? null)->label ?? '—';
            }
            if ($field['type'] === 'region') {
                $display[$key] = $regions[$values[$key] ?? ''] ?? '—';
            }
        }
        $documents = $application->workspace->availableDocuments()->orderBy('created_at')->get();

        return view('portal.application-print', [
            'application' => $application,
            'values' => $values,
            'display' => $display,
            'documents' => $documents,
            'verified' => WhatsappVerification::query()->where('workspace_id', $application->workspace_id)->where('number', $application->workspace->biodata?->whatsapp_number)->whereNull('invalidated_at')->exists(),
        ]);
    }

    public function update(Request $request, Application $application, ReviewApplication $review): RedirectResponse
    {
        Gate::authorize('review', $application);
        $request->validate(['version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:verify,correction,accept,reopen']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $review->handle($actor, $application, $request->integer('version'), $request->string('action')->toString(), $request->only(['identity_checked', 'sk_checked', 'data_checked', 'field_keys', 'public_note']));

        return back()->with('status', 'Hasil pemeriksaan berhasil dicatat.');
    }
}
