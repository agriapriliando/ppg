<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class UserPhoneNumber
{
    public static function normalize(?string $number): ?string
    {
        if ($number === null || trim($number) === '') {
            return null;
        }
        try {
            return ltrim(WhatsappNumber::normalize($number), '+');
        } catch (ValidationException) {
            throw ValidationException::withMessages(['phone_number' => 'Masukkan nomor HP WhatsApp yang valid, misalnya 081234567890 atau 6281234567890.']);
        }
    }
}
