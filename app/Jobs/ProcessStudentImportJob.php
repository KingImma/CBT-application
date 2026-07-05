<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Import\ImportStudents;
use App\Models\Tenant;
use App\Models\Tenant\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStudentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $importLogId,
        public readonly string $tenantId,
    ) {
        $this->queue = 'imports';
    }

    public function handle(ImportStudents $importStudents): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($importStudents) {
            $log = ImportLog::find($this->importLogId);

            if ($log === null || $log->status !== 'pending') {
                return;
            }

            $log->update(['status' => 'processing']);

            try {
                $meta = $log->meta;
                $overwriteExisting = ($meta['overwrite_existing'] ?? 'skip') === 'update';

                $result = $importStudents->processFromParsedRows(
                    $meta['rows'],
                    $overwriteExisting,
                );

                $log->update([
                    'status' => 'completed',
                    'imported' => $result->getImported(),
                    'skipped' => $result->getSkipped(),
                    'updated' => $result->getUpdated(),
                    'errors' => $result->getErrors(),
                    'completed_at' => now(),
                ]);
            } catch (\Exception $e) {
                $log->update([
                    'status' => 'failed',
                    'errors' => [['message' => $e->getMessage()]],
                    'completed_at' => now(),
                ]);
            }
        });
    }
}
