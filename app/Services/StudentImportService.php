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
    
    public function __construct(
        private readonly GenerateAdmissionNumber $admissionNumberGenerator,
        private readonly CreateStudentAction $createStudentAction,
        private readonly UpdateStudentAction $updateStudentAction,)
    {
    }

    public function import(array $validated, string $filePath): array
    {
        if (! is_readable($filePath)) {
            return ['error' => 'Could not read file.'];
        }
    
        $classLevels = ClassLevel::query()->get()->keyBy(fn ($level) => strtolower(trim($level->name)));
        $classArms = ClassArm::query()->get()->keyBy(
            fn ($arm) => $arm->class_level_id . ':' . strtolower(trim($arm->name))
        );
    
        $stats = [
            'total_rows' => 0,
            'imported' => 0,
            'duplicates_found' => 0,
            'failed' => 0,
            'duplicates' => [],
            'errors' => [],
        ];
    
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return ['error' => 'Could not open file.'];
        }
    
        try {
            $headers = $this->readHeaders($handle);
            if ($headers === []) {
                return ['error' => 'CSV file is empty or missing headers.'];
            }
    
            $rowNumber = 1;
    
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $stats['total_rows']++;
    
                if (count($row) !== count($headers)) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'row' => $rowNumber,
                        'errors' => ['csv' => ['Column count does not match header count.']],
                    ];
                    continue;
                }
    
                $data = array_combine($headers, $row);
    
                if (! is_array($data)) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'row' => $rowNumber,
                        'errors' => ['csv' => ['Could not parse row.']],
                    ];
                    continue;
                }
    
                $result = $this->processRow(
                    $this->normalizeRow($data),
                    $rowNumber,
                    $classLevels,
                    $classArms,
                    $validated['class_level_id'] ?? null,
                    $validated['overwrite_existing'] ?? 'skip'
                );
    
                match ($result['status']) {
                    'imported' => $stats['imported']++,
                    'duplicate' => [
                        $stats['duplicates_found']++,
                        $stats['duplicates'][] = $result['data'],
                    ],
                    default => [
                        $stats['failed']++,
                        $stats['errors'][] = $result['error'],
                    ],
                };
            }
        } finally {
            fclose($handle);
        }
    
        return $stats;
    }

    private function processRow(
        array $data,
        int $rowNumber,
        $classLevels,
        $classArms,
        ?int $overrideClassLevelId,
        string $overwriteExisting
    ): array {
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'guardian_email' => ['nullable', 'email'],
            'class_level' => [$overrideClassLevelId ? 'nullable' : 'required', 'string'],
            'class_arm' => ['nullable', 'string'],
            'admission_number' => ['nullable', 'string'],
        ]);
    
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'error' => [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->toArray(),
                ],
            ];
        }
    
        $classLevelId = $overrideClassLevelId ?? $this->resolveClassLevelId($data['class_level'] ?? null, $classLevels, $rowNumber);
        if (! $classLevelId) {
            return $this->notFoundError($rowNumber, 'class_level', $data['class_level'] ?? null);
        }
    
        $classArmId = $this->resolveClassArmId($classLevelId, $data['class_arm'] ?? null, $classArms);
    
        $admissionNumber = $data['admission_number'] ?: $this->admissionNumberGenerator->generate();
        $admissionNumber = strtoupper(trim($admissionNumber));
    
        $email = $data['email'] ?: "{$admissionNumber}@student.local";
    
        $existingStudent = $this->findExistingStudent($admissionNumber, $email);
    
        if ($existingStudent) {
            if ($overwriteExisting === 'update') {
                $this->updateStudentAction->execute(
                    $this->buildPayload($data, $classLevelId, $classArmId, $admissionNumber, $email),
                    $existingStudent->id
                );
    
                return [
                    'status' => 'imported',
                    'data' => [
                        'row' => $rowNumber,
                        'action' => 'updated',
                        'user_id' => $existingStudent->id,
                    ],
                ];
            }
    
            return [
                'status' => 'duplicate',
                'data' => [
                    'row' => $rowNumber,
                    'action' => 'skipped',
                    'existing' => [
                        'first_name' => $existingStudent->first_name,
                        'last_name' => $existingStudent->last_name,
                        'email' => $existingStudent->email,
                        'admission_number' => $existingStudent->studentProfile?->admission_number,
                    ],
                ],
            ];
        }
    
        $this->createStudentAction->execute(
            $this->buildPayload($data, $classLevelId, $classArmId, $admissionNumber, $email)
        );
    
        return [
            'status' => 'imported',
            'data' => [
                'row' => $rowNumber,
                'action' => 'created',
            ],
        ];
    }
}