<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ExamSettingsData extends Data
{
    public function __construct(
        #[Nullable, BooleanType]
        public Optional|bool $randomize_questions,
        #[Nullable, BooleanType]
        public Optional|bool $show_result_immediately,
        #[Nullable, Date]
        public Optional|string|null $results_release_date,
        #[Nullable, BooleanType]
        public Optional|bool $require_attendance,
        #[Nullable, IntegerType, Min(0)]
        public Optional|int $max_suspicious_events,
    ) {}

    public function getRandomizeQuestions(): Optional|bool
    {
        return $this->randomize_questions;
    }

    public function getShowResultImmediately(): Optional|bool
    {
        return $this->show_result_immediately;
    }

    public function getResultsReleaseDate(): Optional|string|null
    {
        return $this->results_release_date;
    }

    public function getRequireAttendance(): Optional|bool
    {
        return $this->require_attendance;
    }

    public function getMaxSuspiciousEvents(): Optional|int
    {
        return $this->max_suspicious_events;
    }
}
