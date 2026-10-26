<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class WhatsappNumber
{
    public static function normalize(string $number): string
    {
        $number = preg_replace('/[\s()-]/', '', trim($number)) ?? '';
        if (str_starts_with($number, '08')) {
            $number = '+62'.substr($number, 1);
        } elseif (str_starts_with($number, '62')) {
            $number = '+'.$number;
        }
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Gunakan nomor WhatsApp yang sah, misalnya 0812… atau +62812….']);
        }

        return $number;
    }
}
