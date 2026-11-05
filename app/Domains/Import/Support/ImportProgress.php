<?php

declare(strict_types=1);

namespace App\Domains\Import\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Live progress of a queued import.
 *
 * The worker writes it, the SSE endpoint reads it. It lives in the shared cache
 * (Redis) rather than in a new table so no migration is needed, and so it keeps
 * working when the HTTP host and the queue worker are different machines
 * (Render serves HTTP, the VPS runs Horizon).
 *
 * The payload outlives the `import_jobs` row: a successful run deletes that row,
 * so the terminal frame has to come from the cache.
 */
final class ImportProgress
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** How long a finished payload stays readable after the run ends. */
    private const TTL_SECONDS = 3600;

    /** Terminal payloads never carry more than this many row errors. */
    private const MAX_ERRORS = 50;

    public function __construct(private readonly string $importJobId)
    {
    }

    public static function make(?string $importJobId): ?self
    {
        return ($importJobId === null || $importJobId === '') ? null : new self($importJobId);
    }

    public static function key(string $importJobId): string
    {
        return 'import:progress:'.$importJobId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshot(): ?array
    {
        $payload = Cache::get(self::key($this->importJobId));

        return is_array($payload) ? $payload : null;
    }

    public function start(string $type, int $totalRows): void
    {
        $this->write(array_merge($this->base(), [
            'type' => $type,
            'status' => self::STATUS_PROCESSING,
            'phase' => 'validated',
            'total_rows' => $totalRows,
        ]));
    }

    public function phase(string $phase, int $processedRows = 0): void
    {
        $this->merge([
            'phase' => $phase,
            'processed_rows' => $processedRows,
        ]);
    }

    public function advance(int $processedRows, int $imported, int $skipped, int $updated): void
    {
        $this->merge([
            'phase' => 'writing',
            'processed_rows' => $processedRows,
            'imported' => $imported,
            'skipped' => $skipped,
            'updated' => $updated,
        ]);
    }

    public function completed(int $imported, int $skipped, int $updated = 0, int $totalRows = 0): void
    {
        $processed = $imported + $skipped;

        $this->merge([
            'status' => self::STATUS_COMPLETED,
            'phase' => 'finished',
            'total_rows' => max($totalRows, $processed),
            'processed_rows' => $processed,
            'imported' => $imported,
            'skipped' => $skipped,
            'updated' => $updated,
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<int, mixed>  $errors
     */
    public function failed(string $message, array $errors = [], ?int $totalRows = null): void
    {
        $this->merge([
            'status' => self::STATUS_FAILED,
            'phase' => 'failed',
            'message' => $message,
            'errors' => array_slice($errors, 0, self::MAX_ERRORS),
            'total_rows' => $totalRows,
            'finished_at' => now()->toIso8601String(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::key($this->importJobId));
    }

    /**
     * @return array<string, mixed>
     */
    private function base(): array
    {
        $now = now()->toIso8601String();

        return [
            'import_job_id' => $this->importJobId,
            'tenant_id' => tenant('id'),
            'type' => null,
            'status' => self::STATUS_PENDING,
            'phase' => null,
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported' => 0,
            'skipped' => 0,
            'updated' => 0,
            'message' => null,
            'errors' => [],
            'started_at' => $now,
            'updated_at' => $now,
            'finished_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function merge(array $changes): void
    {
        $payload = array_merge($this->base(), $this->snapshot() ?? []);

        $payload = array_merge($payload, $changes);
        $payload['updated_at'] = now()->toIso8601String();

        $this->write($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(array $payload): void
    {
        Cache::put(self::key($this->importJobId), $payload, self::TTL_SECONDS);
    }
}
