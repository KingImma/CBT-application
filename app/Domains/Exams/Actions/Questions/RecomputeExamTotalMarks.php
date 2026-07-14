<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Enums\ExamType;
use App\Models\Tenant\Exam;
use App\Models\Tenant\SchoolSetting;
use DomainException;

/**
 * Pure utility â€” recomputes total_marks from live examQuestions.
 * Called after every question add/update/delete/randomize.
 * Not a CRUD action â€” no base primitive needed.
 */
final class RecomputeExamTotalMarks
{
    public function execute(Exam $exam): void
    {
        $total = (float) $exam->examQuestions()
            ->get()
            ->sum(fn ($eq) => $eq->getEffectiveMarks());

        $this->assertWithinSchoolMax($exam, $total);

        $exam->update(['total_marks' => $total]);
    }

    private function assertWithinSchoolMax(Exam $exam, float $total): void
    {
        $key = $exam->type === ExamType::Exam->value
            ? 'exam_max_score'
            : 'assessment_max_score';

        $max = SchoolSetting::where('key', $key)->value('value');

        if ($max === null) {
            return;
        }

        $max = (float) $max;

        throw_if($max <= 0, new DomainException("School max score for {$exam->type} is not configured."));
        throw_if($total > $max, new DomainException("Total marks ({$total}) exceeds school max of {$max} for {$exam->type}."));
    }
}
