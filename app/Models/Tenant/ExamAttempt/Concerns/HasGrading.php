<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAttempt\Concerns;

use App\Actions\Exam\CalculateScore;
use App\Actions\Exam\ResolveGrade;

trait HasGrading
{
    public function gradeAnswers(array $answers): array
    {
        $graded = [];
        $runningTotal = 0.0;

        $examQuestions = $this->exam->examQuestions()
            ->with('question.options')
            ->get()
            ->keyBy('question_id');

        foreach ($answers as $answer) {
            $examQuestion = $examQuestions->get($answer['question_id']);
            $question = $examQuestion?->question;

            if (! $examQuestion || ! $question) {
                continue;
            }

            $selected = $answer['selected_option_ids'] ?? [];
            $correctOption = $question->options->firstWhere('is_correct', true);
            $isCorrect = count($selected) === 1 && $correctOption?->id === $selected[0];

            $marksAwarded = 0.0;
            if ($isCorrect) {
                $marksAwarded = (float) ($examQuestion->getEffectiveMarks() ?? $question->default_marks);
            }

            $answerModel = $this->answers()->where('question_id', $answer['question_id'])->first();
            if ($answerModel) {
                $answerModel->markCorrect($marksAwarded);
                $graded[] = $answerModel;
            }

            $runningTotal += $marksAwarded;
        }

        return $graded;
    }

    public function calculateScore(): array
    {
        $totalScore = (float) $this->answers()->sum('marks_awarded');
        $percentageScore = CalculateScore::execute($totalScore, (float) $this->exam->total_marks);
        $grade = ResolveGrade::execute($percentageScore, $this->exam->gradingScale?->grades);

        return [
            'total_score' => $totalScore,
            'percentage_score' => $percentageScore,
            'grade' => $grade,
        ];
    }

    public function recalculate(): self
    {
        $scores = $this->calculateScore();

        $this->total_score = $scores['total_score'];
        $this->percentage_score = $scores['percentage_score'];
        $this->grade = $scores['grade'];

        return $this;
    }
}
