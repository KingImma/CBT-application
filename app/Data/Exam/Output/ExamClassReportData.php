<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use Spatie\LaravelData\Resource;

class ExamClassReportData extends Resource
{
    public function __construct(
        public readonly ExamClassReportSummaryData $summary,

        /** @var array<ExamClassReportStudentRowData> */
        public readonly array $students,
    ) {}
}
