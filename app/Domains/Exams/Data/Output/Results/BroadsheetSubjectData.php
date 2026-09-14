<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;

final class BroadsheetSubjectData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly float $class_average,
    ) {
    }
}
