<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Support\ExamQuestionRules;
use App\Domains\Tenancy\Exceptions\BaseDomainException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

/**
 * Randomly selects $count questions from the bank and bulk-inserts them.
 * Plain class â€” no base primitive fits a bulk-insert-from-query pattern.
 */
final class RandomizeExamQuestions
{
    public function __construct(private RecomputeExamTotalMarks $recompute) {}

    public function execute(Exam $exam, int $count): void
    {
        ExamQuestionRules::isDraft('Questions can only be randomized for draft exams.')($exam);

        $existingIds = $exam->examQuestions()->pluck('question_id');

        $available = Question::where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->where('is_active', true)
            ->whereNotIn('id', $existingIds);

        throw_if(
            $available->count() < $count,
            new BaseDomainException("Only {$available->count()} questions available, {$count} requested.")
        );

        $questions = $available->inRandomOrder()->limit($count)->get();
        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        DB::transaction(function () use ($exam, $questions, $maxOrder) {
            $exam->examQuestions()->createMany(
                $questions->map(fn ($q, $i) => [
                    'question_id' => $q->id,
                    'order' => $maxOrder + $i + 1,
                    'marks' => $q->default_marks,
                ])->toArray()
            );

            $this->recompute->execute($exam);
        });
    }
}
