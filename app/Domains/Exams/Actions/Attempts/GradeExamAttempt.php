<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Attempts;

use App\Domains\Exams\Actions\ResolveGrade;
use App\Domains\Exams\ValueObjects\AttemptGradeResult;
use App\Domains\Exams\ValueObjects\Percentage;
use App\Domains\Exams\Events\ExamAttemptsUpdated;
use App\Domains\Exams\State\ExamAttemptStateMachine;
use App\Domains\Exams\Support\AttemptScoreCalculator;
use App\Domains\Exams\Support\BatchGradeAnswersUpdater;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamResult;
use App\Models\Tenant\GradingScale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class GradeExamAttempt
{
    public function __construct(
        private AttemptScoreCalculator $attemptScoreCalculator,
        private BatchGradeAnswersUpdater $batchGradeAnswersUpdater,
        private ExamAttemptStateMachine $stateMachine,
    ) {}

    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        $this->stateMachine->grade($attempt);

        return DB::transaction(function () use ($attempt) {
            $exam = $attempt->exam;

            $gradeResult = $this->scoreAndPersistAnswers($attempt, $exam);

            $attempt->update($gradeResult->toAttemptAttributes());
            $gradedAttempt = $attempt->fresh();

            $this->persistResultAndCascade($gradedAttempt, $exam, $gradeResult);

            return $gradedAttempt;
        });
    }

    private function scoreAndPersistAnswers(ExamAttempt $attempt, Exam $exam): AttemptGradeResult
    {
        Log::debug('GradeExamAttempt: scoring started', [
            'attempt_id' => $attempt->id,
            'exam_id' => $exam->id,
        ]);

        $submittedAnswers = ExamAnswer::with('question.options')
            ->where('attempt_id', $attempt->id)
            ->get();

        $examQuestionsByQuestionId = $exam->examQuestions()->get()->keyBy('question_id');

        $gradedAnswers = $this->attemptScoreCalculator->gradeAll($submittedAnswers, $examQuestionsByQuestionId);
        $totalScore = $this->attemptScoreCalculator->total($gradedAnswers);

        $this->batchGradeAnswersUpdater->execute($gradedAnswers);

        $percentage = Percentage::fromRatio($totalScore->value, (float) $exam->total_marks);
        $letterGrade = ResolveGrade::execute(
            $percentage->value,
            $this->defaultGradingScale()?->grades,
        );

        $result = AttemptGradeResult::compute(
            totalScore: $totalScore,
            percentage: $percentage,
            letterGrade: $letterGrade,
            passMark: $exam->pass_mark !== null ? (float) $exam->pass_mark : null,
            timeSpentSeconds: $this->resolveTimeSpent($submittedAnswers, $attempt),
        );

        Log::debug('GradeExamAttempt: scoring complete', [
            'attempt_id' => $attempt->id,
            'total' => $result->totalScore->value,
            'percentage' => $result->percentageScore->value,
            'grade' => $result->letterGrade,
        ]);

        return $result;
    }

    private function resolveTimeSpent($submittedAnswers, ExamAttempt $attempt): int
    {
        $maxTime = $submittedAnswers->max('time_spent_seconds') ?? 0;

        return $maxTime ?: (int) abs(($attempt->submitted_at ?? now())->diffInSeconds($attempt->started_at));
    }

    private function persistResultAndCascade(ExamAttempt $gradedAttempt, Exam $exam, AttemptGradeResult $gradeResult): void
    {
        $examResult = ExamResult::updateOrCreate(
            ['exam_attempt_id' => $gradedAttempt->id],
            array_merge($gradeResult->toResultAttributes(), [
                'student_id' => $gradedAttempt->student_id,
                'exam_id' => $exam->id,
                'subject_id' => $exam->subject_id,
                'term_id' => $exam->term_id,
                'academic_session_id' => $gradedAttempt->academic_session_id ?? $exam->term?->academic_session_id,
            ]),
        );

        // Guards against double-incrementing completed_attempts if this
        // action re-runs against an already-graded attempt (manual recompute).
        if ($examResult->wasRecentlyCreated) {
            $this->advanceExamCompletionState($exam);
        }
    }

    private function advanceExamCompletionState(Exam $exam): void
    {
        $exam->increment('completed_attempts');

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

    /**
     * The default grading scale is tenant-global and changes rarely, so it is
     * cached per tenant instead of re-queried on every graded attempt.
     */
    private function defaultGradingScale(): ?GradingScale
    {
        $cacheKey = 'grading_scale:default:'.tenant('id');

        return Cache::remember($cacheKey, now()->addDay(), function () {
            return GradingScale::where('is_default', true)->first();
        });
    }
}
