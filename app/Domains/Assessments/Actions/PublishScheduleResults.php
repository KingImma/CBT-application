<?php

// app/Domains/Assessments/Actions/PublishScheduleResults.php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Exams\Actions\PublishExamResults;
use App\Models\Tenant\AssessmentSchedule;
use Illuminate\Support\Facades\DB;

final class PublishScheduleResults
{
    public function __construct(
        private PublishExamResults $publishExam,
    ) {}

    /**
     * Publish results for every materialised exam under this schedule and
     * flip the schedule itself to `published`. The all-completed gate runs
     * inside publish(); a schedule that is already published is left
     * untouched so the caller can answer with an informational message.
     *
     * @return array{published: bool, results: array<int, array{exam_id: string, subject_id: ?string, status: string, reason: ?string}>}
     */
    public function execute(AssessmentSchedule $schedule): array
    {
        return DB::transaction(function () use ($schedule): array {
            if ($schedule->isPublished()) {
                return ['published' => false, 'results' => []];
            }

            $schedule->publish();

            $submissions = $schedule->submissions()
                ->whereNotNull('exam_id')
                ->with('exam')
                ->get();

            $results = $submissions->map(function ($submission): array {
                $exam = $submission->exam;

                if ($exam->isPublished()) {
                    return [
                        'exam_id' => $exam->id,
                        'subject_id' => $submission->subject_id,
                        'status' => 'skipped',
                        'reason' => 'Already published.',
                    ];
                }

                $this->publishExam->execute($exam);

                return [
                    'exam_id' => $exam->id,
                    'subject_id' => $submission->subject_id,
                    'status' => 'published',
                    'reason' => null,
                ];
            })->all();

            return ['published' => true, 'results' => $results];
        });
    }
}
