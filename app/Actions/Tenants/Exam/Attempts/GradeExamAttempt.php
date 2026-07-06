<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Attempts;

use App\Actions\Base\UpdateAction;
use App\Actions\Exam\CalculateScore;
use App\Actions\Exam\ResolveGrade;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use App\Support\QuestionGrader;
use Illuminate\Support\Collection;
use App\Actions\Tenants\Exam\Attempts\ExamAttemptGuards;

final class GradeExamAttempt
{
    public function __construct(
        private UpdateAction $action,
        private QuestionGrader $grader,
    ) {}

    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        return $this->action->execute(
            $attempt,
            ['exam' => $attempt->exam],
            guard: ExamAttemptGuards::canGrade(),
            prepare: fn (ExamAttempt $a, array $d) => $this->computeScores($a, $d['exam']),
            after: fn (ExamAttempt $a, array $d) => $this->updateExamCompletion($a, $d['exam']),
        );
    }

    private function computeScores(ExamAttempt $attempt, Exam $exam): array
    {
        $answers = ExamAnswer::with('question.options')->where('attempt_id', $attempt->id)->get();
        $examQuestions = $exam->examQuestions()->get()->keyBy('question_id');

        ['total' => $total, 'maxTime' => $maxTime] = $this->gradeAnswers($answers, $examQuestions);

        $percentage = CalculateScore::execute($total, (float) $exam->total_marks);
        $grade = ResolveGrade::execute($percentage, GradingScale::where('is_default', true)->first()?->grades);

        return [
            'status' => ExamAttemptStatus::Graded->value,
            'total_score' => $total,
            'percentage_score' => $percentage,
            'grade' => $grade,
            'time_spent_seconds' => $maxTime ?: (int) now()->diffInSeconds($attempt->started_at),
        ];
    }

    private function gradeAnswers(Collection $answers, Collection $examQuestions): array
    {
        $total = 0.0;
        $maxTime = 0;

        foreach ($answers as $answer) {
            $isCorrect = $this->grader->isCorrect(
                questionType: $answer->question->type,
                options: $answer->question->options,
                selectedIds: $answer->selected_option_ids ?? [],
                textAnswer: $answer->text_answer,
            );

            $marks = $isCorrect
                ? (float) ($examQuestions->get($answer->question_id)?->getEffectiveMarks() ?? $answer->question->default_marks)
                : 0.0;

            $answer->updateQuietly(['is_correct' => $isCorrect, 'marks_awarded' => $marks]);

            $total += $marks;
            $maxTime = max($maxTime, $answer->time_spent_seconds ?? 0);
        }

        return ['total' => $total, 'maxTime' => $maxTime];
    }

    private function updateExamCompletion(ExamAttempt $attempt, Exam $exam): void
    {
        $exam->increment('completed_attempts');
        $exam->refresh();

        $shouldComplete = $exam->completed_attempts >= $exam->expected_attempts
            || ($exam->window_end !== null && now()->gte($exam->window_end));

        if ($shouldComplete) {
            $exam->update(['status' => ExamStatus::Completed]);
        }

        event(new ExamAttemptsUpdated(
            examId: $exam->id,
            completedAttempts: $exam->completed_attempts,
            expectedAttempts: $exam->expected_attempts,
            status: $shouldComplete ? ExamStatus::Completed : ExamStatus::Active,
            tenantId: (string) tenant('id'),
        ));
    }
}
