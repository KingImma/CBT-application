<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class BroadsheetStudentData extends Data
{
    public function __construct(
        public readonly string $student_id,
        public readonly string $full_name,
        /** @var DataCollection<int, StudentSubjectScoreData> */
        public readonly DataCollection $subjects,
        public readonly float $total_score,
        public readonly float $average_score,
        public readonly int $position,
    ) {
    }
}
