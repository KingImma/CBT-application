<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Actions\Contracts\CreatesStudent;
use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateStudentAction implements CreatesStudent
{
    public function __construct(private GenerateAdmissionNumber $admissionNumberGenerator) {}

    /**
     * Executes the student creation process.
     */
    public function execute(array $data): array
    {
        // Wrap everything in a transaction so sequence generation and user creation are atomic
        return DB::transaction(function () use ($data) {

            $admissionNumber = $data['admission_number'] ?? $this->admissionNumberGenerator->generate();
            $password = $admissionNumber;

            // 1. Create the base user (Removed the 'role' column since Spatie handles it)
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'role' => 'student',
                'is_active' => true,
            ]);

            // 2. Assign role via Spatie
            $user->assignRole('student');

            // 3. Create the student profile
            $user->studentProfile()->create([
                'class_level_id' => $data['class_level_id'],
                'class_arm_id' => $data['class_arm_id'] ?? null,
                'admission_number' => $admissionNumber,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'guardian_email' => $data['guardian_email'] ?? null,
            ]);

            // 4. Update the central index (Safe to do inside this transaction if connections are configured correctly)
            app(TenantUserService::class)->updateCentralIndex($data['email'], 'student');

            // Return the USER entity, not the profile
            return [
                'user' => $user,
                'password' => $password,
            ];
        });
    }
}
