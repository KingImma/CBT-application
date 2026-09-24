<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class StudentCumulativeResultData extends Data
{
    public function __construct(
        public readonly string $student_id,
        public readonly string $admission_number,
        public readonly string $student_name,
        public readonly string $class_level_name,
        public readonly ?string $class_arm_name,
        public readonly string $academic_session_name,
        /** @var array<int, array{id: string, name: string}> ordered oldest→newest */
        public readonly array $terms,
        /** @var DataCollection<int, StudentCumulativeSubjectRowData> */
        public readonly DataCollection $subjects,
        public readonly ?string $gender,
        public readonly int $number_in_class,
        public readonly int $position,
        public readonly float $total_obtained,
        public readonly float $total_obtainable,
        public readonly float $average,
        public readonly ?string $evaluation,
        public readonly ?string $school_section,
        /** @var array<int, array{label: string, min_score: float|int, max_score: float|int, remark?: ?string}> */
        public readonly array $grades,
    ) {
    }
}
