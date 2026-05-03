<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Actions\Contracts\UpdatesStudent;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class UpdateStudentAction implements UpdatesStudent
{
    /**
     * Executes the student update process.
     */
    public function execute(array $data, string $userId): User
    {
        $user = User::role('student')->findOrFail($userId);

        DB::transaction(function () use ($user, $data) {
            
            // 1. Extract and update the User table fields
            $userData = collect($data)->only(['first_name', 'last_name', 'email'])->toArray();
            if (!empty($userData)) {
                $user->update($userData);
            }

            // 2. Extract and update the Student Profile (Backpack) fields
            $profileData = collect($data)->only([
                'class_level_id', 
                'class_arm_id', 
                'admission_number', 
                'date_of_birth', 
                'gender'
            ])->toArray();
            
            if (!empty($profileData)) {
                // updateOrCreate ensures we don't crash if the profile row was accidentally deleted
                $user->studentProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }
        });

        
        return ['user' => $user->fresh()];
    }
}