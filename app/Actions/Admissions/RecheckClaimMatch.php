<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\SkClaim;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecheckClaimMatch
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkClaim $claim, int $version): string
    {
        Gate::forUser($actor)->authorize('review', $claim);

        return DB::transaction(function () use ($actor, $claim, $version): string {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $locked);
            if ($locked->lock_version !== $version || $locked->candidate_sk_participant_id !== null) {
                throw ValidationException::withMessages(['match' => 'Data klaim telah berubah atau sudah memiliki kandidat. Muat ulang halaman sebelum memeriksa kembali.']);
            }
            if ($period->state === PeriodState::Archived || ! in_array($locked->status, ['pending', 'needs_information'], true)) {
                throw ValidationException::withMessages(['match' => 'Pemeriksaan ulang hanya tersedia untuk klaim yang masih menunggu atau memerlukan informasi pada periode yang belum diarsipkan.']);
            }
            $identifier = trim($locked->submitted_identifier ?? '');
            $candidates = SkParticipant::query()->where('admission_period_id', $period->id)
                ->when($identifier !== '',
                    fn ($query) => $query->where('official_participant_id', $identifier),
                    fn ($query) => $query->where('normalized_name', mb_strtolower(Str::squish($locked->submitted_name))))
                ->where('roster_state', 'active')->whereNull('claimed_user_id')->where('admin_hold', false)
                ->orderBy('id')->lockForUpdate()->limit(2)->get();
            if ($candidates->isEmpty()) {
                return 'not_found';
            }
            if ($candidates->count() > 1) {
                return 'ambiguous';
            }
            $candidate = $candidates->firstOrFail();
            $locked->candidate_sk_participant_id = $candidate->id;
            $locked->lock_version++;
            $locked->save();
            $this->audit->handle($actor, 'Mencocokkan ulang klaim SK', $locked, ['participant_id' => $candidate->id]);

            return 'matched';
        });
    }
}
