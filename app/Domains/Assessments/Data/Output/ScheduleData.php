<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Output;

use App\Enums\AssessmentStatus;
use App\Enums\QuestionSubmissionStatus;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class ScheduleData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $assessment_id,
        public readonly string $academic_session_id,
        public readonly string $term_id,
        public readonly string $class_level_id,
        public readonly ?string $class_arm_id,
        public readonly string $question_submission_ends,
        public readonly ?string $assessment_starts,
        public readonly ?string $assessment_ends,
        public readonly QuestionSubmissionStatus $question_submission_status,
        public readonly AssessmentStatus $assessment_status,
        public readonly ?string $activated_at,

        #[WhenLoaded('classLevel')]
        public readonly mixed $classLevel,

        #[WhenLoaded('classArm')]
        public readonly mixed $classArm,

        #[WhenLoaded('term')]
        public readonly mixed $term,

        #[WhenLoaded('academicSession')]
        public readonly mixed $academicSession,

        #[WhenLoaded('scheduleSubjects')]
        public readonly mixed $scheduleSubjects,

        #[WhenLoaded('submissions')]
        public readonly mixed $submissions,
    ) {}
}
