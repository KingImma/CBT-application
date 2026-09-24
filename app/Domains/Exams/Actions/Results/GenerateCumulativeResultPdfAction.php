<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Actions\Results\BuildStudentCumulativeResult;
use App\Domains\Exams\Support\ResolveSchoolPdfHeader;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

final class GenerateCumulativeResultPdfAction
{
    public function __construct(
        private BuildStudentCumulativeResult $build,
        private ResolveSchoolPdfHeader $schoolHeader,
    ) {
    }

    public function execute(User $student, AcademicSession $session): DomPdf
    {
        $result = $this->build->execute($student, $session);

        return Pdf::loadView('pdf.exam-cumulative-result', [
            'result' => $result,
            'school' => $this->schoolHeader->execute(),
        ])->setPaper('a4');
    }

    public function filename(User $student, AcademicSession $session): string
    {
        return Str::slug("{$student->first_name}-{$student->last_name}-cumulative-{$session->name}") . '.pdf';
    }
}
