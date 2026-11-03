<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ConsentRecord;
use App\Models\ConsentText;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use App\Support\WhatsappNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitSkClaim
{
    public function __construct(private RecordAudit $audit) {}

    /** @param array<string, mixed> $input */
    public function handle(?User $requester, array $input): SkClaim
    {
        abort_if($requester !== null && $requester->role !== UserRole::Applicant, 403);
        $data = Validator::make($input, [
            'period_id' => ['required', 'integer', 'exists:admission_periods,id'],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:100'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'request_key' => ['required', 'uuid'],
            'consent_id' => ['required', 'integer'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Persetujuan pemeriksaan klaim wajib diberikan.', 'required' => ':attribute wajib diisi.'])->validate();
        $number = WhatsappNumber::normalize($data['whatsapp_number']);

        return DB::transaction(function () use ($requester, $data, $number): SkClaim {
            $period = AdmissionPeriod::query()->whereKey($data['period_id'])->lockForUpdate()->firstOrFail();
            $existing = SkClaim::query()->where('request_key', $data['request_key'])->first();
            if ($existing !== null) {
                if ($existing->requester_user_id !== $requester?->id || $existing->whatsapp_number !== $number || $existing->submitted_name !== trim($data['name'])) {
                    throw ValidationException::withMessages(['claim' => 'Referensi permohonan sudah digunakan. Muat ulang formulir.']);
                }

                return $existing;
            }
            if (! $period->acceptsApplications()) {
                throw ValidationException::withMessages(['period_id' => 'Periode belum dibuka atau sudah ditutup.']);
            }
            $duplicate = SkClaim::query()->where('admission_period_id', $period->id)->where('requester_user_id', $requester?->id)->where('submitted_name', trim($data['name']))->where('submitted_identifier', $data['identifier'] ?? null)->where('whatsapp_number', $number)->whereIn('status', ['pending', 'needs_information'])->first();
            if ($duplicate !== null) {
                return $duplicate;
            }
            $consent = ConsentText::query()->where('purpose', 'claim')->orderByDesc('id')->first();
            if ($consent === null || $consent->id !== (int) $data['consent_id']) {
                throw ValidationException::withMessages(['consent' => 'Pernyataan telah berubah atau belum tersedia. Muat ulang halaman.']);
            }
            $candidates = SkParticipant::query()->where('admission_period_id', $period->id)->where('normalized_name', mb_strtolower(Str::squish($data['name'])))->when(! empty($data['identifier']), fn ($query) => $query->where('official_participant_id', $data['identifier']))->where('roster_state', 'active')->limit(2)->get();
            $candidate = $candidates->count() === 1 ? $candidates->first() : null;
            $claim = SkClaim::create([
                'admission_period_id' => $period->id, 'requester_user_id' => $requester?->id,
                'candidate_sk_participant_id' => $candidate?->id,
                'submitted_name' => trim($data['name']), 'submitted_identifier' => $data['identifier'] ?? null,
                'whatsapp_number' => $number, 'access_path' => $requester === null ? 'whatsapp' : 'google',
                'public_reference' => (string) Str::uuid(), 'request_key' => $data['request_key'],
            ]);
            ConsentRecord::create(['consent_text_id' => $consent->id, 'user_id' => $requester?->id, 'claim_id' => $claim->id, 'accepted_at' => now()]);
            $this->audit->handle($requester, 'Mengajukan klaim SK', $claim);

            return $claim;
        });
    }
}
