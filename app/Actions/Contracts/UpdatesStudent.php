<?php

namespace App\Actions\Contracts;

use App\Models\Tenant\User;

interface UpdatesStudent
{
    /**
     * @param  array{first_name?: string, last_name?: string, email?: string, class_level_id?: string, class_arm_id?: string, date_of_birth?: string, gender?: string}  $data
     */
    public function execute(array $data, string $userId): User;
}
