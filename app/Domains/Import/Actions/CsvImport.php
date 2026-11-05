<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Support\ImportProgress;
use App\Shared\Support\CsvHeaderNormalizer;
use Illuminate\Support\Facades\Validator;
use Throwable;

abstract class CsvImport
{
    /**
     * Set by the queue job so progress can be reported to the client.
     */
    private ?ImportProgress $progress = null;

    private string $progressType = 'import';

    public function __construct()
    {
    }

    /**
     * Report progress for a specific import job (used by the queue jobs).
     */
    public function forJob(?string $importJobId, string $type = 'import'): static
    {
        $this->progress = ImportProgress::make($importJobId);
        $this->progressType = $type;

        return $this;
    }

    protected function progress(): ?ImportProgress
    {
        return $this->progress;
    }

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

            $limit = (int) config('imports.max_rows', 1000);

            if ($limit > 0 && $parsed['totalRows'] > $limit) {
                return new ImportResult(
                    success: false,
                    message: "This file has {$parsed['totalRows']} rows. The maximum for a single import is {$limit} rows.",
                    errors: [],
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            $referenceErrors = [];
            $rows = $this->resolveReferences($rows, $referenceErrors);
            if ($referenceErrors !== []) {
                return new ImportResult(
                    success: false,
                    message: 'Invalid references in CSV data.',
                    errors: $referenceErrors,
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            $parsed['totalRows'] = count($rows) + count($parsed['errors']);
            $duplicateResult = $this->checkDuplicates($rows, $parsed['totalRows'], $validated, $dryRun);
            if ($duplicateResult !== null) {
                return $duplicateResult;
            }

            $duplicateByRow = $this->buildDuplicateIndex($rows);

            $this->progress?->start($this->progressType, $parsed['totalRows']);

            if ($dryRun) {
                return new ImportResult(
                    success: true,
                    message: 'Preview complete. No records were imported.',
                    totalRows: $parsed['totalRows'],
                    duplicates: $duplicateByRow,
                );
            }

            try {
                $this->progress?->phase('writing');

                return $this->processRows($rows, $duplicateByRow);
            } catch (Throwable $e) {
                return new ImportResult(
                    success: false,
                    message: 'Import failed: '.$e->getMessage(),
                    errors: [],
                    totalRows: count($rows),
                    canProceed: false,
                );
            }
        } finally {
            fclose($handle);
        }
    }

    protected function parseAndValidateRows($handle, array $headers): array
    {
        $rows = [];
        $errors = [];

        $rules = $this->schemaClass()::validatorRules();
        $keyMap = $this->buildKeyMap($headers);
        $columnCount = count($headers);

        while (($raw = fgetcsv($handle)) !== false) {
            if ($this->isRowEmpty($raw)) {
                continue;
            }

            $rowNumber = count($rows) + count($errors) + 1;

            if ($columnCount !== count($raw)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['_columns' => ['Column count mismatch: expected '.$columnCount.', got '.count($raw).'.']],
                ];

                continue;
            }

            $data = $this->applyKeyMap(array_combine($headers, $raw), $keyMap);

            $validator = Validator::make($data, $rules);
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

    /**
     * Precompute the canonical key for every CSV header once per file.
     *
     * @return array<string, string>
     */
    protected function buildKeyMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $header) {
            $header = (string) $header;
            $map[$header] = CsvHeaderNormalizer::normalize($header) ?? strtolower(trim($header));
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $keyMap
     * @return array<string, mixed>
     */
    protected function applyKeyMap(array $data, array $keyMap): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[$keyMap[$key] ?? $key] = $value;
        }

        return $normalized;
    }

    protected function buildDuplicateIndex(array $rows): array
    {
        $duplicateByRow = [];

        foreach ($rows as $row) {
            $duplicates = $row['_duplicates'] ?? [];

            if ($duplicates === []) {
                continue;
            }

            $identifiers = [];
            $messages = [];

            foreach ($duplicates as $duplicate) {
                $identifiers[$duplicate['key']] = $duplicate['value'];
                $messages[] = sprintf(
                    "Existing record found for %s: '%s'.",
                    $duplicate['key'],
                    $duplicate['value'],
                );
            }

            $duplicateByRow[] = array_merge([
                'row' => $row['row'],
                'message' => implode(' ', $messages),
            ], $identifiers);
        }

        return $duplicateByRow;
    }

    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (! blank($value)) {
                return false;
            }
        }

        return true;
    }

    abstract protected function schemaClass(): string;

    abstract protected function findDuplicates(array $rows, array &$errors): array;

    abstract protected function processRows(array $rows, array $duplicateByRow): ImportResult;

    protected function resolveReferences(array $rows, array &$errors): array
    {
        return $rows;
    }

    protected function readHeaders($handle): array
    {
        $raw = fgetcsv($handle);
        if ($raw === false || $raw === null) {
            return [];
        }

        return array_map(fn ($header) => CsvHeaderNormalizer::normalize($header) ?? '', $raw);
    }

    protected function normalizeRow(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalizedKey = CsvHeaderNormalizer::normalize((string) $key) ?? strtolower(trim((string) $key));
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

    protected bool $overwriteExisting = false;

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

    private function checkDuplicates(array &$rows, int $totalRows, array $validated, bool $dryRun = false): ?ImportResult
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

        $this->overwriteExisting = $this->wantsOverwrite($validated);

        if ($dryRun) {
            return null;
        }

        $hasDuplicatesWithoutOverwrite = $duplicateByRow !== [] && ! $this->overwriteExisting;

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

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function wantsOverwrite(array $validated): bool
    {
        $value = $validated['overwrite_existing'] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['update', 'true', '1', 'yes'], true);
    }
}
