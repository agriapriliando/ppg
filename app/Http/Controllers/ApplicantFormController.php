<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\SaveApplicantForm;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\Application;
use App\Models\ConsentText;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\ReferenceProdi;
use App\Models\ReferencePt;
use App\Models\ReferenceRegion;
use App\Support\ApplicantFields;
use App\Support\FormInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicantFormController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.application-periods', ['periods' => AdmissionPeriod::query()->where('state', 'published')->orderByDesc('id')->get(), 'applications' => Application::query()->where('user_id', $request->user()?->id)->with('period')->orderByDesc('id')->get()]);
    }

    public function edit(Request $request, AdmissionPeriod $period): View
    {
        $workspace = ApplicantWorkspace::query()->with(['biodata', 'education', 'application.participant'])->where('user_id', $request->user()?->id)->where('admission_period_id', $period->id)->first();
        abort_if($period->state !== PeriodState::Published && $workspace === null, 404);
        $dataset = ReferenceDataset::query()->where('state', 'active')->latest('id')->first();
        if ($workspace !== null && (($workspace->application !== null && ! $workspace->application->status->editable()) || $period->state === PeriodState::Archived) && $workspace->education?->reference_dataset_id !== null) {
            $dataset = ReferenceDataset::query()->whereKey($workspace->education->reference_dataset_id)->first();
        }
        $data = $workspace ? ApplicantFields::fromWorkspace($workspace) : [];

        return view('portal.application-form', [
            'period' => $period, 'workspace' => $workspace, 'application' => $workspace?->application, 'data' => $data,
            'fields' => ApplicantFields::definitions(), 'dataset' => $dataset,
            'options' => ReferenceOption::query()->where('dataset_id', $dataset?->id)->get()->groupBy('group'),
            'regions' => ReferenceRegion::query()->where('dataset_id', $dataset?->id)->whereIn('code', [FormInput::old('province_code', $data['province_code'] ?? ''), FormInput::old('city_code', $data['city_code'] ?? ''), FormInput::old('district_code', $data['district_code'] ?? '')])->get(),
            'ptLabel' => ReferencePt::query()->where('dataset_id', $dataset?->id)->where('external_id', FormInput::old('pt_external_id', $data['pt_external_id'] ?? ''))->value('name') ?? '',
            'prodiLabel' => ReferenceProdi::query()->where('dataset_id', $dataset?->id)->where('external_id', FormInput::old('prodi_external_id', $data['prodi_external_id'] ?? ''))->value('name') ?? '',
            'consent' => ConsentText::query()->where('purpose', 'submission')->latest('id')->first(),
        ]);
    }

    public function update(Request $request, AdmissionPeriod $period, SaveApplicantForm $save): RedirectResponse
    {
        $request->validate(['version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:draft,submit']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $keys = [...array_keys(ApplicantFields::definitions()), 'pt_external_id', 'prodi_external_id', 'consent', 'consent_id'];
        $submit = $request->input('action') === 'submit';
        $save->handle($actor, $period->id, $request->integer('version'), $request->only($keys), $submit);

        return redirect()->route($submit ? 'applicant.application.status' : 'applicant.form.edit', $period)->with('status', $submit ? 'Pendaftaran telah dikirim. Pantau hasil pemeriksaan operator.' : 'Draf berhasil disimpan di server.');
    }

    public function status(Request $request, AdmissionPeriod $period): View
    {
        $application = Application::query()->where('user_id', $request->user()?->id)->where('admission_period_id', $period->id)->with(['period', 'participant.assignedOperator'])->first();
        if ($application !== null) {
            $application->load(['reviews' => fn ($query) => $query->where('ownership_epoch', $application->ownership_epoch)]);
        }

        return view('portal.application-status', ['period' => $period, 'application' => $application]);
    }

    public function proof(Request $request, AdmissionPeriod $period): View
    {
        $application = Application::query()->where('user_id', $request->user()?->id)->where('admission_period_id', $period->id)->whereNotNull('submitted_at')->where(fn ($query) => $query->where('ownership_epoch', 0)->orWhereNotNull('current_owner_submitted_at'))->with(['period', 'participant.assignedOperator', 'workspace.biodata', 'workspace.education'])->firstOrFail();

        return view('portal.application-proof', ['application' => $application]);
    }
}
