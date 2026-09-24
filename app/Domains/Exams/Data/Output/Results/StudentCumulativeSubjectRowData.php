<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;

final class StudentCumulativeSubjectRowData extends Data
{
    public function __construct(
        public readonly string $subject_id,
        public readonly string $subject_name,
        /** @var array<string, array{ca: ?float, exam: ?float, total: float, grade: ?string}> keyed by term_id */
        public readonly array $terms,
    ) {
    }
}
