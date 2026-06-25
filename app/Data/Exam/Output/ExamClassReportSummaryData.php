<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use Spatie\LaravelData\Resource;

class ExamClassReportSummaryData extends Resource
{
    public function __construct(
        public readonly string $exam_id,
        public readonly string $exam_name,
        public readonly string $class_arm_id,
        public readonly string $class_arm_name,
        public readonly int $students_in_class,
        public readonly int $students_sat,
        public readonly ?float $average_score,
        public readonly ?float $highest_score,
        public readonly ?float $lowest_score,
        public readonly ?int $pass_count,
        public readonly ?int $fail_count,
        public readonly string $completion_status,
        public readonly ?float $completion_rate,
        public readonly string $exam_status,
    ) {}
}
