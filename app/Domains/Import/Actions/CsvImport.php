<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Support\ImportProgress;
use App\Shared\Support\CsvHeaderNormalizer;
use Illuminate\Support\Facades\Log;
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

            $parseStartedAt = microtime(true);
            $parsed = $this->parseAndValidateRows($handle, $headers);
            $parseMs = (int) round((microtime(true) - $parseStartedAt) * 1000);

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

                $writeStartedAt = microtime(true);
                $result = $this->processRows($rows, $duplicateByRow);

                Log::info('Import timed', [
                    'type' => $this->progressType,
                    'rows' => $parsed['totalRows'],
                    'parse_ms' => $parseMs,
                    'write_ms' => (int) round((microtime(true) - $writeStartedAt) * 1000),
                ]);

                return $result;
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

    /** Rows validated per Validator instance (one validator for the chunk). */
    private const VALIDATION_CHUNK_SIZE = 500;

    protected function parseAndValidateRows($handle, array $headers): array
    {
        $rows = [];
        $errors = [];

        $rules = $this->schemaClass()::validatorRules();
        $keyMap = $this->buildKeyMap($headers);
        $columnCount = count($headers);

        // `$chunk` keeps the parse outcome in encounter order so rows and row
        // errors are reported exactly as they appear in the file.
        $chunk = [];
        $rowNumber = 0;

        while (($raw = fgetcsv($handle)) !== false) {
            if ($this->isRowEmpty($raw)) {
                continue;
            }

            $rowNumber++;

            if ($columnCount !== count($raw)) {
                $chunk[$rowNumber] = [
                    'errors' => ['_columns' => ['Column count mismatch: expected '.$columnCount.', got '.count($raw).'.']],
                ];
            } else {
                $chunk[$rowNumber] = [
                    'data' => $this->applyKeyMap(array_combine($headers, $raw), $keyMap),
                ];
            }

            if (count($chunk) >= self::VALIDATION_CHUNK_SIZE) {
                $this->validateChunk($chunk, $rules, $rows, $errors);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->validateChunk($chunk, $rules, $rows, $errors);
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'totalRows' => count($rows) + count($errors),
        ];
    }

    /**
     * Validate many rows with a single Validator instance.
     *
     * Building one `Validator::make()` per CSV row is the most expensive part of
     * parsing a large file (container resolution, translator bootstrapping and
     * rule parsing per row). Wildcard rules validate a whole chunk at once and
     * the per-row messages are re-keyed to the original row numbers, keeping the
     * same response shape as before.
     *
     * Entries are in encounter order and may hold either parsed `data` or a
     * structural `errors` payload (for example a column-count mismatch).
     *
     * @param  array<int, array{data?: array<string, mixed>, errors?: array<string, list<string>>}>  $chunk
     * @param  array<string, mixed>  $rules
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $errors
     */
    private function validateChunk(array $chunk, array $rules, array &$rows, array &$errors): void
    {
        $positions = [];
        $payload = ['rows' => []];

        foreach ($chunk as $rowNumber => $entry) {
            if (! isset($entry['data'])) {
                continue;
            }

            $position = count($positions);
            $positions[$position] = $rowNumber;
            $payload['rows'][$position] = $entry['data'];
        }

        try {
            $wildcardRules = [];
            foreach ($rules as $field => $fieldRules) {
                $wildcardRules['rows.*.'.$field] = $fieldRules;
            }

            $validator = Validator::make($payload, $wildcardRules);
        } catch (Throwable) {
            // Fall back to the per-row validator if wildcard rules are not
            // supported by a custom rule in a schema.
            foreach ($chunk as $rowNumber => $entry) {
                if (! isset($entry['data'])) {
                    $errors[] = ['row' => $rowNumber, 'errors' => $entry['errors']];

                    continue;
                }

                $validator = Validator::make($entry['data'], $rules);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray(),
                    ];

                    continue;
                }

                $rows[] = ['row' => $rowNumber, 'data' => $entry['data']];
            }

            return;
        }

        $byPosition = [];

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $fieldMessages) {
                if (! preg_match('/^rows\.(\d+)\.([^.]+)$/', (string) $key, $matches)) {
                    continue;
                }

                $byPosition[(int) $matches[1]][$matches[2]] = $fieldMessages;
            }
        }

        $position = -1;

        foreach ($chunk as $rowNumber => $entry) {
            if (! isset($entry['data'])) {
                $errors[] = ['row' => $rowNumber, 'errors' => $entry['errors']];

                continue;
            }

            $position++;
            $rowErrors = $byPosition[$position] ?? [];

            if ($rowErrors !== []) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => $rowErrors,
                ];

                continue;
            }

            $rows[] = ['row' => $rowNumber, 'data' => $payload['rows'][$position]];
        }
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
