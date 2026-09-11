<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Persistence\UniqueConstraint;
use Illuminate\Database\QueryException;

/**
 * @deprecated Use {@see UniqueConstraint::isViolation()} from the Persistence
 * module instead. Kept as a delegating shim so the detection logic lives in
 * one place while legacy references are migrated.
 */
final class DatabaseHelper
{
    /**
     * Check if a QueryException is a unique constraint violation,
     * regardless of the underlying database driver.
     */
    public static function isUniqueViolation(QueryException $e): bool
    {
        return UniqueConstraint::isViolation($e);
    }
}
