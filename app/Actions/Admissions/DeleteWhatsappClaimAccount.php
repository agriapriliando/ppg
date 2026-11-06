<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\CredentialIssuance;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteWhatsappClaimAccount
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkClaim $claim, int $version): void
    {
        abort_unless($actor->isAdministrator(), 403);
        DB::transaction(function () use ($actor, $claim, $version): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->status !== 'approved' || $locked->access_path !== 'whatsapp' || $locked->lock_version !== $version) {
                throw ValidationException::withMessages(['claim' => 'Klaim telah berubah atau bukan klaim WhatsApp yang disetujui. Muat ulang sebelum menghapus.']);
            }
            $participant = SkParticipant::query()->lockForUpdate()->findOrFail($locked->candidate_sk_participant_id);
            $application = Application::query()->where('sk_participant_id', $participant->id)->lockForUpdate()->first();
            $user = User::query()->lockForUpdate()->findOrFail($locked->requester_user_id);
            if ($user->role !== UserRole::Applicant || $participant->claimed_user_id !== $user->id || ($application !== null && $application->user_id !== $user->id)) {
                throw ValidationException::withMessages(['claim' => 'Pemilik akun tidak sesuai dengan kepesertaan ini. Periksa kepemilikan sebelum menghapus akses.']);
            }
            // Keep the internal owner key so registration history and files remain intact.
            $user->forceFill(['login_id' => null, 'password' => null, 'account_state' => AccountState::Disabled,
                'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            CredentialIssuance::query()->where('user_id', $user->id)->update(['revoked_at' => now(), 'encrypted_secret' => null]);
            if ($application !== null) {
                $application->forceFill(['claim_account_reset_at' => now(), 'lock_version' => $application->lock_version + 1])->save();
            }
            $participant->forceFill(['claimed_user_id' => null, 'lock_version' => $participant->lock_version + 1])->save();
            $this->audit->handle($actor, 'Menghapus akses akun dan klaim WhatsApp', $locked, [
                'user_id' => $user->id, 'participant_id' => $participant->id, 'application_id' => $application?->id,
            ]);
            // Historical relations retain their foreign keys; normal lookups no longer expose the claim.
            $locked->delete();
            $period->increment('lock_version');
        });
    }
}
