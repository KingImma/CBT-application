<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Student\CreateStudentAction;
use App\Actions\Tenants\Student\UpdateStudentAction;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use App\Actions\Tenants\Student\GenerateAdmissionNumber;
use Illuminate\Support\Facades\Validator;

class StudentImportService
{
    private const BATCH_SIZE = 50;
    
    public function __construct(private readonly GenerateAdmissionNumber $admissionNumberGenerator)
    {
    }

    public function import(array $validated, ?string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ['error' => 'Could not read file.'];
        }

        $headers = array_map('trim', fgetcsv($handle));
        $rows = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) < 2) {
                continue;
            }
            $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $row)];
        }

        fclose($handle);

        if (empty($rows)) {
            return ['error' => 'CSV file is empty.'];
        }

        $classLevels = ClassLevel::all()->keyBy('name');
        $classArms = ClassArm::all()->keyBy(fn ($arm) => $arm->class_level_id . ':' . $arm->name);

        $overwriteExisting = $validated['overwrite_existing'] ?? 'skip';
        $overrideClassLevelId = $validated['class_level_id'] ?? null;

        $imported = 0;
        $duplicatesFound = 0;
        $failed = 0;
        $duplicates = [];
        $errors = [];

        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $item) {
                $result = $this->processRow(
                    $item['data'],
                    $item['row'],
                    $classLevels,
                    $classArms,
                    $overrideClassLevelId,
                    $overwriteExisting
                );

                if ($result['status'] === 'imported') {
                    $imported++;
                } elseif ($result['status'] === 'duplicate') {
                    $duplicatesFound++;
                    $duplicates[] = $result['data'];
                } else {
                    $failed++;
                    $errors[] = $result['error'];
                }
            }
        }

        return [
            'total_rows'        => count($rows),
            'imported'         => $imported,
            'duplicates_found'  => $duplicatesFound,
            'failed'          => $failed,
            'duplicates'      => $duplicates,
            'errors'          => $errors,
        ];
    }

    private function processRow(
        array $data,
        int $rowNumber,
        $classLevels,
        $classArms,
        ?string $overrideClassLevelId,
        string $overwriteExisting
    ): array {
        $validator = Validator::make($data, [
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'email'       => ['nullable', 'email'],
            'class_level' => ['required_without:override_class', 'string'],
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'error' => ['row' => $rowNumber, 'errors' => $validator->errors()->toArray()],
            ];
        }

        $classLevelId = $overrideClassLevelId;

        if (! $classLevelId) {
            $level = $classLevels->get(trim($data['class_level'] ?? ''));
            if (! $level) {
                return [
                    'status' => 'error',
                    'error' => ['row' => $rowNumber, 'errors' => ['class_level' => ["Class level '{$data['class_level']}' not found."]]],
                ];
            }
            $classLevelId = $level->id;
        }

        $classArmId = null;
        if (! empty($data['class_arm'])) {
            $arm = $classArms->get($classLevelId . ':' . trim($data['class_arm']));
            $classArmId = $arm?->id;
        }

        $admissionNumber = ! empty($data['admission_number'])
            ? trim($data['admission_number'])
            : null;

        $email = ! empty($data['email']) ? trim($data['email']) : null;

        $existingByAdmission = $admissionNumber ? StudentProfile::where('admission_number', $admissionNumber)->first() : null;
        $existingByEmail = $email ? User::where('email', $email)->first() : null;

        $existingProfile = $existingByAdmission?->user;
        if (! $existingProfile && $existingByEmail) {
            $existingProfile = $existingByEmail;
        }

        if ($existingProfile && $existingProfile->studentProfile) {
            if ($overwriteExisting === 'update') {
                $updateData = [
                    'first_name'     => trim($data['first_name']),
                    'last_name'    => trim($data['last_name']),
                    'email'        => $email ?? "{$admissionNumber}@student.local",
                    'class_level_id' => $classLevelId,
                    'class_arm_id'   => $classArmId,
                    'date_of_birth'  => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'gender'         => ! empty($data['gender']) ? trim(strtolower($data['gender'])) : null,
                ];

                app(UpdateStudentAction::class)->execute($updateData, $existingProfile->id);

                return [
                    'status' => 'imported',
                    'data'  => ['row' => $rowNumber, 'action' => 'updated', 'user_id' => $existingProfile->id],
                ];
            }

            return [
                'status' => 'duplicate',
                'data'  => [
                    'row'      => $rowNumber,
                    'action'  => 'skipped',
                    'existing' => [
                        'first_name'         => $existingProfile->first_name,
                        'last_name'        => $existingProfile->last_name,
                        'email'           => $existingProfile->email,
                        'admission_number' => $existingProfile->studentProfile->admission_number,
                        'class_level'           => $existingProfile->studentProfile->classLevel?->name,
                        'class_arm'             => $existingProfile->studentProfile->classArm?->name,
                    ],
                    'imported' => [
                        'first_name'        => trim($data['first_name']),
                        'last_name'       => trim($data['last_name']),
                        'email'          => $email ?? "{$admissionNumber}@student.local",
                        'admission_number' => $admissionNumber,
                        'class_level'          => $data['class_level'],
                        'class_arm'            => $data['class_arm'] ?? null,
                    ],
                ],
            ];
        }

        if (! $admissionNumber) {
            $admissionNumber = $this->admissionNumberGenerator->generate();
        }

        $finalEmail = $email ?? "{$admissionNumber}@student.local";

        $createData = [
            'first_name'    => trim($data['first_name']),
            'last_name'   => trim($data['last_name']),
            'email'       => $finalEmail,
            'class_level_id' => $classLevelId,
            'class_arm_id'  => $classArmId,
            'admission_number' => $admissionNumber,
            'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'gender'        => ! empty($data['gender']) ? trim(strtolower($data['gender'])) : null,
        ];

        app(CreateStudentAction::class)->execute($createData);

        return [
            'status' => 'imported',
            'data'  => ['row' => $rowNumber, 'action' => 'created'],
        ];
    }
}