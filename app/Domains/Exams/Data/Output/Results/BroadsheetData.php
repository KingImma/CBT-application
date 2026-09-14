<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class BroadsheetData extends Data
{
    public function __construct(
        public readonly BroadsheetMetaData $meta,
        /** @var DataCollection<int, BroadsheetSubjectData> */
        public readonly DataCollection $subjects,
        /** @var DataCollection<int, BroadsheetStudentData> */
        public readonly DataCollection $students,
    ) {
    }
}
