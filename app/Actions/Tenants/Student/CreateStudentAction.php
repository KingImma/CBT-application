<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Models\Tenant\User;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateStudentAction
{
    /**
     * Executes the student creation process.
     *
     * @param array $data Validated student data
     * @return array
     */
    public function execute(array $data): array
    {
        $regNumber = $data['registration_number'] ?? $this->generateRegNumber();
        $password  = $regNumber; 

        // Wrap tenant DB operations in a transaction so if the profile fails, 
        // the user isn't left stranded in the database.
        $profile = DB::transaction(function () use ($data, $regNumber, $password) {
            
            // 1. Create the base user
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($password),
                'is_active'  => true,
            ]);

            // 2. Assign role
            $user->assignRole('student');

            // 3. Create the student profile
            return StudentProfile::create([
                'user_id'             => $user->id,
                'class_level_id'      => $data['class_level_id'],
                'class_arm_id'        => $data['class_arm_id'] ?? null,
                'registration_number' => $regNumber,
                'date_of_birth'       => $data['date_of_birth'] ?? null,
                'gender'              => $data['gender'] ?? null,
            ]);
        });

        // 4. Update the central index (outside the tenant transaction)
        // Note: Fixed the undefined $email variable from your original code
        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->updateOrInsert(
                ['email' => $data['email'], 'tenant_id' => tenant('id')],
                ['role' => 'student', 'updated_at' => now(), 'created_at' => now()]
            );

        // 5. Load relationships to prep for the API response
        $profile->load(['user', 'classLevel', 'classArm']);

        return [
            'profile'  => $profile,
            'password' => $password,
        ];
    }

    /**
     * Generate a unique registration number.
     */
    private function generateRegNumber(): string
    {
        // Add your specific generation logic here. Example fallback:
        return 'STU-' . strtoupper(Str::random(6));
    }
}