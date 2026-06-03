<?php

declare(strict_types=1);

namespace App\Schemas\Responses;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

/**
 * Response schema for an exam entity.
 *
 * Used by Scribe to generate the OpenAPI response schema. At runtime
 * the controller still returns JsonResponse via ApiResponse; the Data
 * object documents the shape and can later replace the Eloquent Resource.
 */
class ExamResponseData extends Resource
{
    public function __construct(
        public readonly string $id,

        public readonly string $title,

        public readonly ExamType $type,

        public readonly ExamStatus $status,

        public readonly float $total_marks,

        public readonly ?float $pass_mark,

        public readonly int $duration_minutes,

        public readonly ?int $max_attempts,

        #[Computed]
        public readonly Optional|int $question_count,

        public readonly ?string $scheduled_start,

        public readonly ?string $scheduled_end,

        public readonly ?string $instructions,

        public readonly ?string $created_by,
    ) {}
}
