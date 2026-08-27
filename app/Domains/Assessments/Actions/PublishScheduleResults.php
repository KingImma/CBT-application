<?php
// app/Domains/Assessments/Actions/PublishScheduleResults.php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Exams\Actions\PublishExamResults;
use App\Domains\Exams\Exceptions\ExamStateTransitionException;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

final class PublishScheduleResults
{
    public function __construct(
        private PublishExamResults $publishExam,
    ) {}

    /**
     * Publish results for every materialized exam under this schedule.
     * Exams not yet Completed are skipped (not fatal) — reported in the
     * result set so the admin sees exactly what happened, not a silent gap.
     *
     * @return array<int, array{exam_id: string, subject_id: ?string, status: string, reason: ?string}>
     */
    public function execute(AssessmentSchedule $schedule): array
    {
        return DB::transaction(function () use ($schedule): array {
            $submissions = $schedule->submissions()
                ->whereNotNull('exam_id')
                ->with('exam')
                ->get();

            return $submissions->map(function ($submission): array {
                $exam = $submission->exam;

                if ($exam === null) {
                    return [
                        'exam_id' => (string) $submission->exam_id,
                        'subject_id' => $submission->subject_id,
                        'status' => 'skipped',
                        'reason' => 'Exam record missing.',
                    ];
                }

                try {
                    $this->publishExam->execute($exam);
                } catch (ExamStateTransitionException $e) {
                    return [
                        'exam_id' => $exam->id,
                        'subject_id' => $submission->subject_id,
                        'status' => 'skipped',
                        'reason' => $e->getMessage(),
                    ];
                }

                return [
                    'exam_id' => $exam->id,
                    'subject_id' => $submission->subject_id,
                    'status' => 'published',
                    'reason' => null,
                ];
            })->all();
        });
    }
}