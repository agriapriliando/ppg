<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Policies\PeriodDocumentPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetPeriodDocumentVisibility
{
    public function __construct(private PeriodDocumentPolicy $access, private RecordAudit $audit) {}

    public function handle(User $actor, PeriodDocument $document, string $visibility, int $version): void
    {
        abort_unless($this->access->update($actor, $document), 403);
        Validator::make(['visibility' => $visibility], ['visibility' => ['required', Rule::in(array_keys(PeriodDocument::VISIBILITIES))]])->validate();
        DB::transaction(function () use ($actor, $document, $visibility, $version): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($document->admission_period_id);
            $locked = PeriodDocument::query()->lockForUpdate()->findOrFail($document->id);
            $locked->setRelation('period', $period);
            abort_unless($this->access->update($actor, $locked), 403);
            if ($locked->lock_version !== $version) {
                throw ValidationException::withMessages(['visibility' => 'Berkas telah berubah. Muat ulang sebelum mengubah visibilitas.']);
            }
            $locked->forceFill(['visibility' => $visibility, 'lock_version' => $version + 1])->save();
            $this->audit->handle($actor, 'Mengubah visibilitas berkas periode', $locked, ['visibility' => $visibility]);
        });
    }
}
