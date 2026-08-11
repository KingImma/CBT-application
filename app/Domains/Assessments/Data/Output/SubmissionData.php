<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Output;

use App\Enums\SubmissionStatus;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class SubmissionData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $assessment_id,
        public readonly string $teacher_id,
        public readonly string $subject_id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly SubmissionStatus $status,
        public readonly ?float $total_marks,
        public readonly ?string $submitted_at,
        public readonly ?string $returned_at,
        public readonly ?string $approved_at,
        public readonly ?string $exam_id,

        #[Computed]
        public readonly Optional|int $question_count,

        #[WhenLoaded('subject')]
        public readonly mixed $subject,

        #[WhenLoaded('teacher')]
        public readonly mixed $teacher,

        #[WhenLoaded('assessment')]
        public readonly mixed $assessment,

        #[WhenLoaded('submissionQuestions')]
        public readonly mixed $questions,
    ) {}
}
