<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output\Results;

use Spatie\LaravelData\Data;

final class BroadsheetMetaData extends Data
{
    public function __construct(
        public readonly string $class_level_id,
        public readonly string $class_level_name,
        public readonly ?string $class_arm_id,
        public readonly ?string $class_arm_name,
        public readonly string $term_id,
        public readonly string $term_name,
        public readonly string $academic_session_id,
        public readonly string $academic_session_name,
    ) {
    }
}
