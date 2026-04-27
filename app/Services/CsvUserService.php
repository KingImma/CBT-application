<?php

// • What: CsvUserService — handles CSV import/export for students and teachers
// • Does: Uses League/CSV to read/write tenant-scoped user CSVs with BOM,
//         header mapping, validation, and chunked processing for large files
// • Why: League/CSV chosen over native fgetcsv — handles Windows CRLF, UTF-8 BOM,
//        and encoding issues that are common in school-generated Excel exports.
//        Nigerian school context means files often come from Excel on Windows.
// • Delivers: importStudents(), importTeachers(), exportStudents(), exportTeachers()
//             Returns import result summary (created, skipped, errors) per run
// • Alternative: Native fgetcsv — zero dependencies, but fragile on BOM, CRLF,
//                and semicolon-delimited files. Not worth the edge-case risk here.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Writer;
use League\Csv\Statement;

class CsvUserService
{
    // ─── Expected CSV column headers for import ───────────────────────────────
    private const STUDENT_HEADERS = ['first_name', 'last_name', 'email', 'class', 'admission_number', 'guardian_name', 'guardian_email'];
    private const TEACHER_HEADERS = ['first_name', 'last_name', 'email', 'subject', 'staff_id'];

    // ─── Import ───────────────────────────────────────────────────────────────

    public function importStudents(string $filePath): array
    {
        return $this->importUsers($filePath, 'student', self::STUDENT_HEADERS);
    }

    public function importTeachers(string $filePath): array
    {
        return $this->importUsers($filePath, 'teacher', self::TEACHER_HEADERS);
    }
    /**
     * @param array<int,mixed> $requiredHeaders
     * @return array<string,mixed>
     */
    private function importUsers(string $filePath, string $role, array $requiredHeaders): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0); 
        $csv->setOutputBOM(\League\Csv\ByteSequence::BOM_UTF8);

        $headers = $csv->getHeader();
        $missing = array_diff($requiredHeaders, $headers);

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'CSV missing required columns: ' . implode(', ', $missing)
            );
        }

        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        $records = Statement::create()->process($csv);

        foreach ($records as $index => $record) {
            $rowNum = $index + 2; // +2 because row 1 = headers

            try {
                $email = trim(strtolower($record['email'] ?? ''));

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $results['errors'][] = "Row {$rowNum}: Missing or invalid email.";
                    continue;
                }

                if (User::where('email', $email)->exists()) {
                    $results['skipped']++;
                    continue;
                }

                $defaultPassword = $role === 'student'
                    ? ($record['admission_number'] ?? Str::random(10))
                    : Str::random(10);

                User::create([
                    'first_name'       => trim($record['first_name']),
                    'last_name'        => trim($record['last_name']),
                    'email'            => $email,
                    'role'             => $role,
                    'password'         => Hash::make($defaultPassword),
                    'class'            => $record['class'] ?? null,
                    'admission_number' => $record['admission_number'] ?? null,
                    'must_change_password' => true,  
                ]);

                $results['created']++;

            } catch (\Exception $e) {
                $results['errors'][] = "Row {$rowNum}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    // ─── Export ───────────────────────────────────────────────────────────────

    public function exportStudents(): string
    {
        return $this->exportUsers('student', self::STUDENT_HEADERS);
    }

    public function exportTeachers(): string
    {
        return $this->exportUsers('teacher', self::TEACHER_HEADERS);
    }
    /**
     * @param array<int,mixed> $headers
     */
    private function exportUsers(string $role, array $headers): string
    {
        $csv = Writer::createFromString();
        $csv->setOutputBOM(\League\Csv\ByteSequence::BOM_UTF8);   // Excel-safe UTF-8

        $csv->insertOne($headers);  // Write header row

        // Chunk query to avoid loading entire user table into memory at once
        // Same principle as reading a book chapter by chapter vs all at once
        User::where('role', $role)
            ->select(['first_name', 'last_name', 'email', 'class', 'admission_number'])
            ->chunk(500, function ($users) use ($csv, $role, $headers) {
                foreach ($users as $user) {
                    $row = $role === 'student'
                        ? [
                            $user->first_name,
                            $user->last_name,
                            $user->email,
                            $user->class,
                            $user->admission_number,
                          ]
                        : [
                            $user->first_name,
                            $user->last_name,
                            $user->email,
                            $user->subject ?? '',
                          ];

                    $csv->insertOne($row);
                }
            });

        return $csv->toString();
    }
}