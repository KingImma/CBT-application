<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Import\Support\ImportProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-sent events for one import job.
 *
 * `GET /api/imports/{importJobId}/stream`
 *
 * Events:
 *  - `snapshot`  the current state, sent immediately on connect
 *  - `progress`  whenever the worker advances (rows written, phase changes)
 *  - `done`      terminal state, then the stream closes
 *  - `timeout`   the client should reconnect or fall back to polling
 *
 * The stream closes itself so a php-fpm worker is never parked: it ends on a
 * terminal state, on client disconnect, or after `imports.stream_timeout`.
 */
class ImportJobStreamController extends Controller
{
    public function __invoke(Request $request, string $importJob): StreamedResponse
    {
        $tenantId = (string) tenant('id');
        $progress = ImportProgress::make($importJob);

        abort_if($progress === null, 404, 'Import job not found.');

        $initial = $progress->snapshot() ?? $this->fromDatabase($importJob, $tenantId);

        abort_if($initial === null, 404, 'Import job not found.');
        abort_unless((string) ($initial['tenant_id'] ?? '') === $tenantId, 404, 'Import job not found.');

        $intervalMs = max(200, (int) config('imports.stream_interval_ms', 700));
        $deadline = microtime(true) + max(5, (int) config('imports.stream_timeout', 60));

        return response()->stream(
            function () use ($progress, $importJob, $tenantId, $intervalMs, $deadline, $initial): void {
                @set_time_limit(0);
                ignore_user_abort(true);

                // Flush rather than discard existing buffers: this keeps the
                // frames visible to middleware and to test harnesses that
                // capture streamed responses.
                $flush = static function (): void {
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }

                    flush();
                };

                $send = static function (string $event, array $payload): void {
                    echo 'event: '.$event."\n";
                    echo 'data: '.(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}')."\n\n";
                };

                $last = $initial;

                // Tell EventSource-style clients how fast to reconnect, then send
                // the current state so a late subscriber is not left guessing.
                echo "retry: 3000\n\n";
                $send('snapshot', $last);
                $flush();

                if ($this->isTerminal($last)) {
                    $send('done', $last);

                    return;
                }

                while (true) {
                    if (connection_aborted()) {
                        return;
                    }

                    if (microtime(true) > $deadline) {
                        $send('timeout', $last);

                        return;
                    }

                    usleep($intervalMs * 1000);

                    $payload = $progress->snapshot()
                        ?? $this->fromDatabase($importJob, $tenantId)
                        ?? $last;

                    if ($payload !== $last) {
                        $last = $payload;
                        $send('progress', $last);
                    }

                    if ($this->isTerminal($last)) {
                        $send('done', $last);
                        $flush();

                        return;
                    }

                    // Comment frame: keeps proxies from buffering or closing an
                    // otherwise idle connection.
                    echo ": keep-alive\n\n";
                    $flush();
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isTerminal(array $payload): bool
    {
        return in_array(
            $payload['status'] ?? null,
            [ImportProgress::STATUS_COMPLETED, ImportProgress::STATUS_FAILED],
            true,
        );
    }

    /**
     * Fallback state when the cache entry is gone (Redis restart, TTL, or a job
     * that never reported progress). Successful runs delete the row, so this is
     * only ever a pending/processing/failed view.
     *
     * @return array<string, mixed>|null
     */
    private function fromDatabase(string $importJobId, string $tenantId): ?array
    {
        $row = DB::connection('pgsql_imports')
            ->table('import_jobs')
            ->where('id', $importJobId)
            ->first();

        if ($row === null || (string) $row->tenant_id !== $tenantId) {
            return null;
        }

        return [
            'import_job_id' => (string) $row->id,
            'tenant_id' => (string) $row->tenant_id,
            'type' => (string) $row->type,
            'status' => (string) $row->status,
            'phase' => null,
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported' => 0,
            'skipped' => 0,
            'updated' => 0,
            'message' => null,
            'errors' => [],
            'started_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'finished_at' => null,
        ];
    }
}
