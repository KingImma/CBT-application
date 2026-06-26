<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;

final class DatabaseHelper
{
    /**
     * Check if a QueryException is a unique constraint violation,
     * regardless of the underlying database driver.
     */
    public static function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23505', '23000', '1062'], true);
    }
}
