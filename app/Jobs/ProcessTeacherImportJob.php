<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Import\ImportTeachers;
use App\Models\Tenant;
use App\Models\Tenant\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessTeacherImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $importLogId,
        public readonly string $tenantId,
    ) {
        $this->queue = 'imports';
    }

    public function handle(ImportTeachers $importTeachers): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($importTeachers) {
            $log = ImportLog::find($this->importLogId);

            if ($log === null || $log->status !== 'pending') {
                return;
            }

            $log->update(['status' => 'processing']);

            try {
                $meta = $log->meta;
                $storedPath = Storage::disk('local')->path($meta['stored_path']);

                $result = $importTeachers->execute(
                    ['overwrite_existing' => $meta['overwrite_existing'] ?? 'skip'],
                    $storedPath,
                    false,
                );

                $log->update([
                    'status' => $result->isSuccess() ? 'completed' : 'failed',
                    'total_rows' => $result->getTotalRows(),
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
