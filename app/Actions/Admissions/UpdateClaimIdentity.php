<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateClaimIdentity
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, SkClaim $claim, int $version, string $name, ?string $identifier): void
    {
        Gate::forUser($actor)->authorize('review', $claim);
        abort_unless($claim->access_path === 'whatsapp', 403);
        $name = trim($name);
        $identifier = trim($identifier ?? '');
        $identifier = $identifier === '' ? null : $identifier;

        DB::transaction(function () use ($actor, $claim, $version, $name, $identifier): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($claim->admission_period_id);
            $locked = SkClaim::query()->lockForUpdate()->findOrFail($claim->id);
            Gate::forUser($actor)->authorize('review', $locked);
            abort_unless($locked->access_path === 'whatsapp', 403);
            if ($period->state === PeriodState::Archived || $locked->status === 'cancelled' || $locked->lock_version !== $version) {
                throw ValidationException::withMessages(['submitted_name' => 'Klaim telah berubah, dibatalkan, atau periode diarsipkan. Muat ulang halaman.']);
            }
            $normalizedName = mb_strtolower(Str::squish($name));
            $duplicate = SkClaim::query()->where('admission_period_id', $period->id)->whereKeyNot($locked->id)
                ->where('whatsapp_number', $locked->whatsapp_number)->get(['submitted_name'])
                ->contains(fn (SkClaim $other): bool => mb_strtolower(Str::squish($other->submitted_name)) === $normalizedName);
            if ($duplicate) {
                throw ValidationException::withMessages(['submitted_name' => 'Kombinasi nama dan nomor WhatsApp ini sudah terdaftar pada periode yang sama.']);
            }
            $oldName = $locked->submitted_name;
            $oldIdentifier = $locked->submitted_identifier;
            $locked->submitted_name = $name;
            $locked->submitted_identifier = $identifier;
            $locked->lock_version++;
            $locked->save();
            $this->audit->handle($actor, 'Mengubah nama dan Peg ID permohonan', $locked, [
                'old_name' => $oldName,
                'new_name' => $name,
                'old_identifier' => $oldIdentifier,
                'new_identifier' => $identifier,
            ]);
        });
    }
}
