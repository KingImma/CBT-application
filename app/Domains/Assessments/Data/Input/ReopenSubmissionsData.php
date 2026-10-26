<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use App\Shared\Casts\ToUtc;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Reopening a closed question window: the new deadline is normalised to UTC
 * before ReopenSubmissions compares it with now() and stores it.
 */
class ReopenSubmissionsData extends Data
{
    public function __construct(
        #[Required, Date, WithCast(ToUtc::class)]
        public readonly string $question_submission_ends,
    ) {
    }
}