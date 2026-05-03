<?php

namespace App\Actions\Contracts;

use App\Models\Tenant\User;

interface UpdatesTeacher
{
    /**
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string, qualification?: string, staff_id?: string}  $data
     */
    public function execute(array $data, string $userId): User;
}
