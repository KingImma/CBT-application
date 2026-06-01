<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Data\Results\ImportResult;
use App\Support\CsvHeaderNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

abstract class CsvImportService
{
    public function import(array $validated, string $filePath, bool $dryRun): ImportResult
    {
        if (! is_readable($filePath)) {
            return new ImportResult(
                success: false,
                message: 'File is not readable.',
                canProceed: false,
            );
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return new ImportResult(
                success: false,
                message: 'Unable to open file.',
                canProceed: false,
            );
        }

        try {
            $headers = $this->readHeaders($handle);
            $missingHeaders = $this->schemaClass()::missingRequiredHeaders($headers);
            if ($missingHeaders !== []) {
                return new ImportResult(
                    success: false,
                    message: 'Missing required columns.',
                    errors: [],
                    missingHeaders: $missingHeaders,
                    canProceed: false,
                );
            }

            $parsed = $this->parseAndValidateRows($handle, $headers);
            if ($parsed['errors'] !== []) {
                return new ImportResult(
                    success: false,
                    message: 'Row validation failed.',
                    errors: $parsed['errors'],
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            $rows = $parsed['rows'];
            $errors = [];
            $rows = $this->findDuplicates($rows, $errors);

            if ($errors !== []) {
                return new ImportResult(
                    success: false,
                    message: 'Duplicate entries found.',
                    errors: $errors,
                    duplicates: $rows,
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            $duplicateByRow = $this->buildDuplicateIndex($rows);

            if ($duplicateByRow !== [] && ($validated['overwrite_existing'] ?? null) !== 'update') {
                return new ImportResult(
                    success: false,
                    message: 'Existing records will be skipped. Set overwrite_existing=update to overwrite.',
                    errors: $duplicateByRow,
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            if ($dryRun) {
                return new ImportResult(
                    success: true,
                    message: 'Preview complete. No records were imported.',
                    totalRows: $parsed['totalRows'],
                    duplicates: $duplicateByRow,
                );
            }

            try {
                $result = DB::transaction(function () use ($rows, $duplicateByRow) {
                    return $this->processRows($rows, $duplicateByRow);
                });
            } catch (\Exception $e) {
                return new ImportResult(
                    success: false,
                    message: 'Import failed: '.$e->getMessage(),
                    errors: [],
                    canProceed: false,
                );
            }

            return $result;
        } finally {
            fclose($handle);
        }
    }

    protected function parseAndValidateRows($handle, array $headers): array
    {
        $rows = [];
        $errors = [];

        while (($raw = fgetcsv($handle)) !== false) {
            if ($this->isRowEmpty($raw)) {
                continue;
            }

            $rowNumber = count($rows) + count($errors) + 1;
            $data = $this->normalizeRow(array_combine($headers, $raw));

            $validator = Validator::make($data, $this->schemaClass()::validatorRules());
            if ($validator->fails()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $rows[] = [
                'row' => $rowNumber,
                'data' => $data,
            ];
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'totalRows' => count($rows) + count($errors),
        ];
    }

    protected function buildDuplicateIndex(array $rows): array
    {
        $duplicateByRow = [];
        foreach ($rows as $rn => $row) {
            foreach ($row['_duplicates'] ?? [] as $d) {
                $duplicateByRow[] = [
                    'row' => $rn,
                    'message' => "Existing record found for {$d['key']}: '{$d['value']}'.",
                ];
            }
        }

        return $duplicateByRow;
    }

    protected function isRowEmpty(array $row): bool
    {
        return count(array_filter($row, fn ($v) => $v !== '' && $v !== null)) === 0;
    }

    abstract protected function schemaClass(): string;

    abstract protected function findDuplicates(array $rows, array &$errors): array;

    abstract protected function processRows(array $rows, array $duplicateByRow): ImportResult;

    protected function readHeaders($handle): array
    {
        $raw = fgetcsv($handle);
        if ($raw === false || $raw === null) {
            return [];
        }

        $normalizer = new CsvHeaderNormalizer;

        return array_map(fn ($header) => $normalizer->normalize($header) ?? '', $raw);
    }

    protected function normalizeRow(array $data): array
    {
        $normalizer = new CsvHeaderNormalizer;

        $normalized = [];
        foreach ($data as $key => $value) {
            $normalizedKey = $normalizer->normalize((string) $key) ?? strtolower(trim((string) $key));
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    protected function buildPartsSummary(int $imported, int $skipped, int $updated, int $totalRows): ImportResult
    {
        return new ImportResult(
            success: true,
            message: "Imported {$imported} records."
                .($skipped > 0 ? " {$skipped} skipped." : '')
                .($updated > 0 ? " {$updated} updated." : ''),
            totalRows: $totalRows,
            imported: $imported,
            skipped: $skipped,
            updated: $updated,
        );
    }
}
