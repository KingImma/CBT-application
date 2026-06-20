<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Exceptions\Domain\BaseDomainException;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RandomizeExamQuestions
{
    public function __construct(
        private RecomputeExamTotalMarks $recomputeMarks
    ) {}

    public function execute(Exam $exam, int $count): void
    {
        $this->validateState($exam);

        $query = $this->getAvailableQuestionsQuery($exam);
        
        $this->ensureEnoughQuestions($query, $count);

        DB::transaction(fn () => $this->performRandomization($exam, $query, $count));
    }

    /**
     * Fail Fast Guard Clauses
     */
    private function validateState(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            'Questions can only be randomized for draft exams.'
        );
    }

    private function ensureEnoughQuestions(Builder $query, int $count): void
    {
        $availableCount = $query->count();

        throw_if(
            $availableCount < $count,
            BaseDomainException::class,
            "Only {$availableCount} new questions available, but {$count} requested."
        );
    }

    /**
     * Execution Layer
     */
    private function performRandomization(Exam $exam, Builder $query, int $count): void
    {
        $questions = $query->inRandomOrder()->limit($count)->get();
        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        // Map the selected questions into a clean array for bulk insertion
        $examQuestionsData = $questions->map(function ($question, $index) use ($maxOrder) {
            return [
                'question_id' => $question->id,
                'order'       => $maxOrder + $index + 1,
                'marks'       => $question->default_marks,
            ];
        });

        // Use createMany to insert all questions in a single, highly-optimized query
        $exam->examQuestions()->createMany($examQuestionsData->toArray());

        // Correctly trigger the sub-action
        $this->recomputeMarks->execute($exam);
    }

    /**
     * Data Retrieval
     */
    private function getAvailableQuestionsQuery(Exam $exam): Builder
    {
        // Pluck existing question IDs to prevent the randomizer from picking duplicates
        $existingQuestionIds = $exam->examQuestions()->pluck('question_id');

        return Question::where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->where('is_active', true)
            ->whereNotIn('id', $existingQuestionIds);
    }
}