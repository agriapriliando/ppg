<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
use App\Support\ApplicantPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueClaimCredentials
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkClaim $claim, bool $regenerate = false): ?CredentialIssuance
    {
        Gate::forUser($actor)->authorize('review', $claim);

        return DB::transaction(function () use ($actor, $claim, $regenerate): ?CredentialIssuance {
            AdmissionPeriod::query()->whereKey($claim->admission_period_id)->lockForUpdate()->firstOrFail();
            $lockedClaim = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $lockedClaim);
            if ($lockedClaim->status !== 'approved' || $lockedClaim->access_path !== 'whatsapp') {
                throw ValidationException::withMessages(['claim' => 'Penyerahan akses hanya untuk klaim WhatsApp yang disetujui.']);
            }
            $user = User::query()->lockForUpdate()->findOrFail($lockedClaim->requester_user_id);
            if ($user->account_state === AccountState::Disabled) {
                throw ValidationException::withMessages(['claim' => 'Akun dinonaktifkan. Hubungi administrator.']);
            }
            $existing = CredentialIssuance::query()->where('user_id', $user->id)->where('claim_id', $lockedClaim->id)
                ->where(function ($query) use ($user): void {
                    $query->whereNull('revoked_at');
                    if ($user->account_state === AccountState::Active) {
                        $query->orWhereNotNull('used_at');
                    }
                })->latest('id')->first();
            if (! $regenerate && ($existing !== null || $user->account_state === AccountState::Active || $user->password !== null)) {
                $this->audit->handle($actor, 'Melihat akses akun', $user);

                return $existing;
            }
            $application = Application::query()->where('sk_participant_id', $lockedClaim->candidate_sk_participant_id)->firstOrFail();
            $number = $application->workspace->biodata?->whatsapp_number;
            if ($number === null) {
                throw ValidationException::withMessages(['whatsapp' => 'Lengkapi nomor WhatsApp sebelum menerbitkan akses.']);
            }
            if ($regenerate) {
                CredentialIssuance::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $user->password = null;
                $user->account_state = AccountState::PendingActivation;
                $user->must_change_password = false;
                $user->remember_token = Str::random(60);
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $secret = ApplicantPassword::generate($existing?->encrypted_secret);
            $issuance = CredentialIssuance::create(['user_id' => $user->id, 'claim_id' => $lockedClaim->id, 'secret_hash' => Hash::make($secret), 'encrypted_secret' => $secret, 'target_number' => $number, 'issued_by' => $actor->id, 'expires_at' => now()->addMinutes((int) config('pmb.temporary_password_minutes'))]);
            $this->audit->handle($actor, $regenerate ? 'Generate ulang password akun' : 'Menerbitkan akses sementara', $user, ['issuance_id' => $issuance->id]);

            return $issuance;
        });
    }

    /** @return array{issuance: ?CredentialIssuance, secret: ?string, user: User, canDeliver: bool} */
    public function details(User $actor, SkClaim $claim): array
    {
        Gate::forUser($actor)->authorize('review', $claim);
        $user = User::findOrFail($claim->requester_user_id);
        $issuance = CredentialIssuance::query()->where('claim_id', $claim->id)->where('user_id', $user->id)->latest('id')->first();
        $secret = $issuance?->encrypted_secret;
        if ($secret !== null && ($user->account_state === AccountState::Disabled
            || ($user->account_state === AccountState::Active && ($user->password === null || ! Hash::check($secret, $user->password)))
            || ($user->account_state !== AccountState::Active && ($issuance->revoked_at !== null || $issuance->expires_at->isPast())))) {
            $secret = null;
        }
        $number = Application::query()->where('sk_participant_id', $claim->candidate_sk_participant_id)->first()?->workspace->biodata?->whatsapp_number;
        if ($issuance !== null && $number !== $issuance->target_number) {
            $secret = null;
        }
        $canDeliver = $issuance !== null && $secret !== null && $issuance->revoked_at === null && $issuance->used_at === null && $issuance->expires_at->isFuture();

        return compact('issuance', 'secret', 'user', 'canDeliver');
    }
}
