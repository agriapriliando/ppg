<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Policies\PeriodDocumentPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RenamePeriodDocument
{
    public function __construct(private PeriodDocumentPolicy $access, private RecordAudit $audit) {}

    public function handle(User $actor, PeriodDocument $document, string $name, int $version): void
    {
        abort_unless($this->access->update($actor, $document), 403);
        $name = Str::squish($name);
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:180', 'not_in:.,..', 'not_regex:/[\x00-\x1F\x7F\/\\\\]/']], ['name.required' => 'Nama berkas wajib diisi.'])->validate();
        DB::transaction(function () use ($actor, $document, $name, $version): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($document->admission_period_id);
            $locked = PeriodDocument::query()->lockForUpdate()->findOrFail($document->id);
            $locked->setRelation('period', $period);
            abort_unless($this->access->update($actor, $locked), 403);
            if ($locked->lock_version !== $version) {
                throw ValidationException::withMessages(['document' => 'Berkas telah berubah. Tutup dialog dan muat ulang sebelum mengubah.']);
            }
            $locked->forceFill(['display_name' => $name, 'lock_version' => $version + 1])->save();
            $this->audit->handle($actor, 'Mengubah nama berkas periode', $locked);
        });
    }
}
