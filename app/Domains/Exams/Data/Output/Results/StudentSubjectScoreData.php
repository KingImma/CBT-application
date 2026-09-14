<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;

final class StudentSubjectScoreData extends Data
{
    public function __construct(
        public readonly string $subject_id,
        public readonly float $ca,
        public readonly float $exam,
        public readonly float $total,
    ) {
    }
}
