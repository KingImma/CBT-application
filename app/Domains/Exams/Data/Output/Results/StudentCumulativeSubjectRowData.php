<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;

final class StudentCumulativeSubjectRowData extends Data
{
    public function __construct(
        public readonly string $subject_id,
        public readonly string $subject_name,
        /**
         * Keyed by term_id. A row only carries a term when the student has at
         * least one graded assessment in that term for this subject.
         *
         * @var array<string, array{ca: ?float, exam: ?float, total: float, grade: ?string, remark: ?string}>
         */
        public readonly array $terms,
    ) {
    }
}
