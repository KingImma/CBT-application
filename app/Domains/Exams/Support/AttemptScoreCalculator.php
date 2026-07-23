<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use App\Domains\Exams\ValueObjects\GradedAnswer;
use App\Domains\Exams\ValueObjects\Marks;
use App\Domains\Questions\Support\QuestionGrader;
use Illuminate\Support\Collection;

final class AttemptScoreCalculator
{
    public function __construct(private QuestionGrader $questionGrader) {}

    /**
     * @param  Collection  $submittedAnswers  ExamAnswer models, question.options eager loaded
     * @param  Collection  $examQuestionsByQuestionId  ExamQuestion models keyed by question_id
     * @return array<int, GradedAnswer>
     */
    public function gradeAll(Collection $submittedAnswers, Collection $examQuestionsByQuestionId): array
    {
        return $submittedAnswers->map(function ($answer) use ($examQuestionsByQuestionId) {
            $isCorrect = $this->questionGrader->isCorrect(
                questionType: $answer->question->type,
                options: $answer->question->options,
                selectedIds: $answer->selected_option_ids ?? [],
                textAnswer: $answer->text_answer,
            );

            if (! $isCorrect) {
                return GradedAnswer::incorrect($answer->id);
            }

            $marks = Marks::of((float) (
                $examQuestionsByQuestionId->get($answer->question_id)?->getEffectiveMarks()
                ?? $answer->question->default_marks
            ));

            return GradedAnswer::correct($answer->id, $marks);
        })->all();
    }

    /** @param  array<int, GradedAnswer>  $gradedAnswers */
    public function total(array $gradedAnswers): Marks
    {
        $total = Marks::zero();

        foreach ($gradedAnswers as $gradedAnswer) {
            $total = $total->add($gradedAnswer->marksAwarded);
        }

        return $total;
    }
}
