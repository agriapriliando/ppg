<?php

namespace App\Enums;

enum AccountState: string
{
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::PendingActivation => 'Menunggu aktivasi',
            self::Active => 'Aktif',
            self::Disabled => 'Nonaktif',
        };
    }
}
