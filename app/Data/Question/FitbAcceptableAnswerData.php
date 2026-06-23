<?php

declare(strict_types=1);

namespace App\Data\Question;

use Spatie\LaravelData\Resource;

class FitbAcceptableAnswerData extends Resource
{
    public function __construct(
        public readonly string $content,
        public readonly bool $case_sensitive,
    ) {}
}
