<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Student\StudentAction;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Validator;

class StudentImportService
{
    private const BATCH_SIZE = 50;
    
    public function __construct(
        private readonly StudentAction $studentAction,
    ) {}

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
    
        $admissionNumber = $data['admission_number'] ?: $this->studentAction->generateAdmissionNumber();
        $admissionNumber = strtoupper(trim($admissionNumber));
    
        $email = $data['email'] ?: "{$admissionNumber}@student.local";
    
        $existingStudent = $this->findExistingStudent($admissionNumber, $email);
    
        if ($existingStudent) {
            if ($overwriteExisting === 'update') {
                $this->studentAction->update(
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
    
        $this->studentAction->create(
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

    private function readHeaders($handle): array
    {
        $headers = fgetcsv($handle);
        if ($headers === false || $headers === null) {
            return [];
        }

        return array_map(fn ($h) => strtolower(trim($h)), $headers);
    }

    private function normalizeRow(array $data): array
    {
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
    }

    private function resolveClassLevelId(?string $name, $classLevels, int $rowNumber): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $level = $classLevels->get(strtolower(trim($name)));

        return $level?->id;
    }

    private function resolveClassArmId(string $classLevelId, ?string $name, $classArms): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $key = $classLevelId . ':' . strtolower(trim($name));

        return $classArms->get($key)?->id;
    }

    private function findExistingStudent(string $admissionNumber, string $email): ?User
    {
        return User::role('student')
            ->where(function ($query) use ($admissionNumber, $email) {
                $query->where('email', $email)
                    ->orWhereHas('studentProfile', fn ($p) => $p->where('admission_number', $admissionNumber));
            })
            ->first();
    }

    private function notFoundError(int $rowNumber, string $field, ?string $value): array
    {
        return [
            'status' => 'error',
            'error' => [
                'row' => $rowNumber,
                'errors' => [$field => ["{$field} '{$value}' not found."]],
            ],
        ];
    }

    private function buildPayload(array $data, ?string $classLevelId, ?string $classArmId, string $admissionNumber, string $email): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'admission_number' => $admissionNumber,
            'class_level_id' => $classLevelId,
            'class_arm_id' => $classArmId,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'guardian_email' => $data['guardian_email'] ?? null,
        ];
    }
}