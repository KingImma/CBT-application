<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Persistence;

use App\Modules\Persistence\UniqueConstraint;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UniqueConstraintTest extends TestCase
{
    #[Test]
    public function it_recognises_unique_violations_across_drivers(): void
    {
        $this->assertTrue(UniqueConstraint::isViolation($this->queryException('23505')));
        $this->assertTrue(UniqueConstraint::isViolation($this->queryException('23000')));
        $this->assertTrue(UniqueConstraint::isViolation($this->queryException('1062')));
    }

    #[Test]
    public function it_does_not_treat_other_database_errors_as_unique_violations(): void
    {
        $this->assertFalse(UniqueConstraint::isViolation($this->queryException('42S02')));
        $this->assertFalse(UniqueConstraint::isViolation($this->queryException('0')));
    }

    private function queryException(string $code): QueryException
    {
        return new QueryException(
            'pgsql',
            'insert into "terms" ("name") values (?)',
            ['2026/2027'],
            new PDOException('duplicate key value violates unique constraint', (int) $code),
        );
    }
}
