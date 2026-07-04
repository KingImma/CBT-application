<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Report;

use App\Data\Exam\Output\ExamClassReportData;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;

/** Composition root for the class report: query → summarise → map students. */
final class BuildExamClassReport
{
    public function __construct(
        private ExamClassReportQuery       $query,
        private ComputeExamClassSummary    $computeSummary,
        private MapExamClassReportStudents $mapStudents,
    ) {}

    public function execute(ClassArm $arm, Exam $exam): ExamClassReportData
    {
        $data = $this->query->execute($arm, $exam);

        return new ExamClassReportData(
            summary:  $this->computeSummary->execute($exam, $arm, $data->students, $data->attemptsByStudentId),
            students: $this->mapStudents->execute($exam, $data->students, $data->attemptsByStudentId)->all(),
        );
    }
}
