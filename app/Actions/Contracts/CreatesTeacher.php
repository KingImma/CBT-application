<?php

namespace App\Actions\Contracts;

use App\Models\Tenant\User;

interface CreatesTeacher
{
    /**
     * @param  array{first_name: string, last_name: string, email: string, phone?: string, qualification?: string, staff_id?: string, password?: string}  $data
     * @return array{user: User, password: string}
     */
    public function execute(array $data): array;
}
