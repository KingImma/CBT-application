<?php

declare(strict_types=1);

namespace App\Domains\Import\Data\Schemas;

abstract class ImportSchema
{
    public const COLUMNS = [];

    public const IDENTITY = [];

    public static function requiredHeaders(): array
    {
        return array_keys(array_filter(static::COLUMNS, fn ($c) => $c['required']));
    }

    public static function allHeaders(): array
    {
        return array_keys(static::COLUMNS);
    }

    public static function missingRequiredHeaders(array $headers): array
    {
        return array_values(array_diff(static::requiredHeaders(), $headers));
    }
}
