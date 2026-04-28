<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Models\Tenant\User;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Actions\Tenants\Student\GenerateAdmissionNumber;

class CreateStudentAction
{
    public function __construct(private GenerateAdmissionNumber $admissionNumberGenerator)
    {
    }
    
    /**
     * Executes the student creation process.
     */
    public function execute(array $data): array
    {
        // Wrap everything in a transaction so sequence generation and user creation are atomic
        return DB::transaction(function () use ($data) {
            
            $admissionNumber = $data['admission_number'] ?? $this->admissionNumberGenerator->generate();
            $password  = $admissionNumber; 

            // 1. Create the base user (Removed the 'role' column since Spatie handles it)
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($password),
                'role'       => 'student',
                'is_active'  => true,
            ]);

            // 2. Assign role via Spatie
            $user->assignRole('student');

            // 3. Create the student profile
            $user->studentProfile()->create([
                'class_level_id'      => $data['class_level_id'],
                'class_arm_id'        => $data['class_arm_id'] ?? null,
                'admission_number'    => $admissionNumber,
                'date_of_birth'       => $data['date_of_birth'] ?? null,
                'gender'              => $data['gender'] ?? null,
            ]);

            // 4. Update the central index (Safe to do inside this transaction if connections are configured correctly)
            DB::connection(config('tenancy.database.central_connection'))
                ->table('tenant_user_index')
                ->updateOrInsert(
                    ['email' => $data['email'], 'tenant_id' => tenant('id')],
                    ['role' => 'student', 'updated_at' => now(), 'created_at' => now()]
                );

            // Return the USER entity, not the profile
            return [
                'user'     => $user,
                'password' => $password,
            ];
        });
    }
}