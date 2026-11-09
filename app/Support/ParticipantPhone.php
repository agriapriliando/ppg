<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ParticipantPhone
{
    public static function normalizeForImport(string $number): string
    {
        $number = trim($number);
        if (str_starts_with($number, '08')) {
            return '62'.substr($number, 1);
        }
        if (str_starts_with($number, '+628')) {
            return substr($number, 1);
        }

        return $number;
    }

    public static function whatsappUrl(?string $number, string $participantName): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        try {
            $message = 'Halo Bapak/Ibu '.$participantName.', Peserta PPG IAKN Palangka Raya';

            return 'https://wa.me/'.ltrim(WhatsappNumber::normalize($number), '+').'?text='.rawurlencode($message);
        } catch (ValidationException) {
            return null;
        }
    }

    /** @return list<string> */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:100'];
    }
}
