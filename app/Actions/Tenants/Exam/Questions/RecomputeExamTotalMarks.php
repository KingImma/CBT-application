<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Enums\ExamType;
use App\Models\Tenant\Exam;
use App\Models\Tenant\SchoolSetting;
use DomainException;

class RecomputeExamTotalMarks
{
    private const SETTING_KEY_EXAM_MAX_SCORE = 'exam_max_score';
    private const SETTING_KEY_ASSESSMENT_MAX_SCORE = 'assessment_max_score';

    public function execute(Exam $exam): void
    {
        $total = $exam->examQuestions()->get()->sum(fn ($eq) => $eq->getEffectiveMarks());
        
        $this->ensureWithinSchoolMaximum($exam, (float) $total);

        $exam->update(['total_marks' => $total]);
    }

    private function ensureWithinSchoolMaximum(Exam $exam, float $total): void
    {
        $settingKey = $exam->type === ExamType::Exam->value
            ? self::SETTING_KEY_EXAM_MAX_SCORE
            : self::SETTING_KEY_ASSESSMENT_MAX_SCORE;
        
        $schoolMax = SchoolSetting::where('key', $settingKey)->value('value');

        if ($schoolMax === null) return;

        $schoolMaxFloat = (float) $schoolMax;

        throw_if(
            $schoolMaxFloat <= 0,
            DomainException::class,
            "School maximum score for {$exam->type} is not configured correctly."
        );

        throw_if(
            $total > $schoolMaxFloat,
            DomainException::class,
            "Total marks cannot exceed school maximum of {$schoolMax} for {$exam->type}."
        );
    }
}
