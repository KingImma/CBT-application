<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Actions\Reports\BuildExamClassReport;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

final class GenerateCumulativeResultPdfAction
{
    public function __construct(private BuildExamClassReport $buildReport) {}

    public function execute(ClassArm $arm, Exam $exam): DomPdf
    {
        $report = $this->buildReport->execute($arm, $exam);

        return Pdf::loadView('pdf.exam-cumulative-result', [
            'report' => $report,
            'schoolName' => tenant('name') ?? 'EduCBT',
        ])->setPaper('a4', 'landscape');
    }

    public function filename(ClassArm $arm, Exam $exam): string
    {
        $slug = Str::slug("{$exam->title}-{$arm->classLevel->name}-{$arm->name}-cumulative");

        return "{$slug}.pdf";
    }
}