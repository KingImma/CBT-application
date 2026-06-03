<?php

declare(strict_types=1);

namespace App\Data\Teacher;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
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
        public readonly Optional $teacherProfile,
        #[WhenLoaded('assignedClasses')]
        public readonly Optional $assignedClasses,
        #[WhenLoaded('teacherAssignments')]
        public readonly Optional $teacherAssignments,
    ) {}
}
