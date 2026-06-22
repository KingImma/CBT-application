<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;
// TODO:

class ExamData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ExamType $type,
        public readonly ExamStatus $status,
        public readonly ?float $total_marks,
        public readonly ?float $pass_mark,
        public readonly int $duration_minutes,
        public readonly ?int $max_attempts,
        public readonly int $expected_attempts,
        public readonly int $completed_attempts,
        public readonly ?string $scheduled_start,
        public readonly ?string $instructions,
        public readonly ?string $published_at,
        public readonly bool $is_published,
        
        #[Computed] 
        public readonly Optional|int $question_count,
        
        #[WhenLoaded('subject')] 
        public readonly mixed $subject,
        
        #[WhenLoaded('classLevel')] 
        public readonly mixed $classLevel,
        
        #[WhenLoaded('classArm')] 
        public readonly mixed $classArm,
        
        #[WhenLoaded('term')] 
        public readonly mixed $term,
        
        #[WhenLoaded('creator')] 
        public readonly mixed $creator,
    ) {}
}