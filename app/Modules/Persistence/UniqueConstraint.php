<?php

declare(strict_types=1);

namespace App\Modules\Persistence;

use Illuminate\Database\QueryException;

/**
 * Detects unique-constraint violations regardless of the underlying database
 * driver, so domain actions can translate them into a meaningful domain
 * exception instead of leaking a raw {@see QueryException}.
 *
 * SQLSTATEs covered:
 *  - 23505 PostgreSQL unique_violation
 *  - 23000 MySQL/ANSI integrity constraint violation
 *  - 1062  MySQL ER_DUP_ENTRY
 */
final class UniqueConstraint
{
    private const SQLSTATES = ['23505', '23000', '1062'];

    public static function isViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), self::SQLSTATES, true);
    }
}
