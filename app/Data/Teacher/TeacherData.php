<?php

declare(strict_types=1);

namespace App\Data\Teacher;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class TeacherData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly bool $is_active,
        #[WhenLoaded('teacherProfile')]
        public readonly mixed $teacherProfile,
        #[WhenLoaded('assignedClasses')]
        public readonly mixed $assignedClasses,
        #[WhenLoaded('teacherAssignments')]
        public readonly mixed $teacherAssignments,
    ) {}
}
