<?php

namespace App\Support;

class ApplicantPassword
{
    public const NAMES = [
        'adam', 'hawa', 'nuh', 'abraham', 'sara', 'ishak', 'ribka', 'yakub',
        'rahel', 'yusuf', 'musa', 'harun', 'miryam', 'yosua', 'kaleb', 'debora',
        'gideon', 'simson', 'rut', 'naomi', 'samuel', 'saul', 'daud', 'salomo',
        'elia', 'elisa', 'yesaya', 'yeremia', 'yehezkiel', 'daniel', 'ester', 'ayub',
        'yunus', 'ezra', 'nehemia', 'maria', 'marta', 'petrus', 'yohanes', 'paulus',
    ];

    public static function generate(?string $previous = null): string
    {
        $index = random_int(0, count(self::NAMES) - 1);
        if (self::NAMES[$index] === strtolower($previous ?? '')) {
            $index = ($index + random_int(1, count(self::NAMES) - 1)) % count(self::NAMES);
        }

        return self::NAMES[$index];
    }
}
