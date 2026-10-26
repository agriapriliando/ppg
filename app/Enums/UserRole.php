<?php

namespace App\Enums;

enum UserRole: string
{
    case Applicant = 'pendaftar_ppg';
    case Operator = 'operator';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Pendaftar',
            self::Operator => 'Operator',
            self::Administrator => 'Administrator',
        };
    }
}
