<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateClaimLoginId
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkClaim $claim, int $version, string $loginId): void
    {
        Gate::forUser($actor)->authorize('review', $claim);

        $loginId = mb_strtolower(trim($loginId));

        DB::transaction(function () use ($actor, $claim, $version, $loginId): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $locked);

            if ($period->state === PeriodState::Archived || $locked->status === 'cancelled' || $locked->lock_version !== $version) {
                throw ValidationException::withMessages(['login_id' => 'Klaim telah berubah, dibatalkan, atau periode diarsipkan. Muat ulang halaman.']);
            }

            if ($locked->requester_user_id === null) {
                throw ValidationException::withMessages(['login_id' => 'Klaim belum memiliki akun pemohon yang dapat diubah ID login-nya.']);
            }

            $user = User::query()->lockForUpdate()->findOrFail($locked->requester_user_id);

            if (User::query()->where('login_id', $loginId)->where('id', '!=', $user->id)->exists()) {
                throw ValidationException::withMessages(['login_id' => 'ID login sudah digunakan akun lain.']);
            }

            $oldLoginId = $user->login_id;
            if ($oldLoginId !== $loginId) {
                $user->login_id = $loginId;
                $user->remember_token = Str::random(60);
                $user->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $issuance = CredentialIssuance::query()->where('claim_id', $locked->id)->where('user_id', $user->id)->latest('id')->lockForUpdate()->first();
                if ($issuance !== null && $issuance->used_at === null) {
                    $issuance->delivered_at = null;
                    $issuance->delivered_by = null;
                    $issuance->save();
                }

                $locked->lock_version++;
                $locked->save();

                $this->audit->handle($actor, 'Memperbarui ID login akun pendaftar', $user, [
                    'claim_id' => $locked->id,
                    'old_login_id' => $oldLoginId,
                    'new_login_id' => $user->login_id,
                ]);
            }
        });
    }
}
