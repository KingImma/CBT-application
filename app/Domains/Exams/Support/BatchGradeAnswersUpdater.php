<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use App\Domains\Exams\ValueObjects\GradedAnswer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BatchGradeAnswersUpdater
{
    /** @param array<int, GradedAnswer> $gradedAnswers */
    public function execute(array $gradedAnswers): void
    {
        if ($gradedAnswers === []) {
            return;
        }

        $this->assertNoDuplicateAnswerIds($gradedAnswers);

        $answerIds = [];
        $isCorrectCaseSql = '';
        $marksAwardedCaseSql = '';
        $isCorrectBindings = [];
        $marksAwardedBindings = [];

        foreach ($gradedAnswers as $gradedAnswer) {
            $answerIds[] = $gradedAnswer->answerId;

            $isCorrectCaseSql .= "WHEN ? THEN {$this->toSqlBoolLiteral($gradedAnswer->isCorrect)} ";
            $isCorrectBindings[] = $gradedAnswer->answerId;

            $marksAwardedCaseSql .= 'WHEN ? THEN ? ';
            $marksAwardedBindings[] = $gradedAnswer->answerId;
            $marksAwardedBindings[] = $gradedAnswer->marksAwarded->value;
        }

        $whereInPlaceholders = implode(',', array_fill(0, count($answerIds), '?'));

        // Binding order MUST match placeholder order left-to-right:
        // is_correct CASE, then marks_awarded CASE, then WHERE IN.
        DB::update(
            "UPDATE exam_answers SET
                is_correct = CASE id {$isCorrectCaseSql} ELSE is_correct END,
                marks_awarded = CASE id {$marksAwardedCaseSql} ELSE marks_awarded END
             WHERE id IN ({$whereInPlaceholders})",
            [...$isCorrectBindings, ...$marksAwardedBindings, ...$answerIds],
        );
    }

    /** @param  array<int, GradedAnswer>  $gradedAnswers */
    private function assertNoDuplicateAnswerIds(array $gradedAnswers): void
    {
        $ids = array_map(fn (GradedAnswer $g) => $g->answerId, $gradedAnswers);

        if (count($ids) !== count(array_unique($ids))) {
            throw new InvalidArgumentException('Duplicate answer in graded batch');
        }
    }

    private function toSqlBoolLiteral(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
