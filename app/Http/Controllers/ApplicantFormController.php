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
use App\Models\SkClaim;
use App\Support\ApplicantFields;
use App\Support\FormInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicantFormController extends Controller
{
    public function edit(Request $request, AdmissionPeriod $period): View
    {
        $workspace = ApplicantWorkspace::query()->with(['biodata', 'education', 'application.participant'])->where('user_id', $request->user()?->id)->where('admission_period_id', $period->id)->first();
        abort_if($period->state !== PeriodState::Published && $workspace === null, 404);
        $dataset = ReferenceDataset::query()->where('state', 'active')->latest('id')->first();
        if ($workspace !== null && (($workspace->application !== null && ! $workspace->application->status->editable()) || $period->state === PeriodState::Archived) && $workspace->education?->reference_dataset_id !== null) {
            $dataset = ReferenceDataset::query()->whereKey($workspace->education->reference_dataset_id)->first();
        }
        $data = $workspace ? ApplicantFields::fromWorkspace($workspace) : [];
        $options = ReferenceOption::query()->where('dataset_id', $dataset?->id)->get()->groupBy('group');
        if ($workspace?->application === null || $workspace->application->status->editable()) {
            $data['is_kps_recipient'] ??= false;
            foreach (['nationality_code' => ['nationality', 'Indonesia'], 'religion_code' => ['religion', 'Kristen'], 'special_need_code' => ['special_need', 'Tidak ada kebutuhan khusus']] as $field => [$group, $label]) {
                if (empty($data[$field])) {
                    $data[$field] = $options->get($group)?->firstWhere('label', $label)?->code;
                }
            }
        }
        if (empty($data['whatsapp_number']) && $request->user() !== null) {
            $claim = SkClaim::query()
                ->where('admission_period_id', $period->id)
                ->where(function ($query) use ($request): void {
                    $query->where('requester_user_id', $request->user()->id)
                        ->orWhere('review_owner_id', $request->user()->id);
                })
                ->orderBy('id')
                ->first();

            if ($claim !== null && ! empty($claim->whatsapp_number)) {
                $data['whatsapp_number'] = $claim->whatsapp_number;
            }
        }

        $selectedDistrict = (string) FormInput::old('district_code', $data['district_code'] ?? '');
        $selectedCity = (string) FormInput::old('city_code', $data['city_code'] ?? '');
        $selectedProvince = (string) FormInput::old('province_code', $data['province_code'] ?? '');

        if (! empty($selectedDistrict) && (empty($selectedCity) || empty($selectedProvince))) {
            $distRegion = ReferenceRegion::query()->where('dataset_id', $dataset?->id)->where('code', $selectedDistrict)->first();
            if ($distRegion && $distRegion->parent_code) {
                $selectedCity = $selectedCity ?: $distRegion->parent_code;
                $data['city_code'] = $selectedCity;
                $cityRegion = ReferenceRegion::query()->where('dataset_id', $dataset?->id)->where('code', $selectedCity)->first();
                if ($cityRegion && $cityRegion->parent_code) {
                    $selectedProvince = $selectedProvince ?: $cityRegion->parent_code;
                    $data['province_code'] = $selectedProvince;
                }
            }
        }

        $regionCodes = array_values(array_filter([$selectedProvince, $selectedCity, $selectedDistrict]));

        return view('portal.application-form', [
            'period' => $period, 'workspace' => $workspace, 'application' => $workspace?->application, 'data' => $data,
            'fields' => ApplicantFields::definitions(), 'dataset' => $dataset,
            'options' => $options,
            'regions' => ReferenceRegion::query()->where('dataset_id', $dataset?->id)->whereIn('code', $regionCodes)->get(),
            'ptLabel' => $data['pt_name'] ?? (ReferencePt::query()->where('dataset_id', $dataset?->id)->where('external_id', FormInput::old('pt_external_id', $data['pt_external_id'] ?? ''))->value('name') ?? ''),
            'prodiLabel' => $data['prodi_name'] ?? (ReferenceProdi::query()->where('dataset_id', $dataset?->id)->where('external_id', FormInput::old('prodi_external_id', $data['prodi_external_id'] ?? ''))->value('name') ?? ''),
            'consent' => ConsentText::query()->where('purpose', 'submission')->latest('id')->first(),
        ]);
    }

    public function update(Request $request, AdmissionPeriod $period, SaveApplicantForm $save): RedirectResponse
    {
        $request->validate(['version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:draft,submit']]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $keys = [...array_keys(ApplicantFields::definitions()), 'pt_external_id', 'prodi_external_id', 'pt_name', 'prodi_name', 'degree_level', 'graduation_year', 'education_history', 'consent', 'consent_id'];
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
