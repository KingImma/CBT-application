<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Data\Results\ImportResult;
use App\Support\CsvHeaderNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

abstract class CsvImport
{
    public function __construct(
        private CsvHeaderNormalizer $headerNormalizer
    ) {}

    public function execute(array $validated, string $filePath, bool $dryRun): ImportResult
    {
        $handle = $this->openFile($filePath);
        if ($handle === null) {
            return new ImportResult(
                success: false,
                message: $this->lastFileErrorMessage,
                canProceed: false,
            );
        }

        try {
            $headerResult = $this->validateHeaders($handle);
            if ($headerResult === null) {
                return $this->missingHeadersResult();
            }
            $headers = $headerResult;

            $parsed = $this->parseAndValidateRows($handle, $headers);
            if ($parsed['errors'] !== []) {
                return $this->validationFailedResult($parsed);
            }

            $rows = $parsed['rows'];
            $duplicateResult = $this->checkDuplicates($rows, $parsed['totalRows'], $validated);
            if ($duplicateResult !== null) {
                return $duplicateResult;
            }
            $duplicateByRow = $duplicateResult ?? [];

            if ($dryRun) {
                return new ImportResult(
                    success: true,
                    message: 'Preview complete. No records were imported.',
                    totalRows: $parsed['totalRows'],
                    duplicates: $duplicateByRow,
                );
            }

            return $this->processImportTransaction($rows, $duplicateByRow);
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
        return collect($row)->reject(fn ($v) => blank($v))->isEmpty();
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

        return array_map(fn ($header) => $this->headerNormalizer->normalize($header) ?? '', $raw);
    }

    protected function normalizeRow(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalizedKey = $this->headerNormalizer->normalize((string) $key) ?? strtolower(trim((string) $key));
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

    private ?string $lastFileErrorMessage = null;

    private array $lastMissingHeaders = [];

    private function openFile(string $filePath): mixed
    {
        if (! is_readable($filePath)) {
            $this->lastFileErrorMessage = 'File is not readable.';

            return null;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->lastFileErrorMessage = 'Unable to open file.';

            return null;
        }

        return $handle;
    }

    private function validateHeaders($handle): ?array
    {
        $headers = $this->readHeaders($handle);
        $this->lastMissingHeaders = $this->schemaClass()::missingRequiredHeaders($headers);

        if ($this->lastMissingHeaders !== []) {
            return null;
        }

        return $headers;
    }

    private function missingHeadersResult(): ImportResult
    {
        return new ImportResult(
            success: false,
            message: 'Missing required columns.',
            errors: [],
            missingHeaders: $this->lastMissingHeaders,
            canProceed: false,
        );
    }

    private function validationFailedResult(array $parsed): ImportResult
    {
        return new ImportResult(
            success: false,
            message: 'Row validation failed.',
            errors: $parsed['errors'],
            totalRows: $parsed['totalRows'],
            canProceed: false,
        );
    }

    private function checkDuplicates(array &$rows, int $totalRows, array $validated): ?ImportResult
    {
        $errors = [];
        $rows = $this->findDuplicates($rows, $errors);

        if ($errors !== []) {
            return new ImportResult(
                success: false,
                message: 'Duplicate entries found.',
                errors: $errors,
                duplicates: $rows,
                totalRows: $totalRows,
                canProceed: false,
            );
        }

        $duplicateByRow = $this->buildDuplicateIndex($rows);

        $hasDuplicatesWithoutOverwrite = $duplicateByRow !== [] && ($validated['overwrite_existing'] ?? null) !== 'update';

        if ($hasDuplicatesWithoutOverwrite) {
            return new ImportResult(
                success: false,
                message: 'Existing records will be skipped. Set overwrite_existing=update to overwrite.',
                errors: $duplicateByRow,
                totalRows: $totalRows,
                canProceed: false,
            );
        }

        return null;
    }

    private function processImportTransaction(array $rows, array $duplicateByRow): ImportResult
    {
        try {
            return DB::transaction(function () use ($rows, $duplicateByRow) {
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
    }
}
