<?php

namespace App\Livewire\Documents;

use App\Actions\Admissions\SaveApplicantForm;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\ApplicationSubmission;
use App\Models\ConsentText;
use App\Models\DocumentPolicyVersion;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\ReferenceRegion;
use App\Policies\ApplicantDocumentPolicy;
use App\Support\ApplicantFields;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SubmitRegistration extends Component
{
    #[Locked]
    public int $workspaceId;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public int $documentsVersion = 0;

    #[Locked]
    public int $policyId = 0;

    #[Locked]
    public int $consentId = 0;

    #[Locked]
    public int $datasetId = 0;

    #[Locked]
    public string $requestId = '';

    public bool $showConfirmation = false;

    public bool $consent = false;

    public function mount(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $this->workspace();
    }

    private function workspace(): ApplicantWorkspace
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->role === UserRole::Applicant && $user->account_state === AccountState::Active, 403);

        return ApplicantWorkspace::query()->where('user_id', $user->id)->with(['period', 'application.participant', 'biodata', 'education'])->findOrFail($this->workspaceId);
    }

    public function openConfirmation(): void
    {
        $this->resetValidation();
        $this->consent = false;
        $workspace = $this->workspace();
        $data = ApplicantFields::fromWorkspace($workspace);
        $dataset = ReferenceDataset::query()->where('state', 'active')->latest('id')->first();
        try {
            ApplicantFields::validate($data, true, $dataset?->id);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }
        $consent = ConsentText::query()->where('purpose', 'submission')->latest('id')->first();
        if ($consent === null) {
            $this->addError('submission', 'Pernyataan belum tersedia. Hubungi pengelola.');

            return;
        }
        if ($workspace->application === null || ! $workspace->application->status->editable()) {
            $this->addError('submission', 'Pendaftaran belum disetujui atau sudah dikirim.');

            return;
        }
        $this->version = $workspace->lock_version;
        $this->documentsVersion = $workspace->documents_version;
        $this->policyId = DocumentPolicyVersion::resolveForPeriod($workspace->period)->id;
        $this->consentId = $consent->id;
        $this->datasetId = $dataset->id ?? 0;
        $this->requestId = (string) Str::uuid();
        $this->showConfirmation = true;
    }

    public function cancelPendingUploads(): void
    {
        $workspace = $this->workspace();
        DB::transaction(function () use ($workspace): void {
            AdmissionPeriod::query()->lockForUpdate()->findOrFail($workspace->admission_period_id);
            $locked = ApplicantWorkspace::query()->lockForUpdate()->findOrFail($workspace->id);
            abort_unless(app(ApplicantDocumentPolicy::class)->upload(auth()->user(), $locked), 403);
            DB::table('applicant_upload_batches')->where('workspace_id', $locked->id)->where('user_id', auth()->id())
                ->whereIn('status', ['uploading', 'failed'])->update(['status' => 'cancelled', 'updated_at' => now()]);
        });
        $this->dispatch('registration-uploading', busy: false);
        $this->dispatch('registration-upload-cancelled');
        $this->dispatch('registration-documents-changed');
    }

    public function submit(SaveApplicantForm $save): void
    {
        $this->validate(['consent' => ['accepted']], ['consent.accepted' => 'Centang pernyataan sebelum mengirim.']);
        abort_unless($this->requestId !== '', 422);
        $workspace = $this->workspace();
        $previous = ApplicationSubmission::query()->where('request_id', $this->requestId)->where('submitted_by', auth()->id())
            ->where('application_id', $workspace->application?->id)->first();
        if ($previous !== null) {
            $this->redirectRoute('applicant.application.status', $workspace->admission_period_id);

            return;
        }
        if ($this->datasetId !== (int) ReferenceDataset::query()->where('state', 'active')->latest('id')->value('id')) {
            $this->addError('submission', 'Referensi berubah. Tutup dan periksa ulang ringkasan.');

            return;
        }
        $data = ApplicantFields::fromWorkspace($workspace);
        $save->handle(auth()->user(), $workspace->admission_period_id, $this->version, array_merge($data, [
            'consent' => true, 'consent_id' => $this->consentId, 'submission_request_id' => $this->requestId,
            'documents_version' => $this->documentsVersion, 'document_policy_id' => $this->policyId,
        ]), true);
        $this->showConfirmation = false;
        session()->flash('status', 'Biodata dan berkas pendaftaran berhasil dikirim untuk pemeriksaan.');
        $this->redirectRoute('applicant.application.status', $workspace->admission_period_id);
    }

    public function render(): View
    {
        $workspace = $this->workspace();
        $data = ApplicantFields::fromWorkspace($workspace);
        $issues = [];
        try {
            ApplicantFields::validate($data, true);
        } catch (ValidationException $exception) {
            $issues = $exception->errors();
        }
        $busy = DB::table('applicant_upload_batches')->where('workspace_id', $workspace->id)->whereIn('status', ['uploading', 'failed'])->where('expires_at', '>', now())->exists();
        $levels = ['S1', 'S2', 'S3'];
        $highest = array_search($data['degree_level'] ?? '', $levels, true);
        $excludedLevels = [];
        foreach ($data['education_history'] ?? [] as $level => $item) {
            if ($highest === false || array_search($level, $levels, true) > $highest) {
                if (is_array($item) && array_filter($item, fn ($value) => $value !== null && $value !== '') !== []) {
                    $excludedLevels[] = $level;
                }
                unset($data['education_history'][$level]);
            }
        }
        $display = [];
        $options = ReferenceOption::query()->where('dataset_id', ReferenceDataset::query()->where('state', 'active')->latest('id')->value('id'))->get()->groupBy('group');
        $regions = ReferenceRegion::query()->whereIn('code', array_filter([$data['district_code'] ?? null, $data['city_code'] ?? null, $data['province_code'] ?? null]))->pluck('name', 'code');
        foreach (ApplicantFields::definitions() as $key => $field) {
            $value = $data[$key] ?? null;
            $display[$key] = match ($field['type']) {
                'select' => $options->get($field['reference'] ?? '')?->firstWhere('code', $value)->label ?? ($value ?: 'Belum diisi'),
                'region' => $regions->get($value) ?? ($value ?: 'Belum diisi'),
                'boolean' => $value === null || $value === '' ? 'Belum diisi' : ((bool) $value ? 'Ya' : 'Tidak'),
                default => $value === null || $value === '' ? 'Belum diisi' : $value,
            };
        }

        return view('livewire.documents.submit-registration', [
            'workspace' => $workspace, 'data' => $data, 'issues' => $issues, 'busy' => $busy,
            'excludedLevels' => $excludedLevels,
            'display' => $display,
            'documents' => $workspace->availableDocuments()->orderBy('created_at')->get(),
            'statement' => ConsentText::query()->where('purpose', 'submission')->latest('id')->first(),
        ]);
    }
}
