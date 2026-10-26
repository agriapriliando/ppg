<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draf';
    case Submitted = 'terkirim';
    case Verified = 'diverifikasi';
    case Correction = 'perbaikan';
    case Accepted = 'diterima_lengkap';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf', self::Submitted => 'Terkirim', self::Verified => 'Diverifikasi', self::Correction => 'Perlu perbaikan', self::Accepted => 'Diterima lengkap',
        };
    }

    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::Correction], true);
    }
}
