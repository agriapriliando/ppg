<?php

namespace App\Enums;

enum ParticipantAccountStatus: string
{
    case NoAccount = 'no_account';
    case PendingActivation = 'pending_activation';
    case Connected = 'connected';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::NoAccount => 'Belum Ada Akun',
            self::PendingActivation => 'Menunggu Aktivasi',
            self::Connected => 'Akun Terhubung',
            self::Disabled => 'Nonaktif',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NoAccount => 'draf',
            self::PendingActivation => 'perbaikan',
            self::Connected => 'diterima_lengkap',
            self::Disabled => 'declined',
        };
    }
}
