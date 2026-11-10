<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationSetting;
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
    public function __construct(private RecordAudit $audit, private LookupPublicClaimParticipant $lookup, private SynchronizeApplicantPhone $syncPhone) {}

    /** @param array<string, mixed> $input */
    public function handle(?User $requester, array $input, bool $useRosterIdentity = false): SkClaim
    {
        abort_if($requester !== null && $requester->role !== UserRole::Applicant, 403);
        if ($useRosterIdentity) {
            $participants = $this->lookup->handle($input);
            $selection = Validator::make($input, ['period_id' => ['nullable', 'integer']], ['period_id.integer' => 'Periode pendaftaran tidak valid. Periksa kembali PEG ID.'])->validate();
            $participant = isset($selection['period_id'])
                ? $participants->firstWhere('admission_period_id', (int) $selection['period_id'])
                : ($participants->count() === 1 ? $participants->first() : null);
            if ($participant === null) {
                throw ValidationException::withMessages(['period_id' => 'Pilih periode yang sesuai dari hasil pencarian PEG ID.']);
            }
            $input['period_id'] = $participant->admission_period_id;
            $input['name'] = $participant->name_on_sk;
        }
        $data = Validator::make($input, [
            'period_id' => ['required', 'integer', 'exists:admission_periods,id'],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => [ApplicationSetting::claimPegIdRequired() ? 'required' : 'nullable', 'string', 'max:100'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
            'request_key' => ['required', 'uuid'],
            'consent_id' => ['required', 'integer'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Persetujuan pemeriksaan klaim wajib diberikan.',
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'integer' => ':attribute tidak valid.',
            'max.string' => ':attribute maksimal :max karakter.',
            'period_id.exists' => 'Periode pendaftaran tidak tersedia. Pilih periode yang masih dibuka.',
            'identifier.required' => 'Nomor Peg ID wajib diisi.',
            'request_key.uuid' => 'Referensi formulir tidak valid. Muat ulang halaman sebelum mengirim.',
        ], [
            'period_id' => 'Periode pendaftaran', 'name' => 'Nama lengkap', 'identifier' => 'Peg ID',
            'whatsapp_number' => 'Nomor WhatsApp', 'request_key' => 'Referensi formulir', 'consent_id' => 'Pernyataan persetujuan',
        ])->validate();
        $number = WhatsappNumber::normalize($data['whatsapp_number']);
        $normalizedName = mb_strtolower(Str::squish($data['name']));
        $identifier = trim($data['identifier'] ?? '');

        return DB::transaction(function () use ($requester, $data, $number, $normalizedName, $identifier, $useRosterIdentity): SkClaim {
            $period = AdmissionPeriod::query()->whereKey($data['period_id'])->lockForUpdate()->firstOrFail();
            if ($useRosterIdentity) {
                $matches = SkParticipant::query()->where('admission_period_id', $period->id)->where('official_participant_id', $identifier)->where('roster_state', 'active')->lockForUpdate()->limit(2)->get();
                if ($matches->count() !== 1) {
                    throw ValidationException::withMessages(['identifier' => 'Data PEG ID telah berubah atau tidak dapat dipastikan. Periksa kembali PEG ID atau hubungi operator.']);
                }
                $data['name'] = $matches->first()->name_on_sk;
                $normalizedName = mb_strtolower(Str::squish($data['name']));
            }
            $existing = SkClaim::query()->where('request_key', $data['request_key'])->first();
            if ($existing !== null) {
                if ($existing->requester_user_id !== $requester?->id || $existing->admission_period_id !== $period->id || $existing->whatsapp_number !== $number || $existing->submitted_name !== trim($data['name']) || ($existing->submitted_identifier ?? '') !== $identifier) {
                    throw ValidationException::withMessages(['claim' => 'Referensi permohonan sudah digunakan. Muat ulang formulir.']);
                }

                return $existing;
            }
            if (! $period->acceptsApplications()) {
                throw ValidationException::withMessages(['period_id' => 'Periode belum dibuka atau sudah ditutup.']);
            }
            $duplicate = SkClaim::query()->where('admission_period_id', $period->id)->where('whatsapp_number', $number)
                ->get(['id', 'submitted_name'])->contains(fn (SkClaim $claim) => mb_strtolower(Str::squish($claim->submitted_name)) === $normalizedName);
            if ($duplicate) {
                throw ValidationException::withMessages(['claim' => 'Kombinasi nama dan nomor WhatsApp ini sudah terdaftar pada periode yang dipilih. Tidak perlu mengirim pendaftaran akun kembali. Tunggu informasi akun melalui WhatsApp atau hubungi operator untuk bantuan.']);
            }
            if ($identifier !== '' && SkClaim::query()->where('admission_period_id', $period->id)->where('submitted_identifier', $identifier)->exists()) {
                throw ValidationException::withMessages(['identifier' => 'Peg ID ini sudah terdaftar pada periode yang dipilih. Pendaftaran ulang tidak diperlukan meskipun nama atau nomor WhatsApp berbeda. Tunggu informasi akun melalui WhatsApp atau hubungi operator untuk perbaikan data.']);
            }
            $consent = ConsentText::query()->where('purpose', 'claim')->orderByDesc('id')->first();
            if ($consent === null || $consent->id !== (int) $data['consent_id']) {
                throw ValidationException::withMessages(['consent' => 'Pernyataan telah berubah atau belum tersedia. Muat ulang halaman.']);
            }
            $candidates = SkParticipant::query()->where('admission_period_id', $period->id)
                ->when($identifier !== '',
                    fn ($query) => $query->where('official_participant_id', $identifier),
                    fn ($query) => $query->where('normalized_name', $normalizedName))
                ->where('roster_state', 'active')->limit(2)->get();
            if ($identifier !== '' && $candidates->isEmpty()) {
                throw ValidationException::withMessages(['identifier' => 'Peg ID tidak ditemukan dalam daftar peserta SK aktif pada periode yang dipilih. Periksa kembali Peg ID dan periode pendaftaran sesuai surat. Jika data sudah benar, hubungi operator untuk pemeriksaan data peserta SK.']);
            }
            $candidate = $candidates->count() === 1 ? $candidates->first() : null;
            $claim = SkClaim::create([
                'admission_period_id' => $period->id, 'requester_user_id' => $requester?->id,
                'candidate_sk_participant_id' => $candidate?->id,
                'submitted_name' => trim($data['name']), 'submitted_identifier' => $identifier !== '' ? $identifier : null,
                'whatsapp_number' => $number, 'access_path' => $requester === null ? 'whatsapp' : 'google',
                'public_reference' => (string) Str::uuid(), 'request_key' => $data['request_key'],
            ]);
            ConsentRecord::create(['consent_text_id' => $consent->id, 'user_id' => $requester?->id, 'claim_id' => $claim->id, 'accepted_at' => now()]);
            if ($requester !== null) {
                $this->syncPhone->handle($requester, $number);
            }
            $this->audit->handle($requester, 'Mengajukan klaim SK', $claim);

            return $claim;
        }, attempts: 3);
    }
}
