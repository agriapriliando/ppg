<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\ApplicationStatus;
use App\Enums\PeriodState;
use App\Models\AdministrativeCase;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\User;
use App\Models\WhatsappVerification;
use App\Support\ApplicantFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewApplication
{
    public function __construct(private RecordAudit $audit) {}

    /** @param array<string,mixed> $input */
    public function handle(User $actor, Application $application, int $version, string $action, array $input): void
    {
        DB::transaction(function () use ($actor, $application, $version, $action, $input): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($application->admission_period_id);
            $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
            Gate::forUser($actor)->authorize('review', $locked);
            if ($period->state === PeriodState::Archived || $locked->lock_version !== $version) {
                throw ValidationException::withMessages(['review' => 'Pendaftaran telah berubah atau periode diarsipkan. Muat ulang pemeriksaan.']);
            }
            $allowed = match ($action) {
                'verify' => [ApplicationStatus::Submitted],
                'correction' => [ApplicationStatus::Submitted, ApplicationStatus::Verified],
                'accept' => [ApplicationStatus::Verified],
                'reopen' => $actor->isAdministrator() ? [ApplicationStatus::Accepted] : [],
                default => [],
            };
            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages(['review' => 'Perubahan status tidak diizinkan dari keadaan saat ini.']);
            }
            $rules = match ($action) {
                'verify' => ['identity_checked' => ['accepted'], 'sk_checked' => ['accepted'], 'data_checked' => ['accepted']],
                'correction','reopen' => ['public_note' => ['required', 'string', 'min:10', 'max:1000'], 'field_keys' => ['required', 'array', 'min:1'], 'field_keys.*' => [Rule::in(['Identitas', 'Alamat', 'Tambahan', 'Pendidikan asal', 'Kontak WhatsApp'])]],
                default => [],
            };
            $data = Validator::make($input, $rules, ['public_note.required' => 'Tuliskan catatan perbaikan yang spesifik.', 'field_keys.required' => 'Pilih kelompok data yang perlu diperbaiki.'])->validate();
            if (in_array($action, ['verify', 'accept'], true)) {
                if (! $locked->hasCurrentOwnerSubmission()) {
                    throw ValidationException::withMessages(['review' => 'Akun pemilik saat ini harus mengirim formulir sebelum diperiksa.']);
                }
                ApplicantFields::validate(ApplicantFields::fromWorkspace($locked->workspace), true);
            }
            if ($action === 'accept') {
                if (AdministrativeCase::query()->where('application_id', $locked->id)->where('state', 'open')->exists()) {
                    throw ValidationException::withMessages(['review' => 'Kasus administratif pendaftaran belum diselesaikan administrator.']);
                }
                $number = $locked->workspace->biodata?->whatsapp_number;
                $latestReview = ApplicationReview::query()->where('application_id', $locked->id)->where('ownership_epoch', $locked->ownership_epoch)->where('action', 'verify')->where('submission_revision', $locked->submission_revision)->latest('id')->first();
                if ($latestReview === null || $number === null || ! WhatsappVerification::query()->where('workspace_id', $locked->workspace_id)->where('number', $number)->whereNull('invalidated_at')->exists() || $locked->participant->claimed_user_id !== $locked->user_id || $locked->participant->admin_hold || $locked->participant->roster_state !== 'active') {
                    throw ValidationException::withMessages(['review' => 'Keputusan memerlukan pemeriksaan revisi terbaru, kepesertaan sah, WhatsApp nomor saat ini terverifikasi, serta seluruh konflik selesai.']);
                }
                $locked->forceFill(['accepted_at' => now(), 'accepted_by' => $actor->id]);
            }
            if ($action === 'reopen') {
                $locked->forceFill(['accepted_at' => null, 'accepted_by' => null]);
            }
            $locked->status = match ($action) {
                'verify' => ApplicationStatus::Verified,'accept' => ApplicationStatus::Accepted,default => ApplicationStatus::Correction
            };
            $locked->lock_version++;
            $locked->save();
            ApplicationReview::create(['application_id' => $locked->id, 'ownership_epoch' => $locked->ownership_epoch, 'reviewer_id' => $actor->id, 'submission_revision' => $locked->submission_revision, 'action' => $action, 'identity_checked' => (bool) ($data['identity_checked'] ?? false), 'sk_checked' => (bool) ($data['sk_checked'] ?? false), 'data_checked' => (bool) ($data['data_checked'] ?? false), 'field_keys' => $data['field_keys'] ?? [], 'public_note' => $data['public_note'] ?? null]);
            $this->audit->handle($actor, 'Keputusan: '.$locked->status->label(), $locked, ['status' => $locked->status->value, 'revision' => $locked->submission_revision], $action === 'reopen' ? $data['public_note'] : null);
        });
    }
}
