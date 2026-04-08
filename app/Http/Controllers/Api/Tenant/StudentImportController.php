<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class StudentImportController extends Controller
{
    private const BATCH_SIZE = 50;

    /**
     * Download a filled CSV template.
     * Frontend links directly to this endpoint.
     */
    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'first_name',
                'last_name',
                'email',
                'registration_number',
                'class_level',          // use exact name e.g. "JSS 1"
                'class_arm',            // use exact name e.g. "A"
                'date_of_birth',        // YYYY-MM-DD
                'gender',               // male, female, other
            ]);

            // Example rows
            fputcsv($handle, ['John', 'Doe', 'john.doe@example.com', 'STU/2025/0001', 'JSS 1', 'A', '2010-03-15', 'male']);
            fputcsv($handle, ['Jane', 'Smith', '', 'STU/2025/0002', 'SS 2', 'B', '2008-07-22', 'female']);

            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Imports students from a CSV file.
     *
     * Processes in batches of 50 to stay within the 30-second target for 500 students.
     * Returns a detailed report — successful imports, failures, and row-level errors.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'           => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5MB max
            'class_level_id' => ['nullable', 'uuid', 'exists:class_levels,id'],    // override CSV class
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (! $handle) {
            return response()->json(['message' => 'Could not read file.'], 422);
        }

        $headers      = array_map('trim', fgetcsv($handle)); // skip header row
        $rows         = [];
        $rowNumber    = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) < 2) continue; // skip empty rows
            $rows[] = ['row' => $rowNumber, 'data' => array_combine($headers, $row)];
        }

        fclose($handle);

        if (empty($rows)) {
            return response()->json(['message' => 'CSV file is empty.'], 422);
        }

        // Preload class levels and arms for fast lookup
        $classLevels = ClassLevel::all()->keyBy('name');
        $classArms   = ClassArm::all()->keyBy(fn ($arm) => $arm->class_level_id . ':' . $arm->name);

        $imported = 0;
        $failed   = 0;
        $errors   = [];

        // Process in batches
        foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
            foreach ($batch as $item) {
                $result = $this->processRow(
                    $item['data'],
                    $item['row'],
                    $classLevels,
                    $classArms,
                    $request->class_level_id
                );

                if ($result['success']) {
                    $imported++;
                } else {
                    $failed++;
                    $errors[] = $result['error'];
                }
            }
        }

        return response()->json([
            'message'        => "Import complete. {$imported} imported, {$failed} failed.",
            'total_rows'     => count($rows),
            'imported'       => $imported,
            'failed'         => $failed,
            'errors'         => $errors,
        ], $failed > 0 ? 207 : 201); // 207 Multi-Status when partial success
    }

    private function processRow(
        array $data,
        int $rowNumber,
        $classLevels,
        $classArms,
        ?string $overrideClassLevelId
    ): array {
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string'],
            'last_name'  => ['required', 'string'],
            'email'      => ['nullable', 'email'],
            'class_level'=> ['required_without:override_class', 'string'],
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'error'   => ['row' => $rowNumber, 'errors' => $validator->errors()->toArray()],
            ];
        }

        // Resolve class level
        $classLevelId = $overrideClassLevelId;

        if (! $classLevelId) {
            $level = $classLevels->get(trim($data['class_level'] ?? ''));
            if (! $level) {
                return [
                    'success' => false,
                    'error'   => ['row' => $rowNumber, 'errors' => ['class_level' => ["Class level '{$data['class_level']}' not found."]]],
                ];
            }
            $classLevelId = $level->id;
        }

        // Resolve class arm
        $classArmId = null;
        if (! empty($data['class_arm'])) {
            $arm        = $classArms->get($classLevelId . ':' . trim($data['class_arm']));
            $classArmId = $arm?->id;
        }

        // Generate registration number
        $regNumber = ! empty($data['registration_number'])
            ? trim($data['registration_number'])
            : 'STU/' . now()->format('Y') . '/' . str_pad((string)(StudentProfile::count() + 1), 4, '0', STR_PAD_LEFT);

        // Skip if reg number already exists
        if (StudentProfile::where('registration_number', $regNumber)->exists()) {
            return [
                'success' => false,
                'error'   => ['row' => $rowNumber, 'errors' => ['registration_number' => ["Registration number '{$regNumber}' already exists."]]],
            ];
        }

        $email = ! empty($data['email']) ? trim($data['email']) : "{$regNumber}@student.local";

        // Skip if email already exists
        if (User::where('email', $email)->exists()) {
            return [
                'success' => false,
                'error'   => ['row' => $rowNumber, 'errors' => ['email' => ["Email '{$email}' already exists."]]],
            ];
        }

        $user = User::create([
            'first_name' => trim($data['first_name']),
            'last_name'  => trim($data['last_name']),
            'email'      => $email,
            'password'   => Hash::make($regNumber), // default password = reg number
            'is_active'  => true,
        ]);

        $user->assignRole('student');

        StudentProfile::create([
            'user_id'             => $user->id,
            'class_level_id'      => $classLevelId,
            'class_arm_id'        => $classArmId,
            'registration_number' => $regNumber,
            'date_of_birth'       => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            'gender'              => ! empty($data['gender']) ? trim(strtolower($data['gender'])) : null,
        ]);

        return ['success' => true];
    }
}