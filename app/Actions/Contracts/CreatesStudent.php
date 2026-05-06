<?php

namespace App\Actions\Contracts;

use App\Models\Tenant\User;

interface CreatesStudent
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, class_level_id: string, class_arm_id?: string, date_of_birth?: string, gender?: string, admission_number?: string, guardian_email?: string}  $data
     * @return array{user: User, password: string}
     */
    public function execute(array $data): array;
}
