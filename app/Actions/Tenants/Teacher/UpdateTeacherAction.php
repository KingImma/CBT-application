<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Actions\Contracts\UpdatesTeacher;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class UpdateTeacherAction implements UpdatesTeacher
{
    /**
     * Executes the teacher update process.
     */
    public function execute(array $data, string $userId): User
    {
        $user = User::role('teacher')->findOrFail($userId);

        DB::transaction(function () use ($user, $data) {
            $userData = collect($data)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();
            if (! empty($userData)) {
                $user->update($userData);
            }

            $profileData = collect($data)->only(['qualification', 'staff_id'])->toArray();
            if (! empty($profileData)) {
                $user->teacherProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }
        });

        return $user->fresh('teacherProfile');
    }
}
