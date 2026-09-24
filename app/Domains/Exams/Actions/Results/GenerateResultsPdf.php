<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Actions\Results\BuildStudentCumulativeResult;
use App\Domains\Exams\Support\ResolveSchoolPdfHeader;
use App\Domains\Exams\Data\Output\ExamResultData;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Term;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

final class GenerateResultsPdf
{
    public function __construct(
        private BuildStudentCumulativeResult $buildCumulative,
        private ResolveSchoolPdfHeader $schoolHeader,
    ) {
    }

    /** Default — per subject, current term. Same route as before. */
    public function execute(ExamAttempt $attempt): DomPdf
    {
        return $this->perSubject($attempt);
    }

    /** For a future explicit route. */
    public function executePerQuestion(ExamAttempt $attempt): DomPdf
    {
        return $this->perQuestion($attempt);
    }

    public function filename(ExamAttempt $attempt): string
    {
        $student = $attempt->student;
        $exam = $attempt->exam;

        return Str::slug("{$exam->title} for {$student->first_name}-{$student->last_name}") . '.pdf';
    }

    private function perSubject(ExamAttempt $attempt): DomPdf
    {
        $attempt->loadMissing(['student.studentProfile', 'term', 'exam.term']);

        $term = $attempt->term ?? $attempt->exam->term;

        $result = $this->buildCumulative->executeForTerm($attempt->student, $term);

        return Pdf::loadView('pdf.exam-result-summary', [
            'result' => $result,
            'school' => $this->schoolHeader->execute(),
        ])->setPaper('a4');
    }

    private function perQuestion(ExamAttempt $attempt): DomPdf
    {
        $attempt->loadMissing([
            'student',
            'exam.subject',
            'exam.classLevel',
            'exam.examQuestions.question',
            'answers.question.options',
        ]);

        $resultData = ExamResultData::fromAttempt($attempt);

        return Pdf::loadView('pdf.exam-result', [
            'result' => $resultData,
            'attempt' => $attempt,
            'schoolName' => $this->schoolHeader->execute()['name'],
        ])->setPaper('a4');
    }
}
