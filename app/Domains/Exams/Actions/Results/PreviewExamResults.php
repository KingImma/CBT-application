<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Data\Output\ExamClassReportData;
use App\Domains\Exams\Actions\Reports\BuildExamClassReport;
use App\Domains\Exams\Exceptions\ExamStateTransitionException;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;

final class PreviewExamResults
{
    public function __construct(private BuildExamReport $buildReport) {}

    public function execute(Exam $cxam, ClassArm $arm): ExamClassReportData
    {
        throw_unless(
            $exam->isCompleted(),
            new ExamStateTransitionException("Results can only ") 
        );

        return $this->buildReport->execute($arm, $exam);
    }
}