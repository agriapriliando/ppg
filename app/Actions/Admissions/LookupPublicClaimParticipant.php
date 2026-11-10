<?php

namespace App\Actions\Admissions;

use App\Enums\PeriodState;
use App\Models\SkParticipant;
use App\Support\WhatsappNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LookupPublicClaimParticipant
{
    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, SkParticipant>
     */
    public function handle(array $input): Collection
    {
        $data = Validator::make($input, [
            'identifier' => ['required', 'string', 'max:100'],
            'whatsapp_number' => ['required', 'string', 'max:30'],
        ], ['required' => ':attribute wajib diisi.', 'string' => ':attribute harus berupa teks.', 'max.string' => ':attribute maksimal :max karakter.'], [
            'identifier' => 'Nomor Peg ID', 'whatsapp_number' => 'Nomor WhatsApp',
        ])->validate();
        WhatsappNumber::normalize($data['whatsapp_number']);

        $participants = SkParticipant::query()->select(['id', 'admission_period_id', 'name_on_sk'])->with('period:id,name')
            ->where('official_participant_id', trim($data['identifier']))->where('roster_state', 'active')
            ->whereHas('period', fn ($query) => $query->where('state', 'published')->whereNull('manual_closed_at')->where('opens_at', '<=', now())->where('closes_at', '>', now()))
            ->orderByDesc('admission_period_id')->get();

        if ($participants->isEmpty()) {
            $registered = SkParticipant::query()->select(['id', 'admission_period_id'])
                ->with('period:id,state,opens_at,closes_at,manual_closed_at')
                ->where('official_participant_id', trim($data['identifier']))->where('roster_state', 'active')
                ->orderByDesc('admission_period_id')->first();
            if ($registered?->period !== null) {
                $period = $registered->period;
                $message = $period->manual_closed_at !== null || now()->greaterThanOrEqualTo($period->closes_at)
                    ? 'PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut sudah ditutup. Pendaftaran akun belum dapat dikirim. Hubungi operator untuk informasi lebih lanjut.'
                    : 'PEG ID Anda terdaftar, tetapi pendaftaran untuk periode tersebut belum dibuka. Silakan menunggu pembukaan pendaftaran atau hubungi operator untuk informasi jadwal.';
                if ($period->state === PeriodState::Archived) {
                    $message = 'PEG ID Anda terdaftar, tetapi periode pendaftarannya sudah diarsipkan dan tidak menerima pendaftaran akun. Hubungi operator untuk informasi lebih lanjut.';
                }
                throw ValidationException::withMessages(['identifier' => $message]);
            }
            throw ValidationException::withMessages(['identifier' => 'Peg ID tidak ditemukan dalam daftar peserta SK aktif pada periode yang sedang dibuka. Periksa kembali Peg ID sesuai surat. Jika sudah benar, hubungi operator untuk pemeriksaan data peserta SK atau jadwal pendaftaran.']);
        }

        return $participants;
    }
}
