<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\SkClaim;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveAdmissionPeriod
{
    public function __construct(private RecordAudit $audit) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, ?int $id, int $version, array $input): AdmissionPeriod
    {
        Gate::forUser($actor)->authorize('create', AdmissionPeriod::class);
        $data = Validator::make($input, [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('admission_periods', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:20'],
            'wave' => ['sometimes', 'required', 'string', 'max:50'],
            'state' => ['required', Rule::enum(PeriodState::class)],
            'opens_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'closes_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:opens_at'],
            'allow_correction_after_close' => ['required', 'boolean'],
            'correction_closes_at' => ['nullable', 'required_if:allow_correction_after_close,true', 'date_format:Y-m-d\TH:i', 'after:closes_at'],
            'is_public' => ['required', 'boolean'],
        ], [
            'required' => ':attribute wajib diisi.',
            'closes_at.after' => 'Waktu tutup harus setelah waktu buka.',
            'correction_closes_at.required_if' => 'Batas akhir perbaikan wajib diisi jika perbaikan setelah penutupan diizinkan.',
            'correction_closes_at.after' => 'Batas perbaikan harus setelah waktu tutup.',
            'code.unique' => 'Kode periode sudah digunakan.',
        ], ['name' => 'Nama periode', 'code' => 'Kode periode', 'opens_at' => 'Waktu buka', 'closes_at' => 'Waktu tutup'])->validate();

        return DB::transaction(function () use ($actor, $id, $version, $data): AdmissionPeriod {
            ApplicationSetting::query()->firstOrCreate(['key' => 'period-control'], ['value' => []]);
            ApplicationSetting::query()->where('key', 'period-control')->lockForUpdate()->firstOrFail();
            $period = $id === null ? new AdmissionPeriod : AdmissionPeriod::query()->lockForUpdate()->findOrFail($id);
            if ($id !== null && $period->lock_version !== $version) {
                throw ValidationException::withMessages(['conflict' => 'Periode telah diubah pengguna lain. Muat ulang sebelum menyimpan.']);
            }
            if ($id !== null && $period->state === PeriodState::Archived) {
                throw ValidationException::withMessages(['conflict' => 'Periode yang diarsipkan tidak dapat diubah.']);
            }
            $values = $data;
            $values['wave'] = $data['wave'] ?? $period->wave ?? '1';
            if ($id !== null && $data['state'] === PeriodState::Archived->value && (
                Application::query()->where('admission_period_id', $id)->where('status', '!=', 'diterima_lengkap')->whereHas('participant', fn ($query) => $query->where('roster_state', 'active'))->exists()
                || SkClaim::query()->where('admission_period_id', $id)->whereIn('status', ['pending', 'needs_information'])->exists()
            )) {
                throw ValidationException::withMessages(['state' => 'Selesaikan pendaftaran dan klaim yang masih terbuka sebelum mengarsipkan periode.']);
            }
            foreach (['opens_at', 'closes_at', 'correction_closes_at'] as $field) {
                $values[$field] = ! empty($data[$field]) ? CarbonImmutable::parse((string) $data[$field], 'Asia/Jakarta')->utc() : null;
            }
            if (! $data['allow_correction_after_close']) {
                $values['correction_closes_at'] = null;
            }
            if ($data['is_public'] && $data['state'] !== PeriodState::Published->value) {
                throw ValidationException::withMessages(['is_public' => 'Hanya periode yang dipublikasikan dapat ditampilkan pada portal publik.']);
            }
            if ($data['is_public']) {
                AdmissionPeriod::query()->where('is_public', true)->when($id !== null, fn ($query) => $query->where('id', '!=', $id))->update(['is_public' => false, 'lock_version' => DB::raw('lock_version + 1')]);
            }
            $period->fill($values);
            $period->lock_version = $period->exists ? $period->lock_version + 1 : 1;
            $period->save();
            $this->audit->handle($actor, $id === null ? 'Membuat periode' : 'Memperbarui periode', $period, ['code' => $period->code, 'state' => $period->state->value]);

            return $period;
        });
    }
}
