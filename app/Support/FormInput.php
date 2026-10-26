<?php

namespace App\Support;

class FormInput
{
    public static function old(string $key, mixed $default = null): string|int|float|bool|null
    {
        $value = old($key, $default);

        return is_scalar($value) || $value === null ? $value : null;
    }
}
