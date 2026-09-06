<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Data\Output\ExamResultData;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;
use RuntimeException;

final class GenerateBulkResultPdfAction
{
    public function execute(ClassArm $arm, Exam $exam): DomPdf
    {
        $attempts = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereHas('student.studentProfile', fn ($q) => $q
                ->where('class_level_id', $arm->class_level_id)
                ->where('class_arm_id', $arm->id))
            ->completed()
            ->with([
                'student',
                'exam.subject',
                'exam.classLevel',
                'exam.examQuestions.question',
                'answers.question.options',
            ])
            ->orderBy('student_id')
            ->get();

        throw_if(
            $attempts->isEmpty(),
            new RuntimeException('No completed attempts found for this class and exam.')
        );

        $items = $attempts->map(fn (ExamAttempt $attempt) => [
            'attempt' => $attempt,
            'result' => ExamResultData::fromAttempt($attempt), // loadMissing() no-ops here — Phase 2 fix
        ]);

        return Pdf::loadView('pdf.exam-result-bulk', [
            'items' => $items,
            'schoolName' => tenant('name') ?? 'EduCBT',
        ])->setPaper('a4');
    }

    public function filename(ClassArm $arm, Exam $exam): string
    {
        return Str::slug("{$exam->title}-{$arm->classLevel->name}-{$arm->name}-bulk-results").'.pdf';
    }
}