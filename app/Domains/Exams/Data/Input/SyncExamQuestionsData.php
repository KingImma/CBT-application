<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Input;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SyncExamQuestionsData extends Data
{
    public function __construct(
        #[DataCollectionOf(SyncExamQuestionItemData::class)]
        public readonly DataCollection $questions,
    ) {}
}
