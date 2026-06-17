<?php

declare(strict_types=1);

namespace App\Data\Exam;

use App\Enums\ExamType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class CreateExamData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string $title,
        #[Uuid, Exists('subjects', 'id')]
        public readonly string $subject_id,
        #[Uuid, Exists('class_levels', 'id')]
        public readonly string $class_level_id,
        #[Nullable, Uuid, Exists('class_arms', 'id')]
        public readonly ?string $class_arm_id,
        #[Uuid, Exists('terms', 'id')]
        public readonly string $term_id,
        public readonly ExamType $type,
        #[IntegerType, Min(1)]
        public readonly int $duration_minutes,
        #[Nullable, Numeric, Min(0)]
        public readonly ?float $total_marks,
        #[Nullable, Numeric, Min(0)]
        public readonly ?float $pass_mark,
        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $max_attempts,
        #[Nullable, Date]
        public readonly ?string $scheduled_start,
        #[Nullable, StringType]
        public readonly ?string $instructions,
        #[Nullable]
        public readonly ?ExamSettingsData $settings,
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubjectId(): string
    {
        return $this->subject_id;
    }

    public function getClassLevelId(): string
    {
        return $this->class_level_id;
    }

    public function getClassArmId(): ?string
    {
        return $this->class_arm_id;
    }

    public function getTermId(): string
    {
        return $this->term_id;
    }

    public function getType(): ExamType
    {
        return $this->type;
    }

    public function getDurationMinutes(): int
    {
        return $this->duration_minutes;
    }

    public function getPassMark(): ?float
    {
        return $this->pass_mark;
    }

    public function getMaxAttempts(): ?int
    {
        return $this->max_attempts;
    }

    public function getScheduledStart(): ?string
    {
        return $this->scheduled_start;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function getSettings(): ?ExamSettingsData
    {
        return $this->settings;
    }
}
