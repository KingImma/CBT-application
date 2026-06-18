<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class NormalizeName
{
    public static function canonical(string $value): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim($value)));
    }
}
