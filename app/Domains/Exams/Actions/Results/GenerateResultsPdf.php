<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Data\Output\ExamResultData;
use App\Models\Tenant\ExamAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

final class GenerateResultsPdf
{
    public function execute(ExamAttempt $attempt): DomPdf
    {
        $attempt->loadMissing([
            'student',
            'exam.subject',
            'exam.classLevel',
            'exam.examQuestions.question',
            'answers.question.options'
        ]);

        $resultData = ExamResultData::fromAttempt($attempt);

        return Pdf::loadView('pdf.exam-result', [
            'result' => $resultData,
            'attempt' => $attempt,
            'schoolName' => tenant('name') ?? 'EduCBT'
        ])->setPaper('a4');
    }

    public function filename(ExamAttempt $attempt): string
    {
        $student = $attempt->student;
        $exam = $attempt->exam;

        $slug = Str::slug("{$exam->title} for {$student->first_name}-{$student->last_name}");

        return "{$slug}.pdf";
    }
}
