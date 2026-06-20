<?php

namespace App\Data\Exam\Input;


use App\Data\Exam\Input\ExamSettingsData;
use App\Enums\ExamType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateExamData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string|Optional $title,
        
        #[Uuid, Exists('subjects', 'id')]
        public readonly string|Optional $subject_id,
        
        #[Uuid, Exists('class_levels', 'id')]
        public readonly string|Optional $class_level_id,
        
        #[Uuid, Exists('class_arms', 'id')]
        public readonly string|Optional $class_arm_id,
        
        #[Uuid, Exists('terms', 'id')]
        public readonly string|Optional $term_id,
        
        public readonly ExamType|Optional $type,
        
        #[IntegerType, Min(1)]
        public readonly int|Optional $duration_minutes,
        
        #[Numeric, Min(0)]
        public readonly float|Optional $total_marks,
        
        #[Numeric, Min(0)]
        public readonly float|Optional $pass_mark,
        
        #[IntegerType, Min(1)]
        public readonly int|Optional $max_attempts,
        
        #[Date]
        public readonly string|Optional $scheduled_start,
        
        #[StringType]
        public readonly string|Optional $instructions,
        
        public readonly ExamSettingsData|Optional|null $settings,
    ) {}
}