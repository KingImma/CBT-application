<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data;

use Spatie\LaravelData\Data;

class MaterializeExamRequest extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly string $subjectId,
        public readonly string $classLevelId,
        public readonly ?string $classArmId,
        public readonly string $termId,
        public readonly string $createdBy,
        public readonly int $durationMinutes,
        public readonly float $totalMarks,
        public readonly ?string $scheduledStart,
        public readonly ?string $windowEnd,
        public readonly ?string $instructions,
        /** @var MaterializeExamQuestionRequest[] */
        public readonly array $questions,
    ) {}
}