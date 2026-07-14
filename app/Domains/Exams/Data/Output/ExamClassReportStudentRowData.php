<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output;

use Spatie\LaravelData\Resource;

class ExamClassReportStudentRowData extends Resource
{
    public function __construct(
        public readonly string $student_id,
        public readonly string $student_name,
        public readonly ?float $score,
        public readonly ?float $percentage,
        public readonly ?string $grade,
        public readonly ?string $result_status,
        public readonly ?string $submitted_at,
        public readonly ?string $completed_at,
    ) {}
}
