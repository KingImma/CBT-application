<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAttempt\Concerns;

use App\Domains\Exams\Actions\CalculateScore;
use App\Domains\Exams\Actions\ResolveGrade;
use App\Domains\Questions\Support\QuestionGrader;

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

            $isCorrect = app(QuestionGrader::class)->isCorrect(
                questionType: $question->type,
                options: $question->options,
                selectedIds: $answer['selected_option_ids'] ?? [],
                textAnswer: $answer['text_answer'] ?? null,
            );

            $marksAwarded = 0.0;
            if ($isCorrect) {
                $marksAwarded = (float) ($examQuestion->getEffectiveMarks() ?? $question->default_marks);
            }

            $answerModel = $this->answers()->where('question_id', $answer['question_id'])->first();
            if ($answerModel) {
                if ($isCorrect) {
                    $answerModel->markCorrect($marksAwarded);
                } else {
                    $answerModel->markIncorrect();
                }

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
