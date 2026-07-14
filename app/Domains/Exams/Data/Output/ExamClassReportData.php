<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output;

use Spatie\LaravelData\Resource;

class ExamClassReportData extends Resource
{
    public function __construct(
        public readonly ExamClassReportSummaryData $summary,

        /** @var array<ExamClassReportStudentRowData> */
        public readonly array $students,
    ) {}
}
