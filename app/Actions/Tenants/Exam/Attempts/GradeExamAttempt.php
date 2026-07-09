<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Attempts;

use App\Actions\Base\UpdateAction;
use App\Actions\Exam\CalculateScore;
use App\Actions\Exam\ResolveGrade;
use App\Actions\Tenants\Exam\ExamAttemptGuards;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamResult;
use App\Models\Tenant\GradingScale;
use App\Support\QuestionGrader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class GradeExamAttempt
{
    public function __construct(
        private UpdateAction $action,
        private QuestionGrader $grader,
    ) {}

    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        $guard = ExamAttemptGuards::canGrade();
        $guard($attempt);

        // Own transaction boundary — grading is one atomic unit. If anything
        // throws below, answers/exam_result/completed_attempts writes all
        // roll back together. Control returns to FinalizeAttempt::submit()
        // AFTER this rollback completes, so its Failed-status write is safe.
        return DB::transaction(function () use ($attempt, $guard) {
            $grades = $this->computeAndScore($attempt);

            return $this->action->execute(
                $attempt,
                ['grades' => $grades],
                guard: $guard,
                prepare: fn (ExamAttempt $a, array $d) => $d['grades'],
                after: fn (ExamAttempt $a, array $d) => $this->onGraded($a, $d['grades']),
            );
        });
    }

    private function onGraded(ExamAttempt $attempt, array $grades): void
    {
        $exam = $grades['exam'];

        $result = ExamResult::updateOrCreate(
            ['exam_attempt_id' => $attempt->id],
            [
                'student_id' => $attempt->student_id,
                'exam_id' => $exam->id,
                'subject_id' => $exam->subject_id,
                'term_id' => $exam->term_id,
                'academic_session_id' => $attempt->academic_session_id ?? $exam->term?->academic_session_id,
                'total_score' => $grades['total_score'],
                'percentage_score' => $grades['percentage_score'],
                'grade' => $grades['grade'],
                'objective_score' => $grades['objective_score'] ?? null,
                'theory_score' => $grades['theory_score'] ?? null,
                'passed' => $exam->pass_mark !== null
                    ? (float) ($grades['percentage_score'] ?? 0) >= (float) $exam->pass_mark
                    : null,
                'graded_at' => now(),
            ],
        );

        if ($result->wasRecentlyCreated) {
            $this->updateExamCompletion($attempt, $exam);
        }
    }

    private function computeAndScore(ExamAttempt $attempt): array
    {
        $exam = $attempt->exam;

        Log::debug('GradeExamAttempt: scoring started', [
            'attempt_id' => $attempt->id,
            'exam_id' => $exam->id,
        ]);

        $answers = ExamAnswer::with('question.options')
            ->where('attempt_id', $attempt->id)
            ->get();

        $examQuestions = $exam->examQuestions()->get()->keyBy('question_id');

        $total = 0.0;
        $objectiveTotal = 0.0;
        $theoryTotal = 0.0;
        $maxTime = 0;
        $gradedAnswers = [];

        foreach ($answers as $answer) {
            $isCorrect = $this->grader->isCorrect(
                questionType: $answer->question->type,
                options: $answer->question->options,
                selectedIds: $answer->selected_option_ids ?? [],
                textAnswer: $answer->text_answer,
            );

            $marks = $isCorrect
                ? (float) ($examQuestions
                    ->get($answer->question_id)
                    ?->getEffectiveMarks() ?? $answer->question->default_marks)
                : 0.0;

            $type = $answer->question->type;
            if (in_array($type, ['mcq', 'true_false', 'fill_in_blank'], true)) {
                $objectiveTotal += $marks;
            } else {
                $theoryTotal += $marks;
            }

            $gradedAnswers[] = [
                'id' => $answer->id,
                'is_correct' => $isCorrect,
                'marks_awarded' => $marks,
            ];

            $total += $marks;
            $maxTime = max($maxTime, $answer->time_spent_seconds ?? 0);
        }

        $this->batchUpdateAnswers($gradedAnswers);

        $percentage = CalculateScore::execute($total, (float) $exam->total_marks);

        $grade = ResolveGrade::execute(
            $percentage,
            GradingScale::where('is_default', true)->first()?->grades,
        );

        Log::debug('GradeExamAttempt: scoring complete', [
            'attempt_id' => $attempt->id,
            'total' => $total,
            'percentage' => $percentage,
            'grade' => $grade,
        ]);

        return [
            'status' => ExamAttemptStatus::Graded->value,
            'total_score' => $total,
            'percentage_score' => $percentage,
            'grade' => $grade,
            'objective_score' => $objectiveTotal,
            'theory_score' => $theoryTotal,
            'time_spent_seconds' => $maxTime ?: (int) abs(($attempt->submitted_at ?? now())->diffInSeconds($attempt->started_at)),
            'exam' => $exam,
        ];
    }

    private function batchUpdateAnswers(array $gradedAnswers): void
    {
        if ($gradedAnswers === []) {
            return;
        }

        $ids = [];
        $caseSql = '';
        $bindings = [];

        foreach ($gradedAnswers as $data) {
            $ids[] = (string) $data['id'];
            $isCorrect = $data['is_correct'] ? 'true' : 'false';
            $caseSql .= "WHEN ? THEN {$isCorrect} ";
            $bindings[] = $data['id'];
        }

        $sql = 'UPDATE exam_answers SET is_correct = CASE id '.$caseSql.'ELSE is_correct END, ';
        $caseSql = '';
        foreach ($gradedAnswers as $data) {
            $marks = (string) (float) $data['marks_awarded'];
            $caseSql .= "WHEN ? THEN {$marks} ";
            $bindings[] = $data['id'];
        }
        $sql .= 'marks_awarded = CASE id '.$caseSql.'ELSE marks_awarded END ';
        $sql .= 'WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')';
        $bindings = array_merge($bindings, $ids);

        DB::update($sql, $bindings);
    }

    private function updateExamCompletion(ExamAttempt $attempt, Exam $exam): void
    {
        $exam->increment('completed_attempts');
        $exam->refresh();

        $shouldComplete =
            $exam->completed_attempts >= $exam->expected_attempts ||
            ($exam->window_end !== null && now()->gte($exam->window_end));

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
