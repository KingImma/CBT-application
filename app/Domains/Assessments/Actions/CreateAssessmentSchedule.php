<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\CreateScheduleData;
use App\Domains\Assessments\Events\AssessmentOpened;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAssessmentSchedule
{
    public function __construct() {}

    /**
     * Schedule a new occurrence of an assessment in the current academic
     * term. Session + term are resolved server-side (single source of truth);
     * creating the schedule immediately OPENS the question-submission window.
     */
    public function execute(Assessment $assessment, CreateScheduleData $dto): AssessmentSchedule
    {
        return DB::transaction(function () use ($assessment, $dto): AssessmentSchedule {
            $term = Term::where('is_current', true)->first();

            throw_unless(
                $term !== null,
                ValidationException::withMessages([
                    'term_id' => ['No current academic term is configured for this school.'],
                ])
            );

            $ends = Carbon::parse($dto->question_submission_ends);

            throw_unless(
                $ends->isFuture(),
                ValidationException::withMessages([
                    'question_submission_ends' => ['The question submission deadline must be in the future.'],
                ])
            );

            $starts = isset($dto->assessment_starts) ? Carbon::parse($dto->assessment_starts) : null;
            $windowEnds = isset($dto->assessment_ends) ? Carbon::parse($dto->assessment_ends) : null;

            throw_if(
                $starts !== null && $windowEnds !== null && $starts->gte($windowEnds),
                ValidationException::withMessages([
                    'assessment_ends' => ['The student end time must be after the student start time.'],
                ])
            );

            $schedule = AssessmentSchedule::create([
                'assessment_id' => $assessment->id,
                'academic_session_id' => $term->academic_session_id,
                'term_id' => $term->id,
                'question_submission_ends' => $ends,
                'assessment_starts' => $starts,
                'assessment_ends' => $windowEnds,
            ]);

            event(new AssessmentOpened($schedule));

            return $schedule->fresh();
        });
    }
}
