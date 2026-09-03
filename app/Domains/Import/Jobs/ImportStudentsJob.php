<?php

declare(strict_types=1);

namespace App\Domains\Import\Jobs;

use App\Domains\Import\Actions\ImportStudents;
use App\Domains\Import\Data\ImportResult;
use App\Events\ActivityFeedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    
    public int $backoff = 30;
    
    public int $timeout = 300;

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->importJobId))->releaseAfter(60)];
    }

    public function __construct(private readonly string $importJobId)
    {
        $this->queue = 'imports';
    }

    public function handle(): void
    {
        $central = 'pgsql_imports';

        DB::purge($central);
        
        $connection = DB::connection($central);

        $claimed = $connection->transaction(function () use ($connection) {
            $row = $connection
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->lockForUpdate()
                ->first();
    
            if ($row === null || $row->status === 'completed') {
                return null;
            }
    
            $connection
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);
    
            return $row;
        }, 3);

        if ($claimed === null) {
            Log::info('ImportStudentsJob: skipped — already completed or row missing', [
                'import_job_id' => $this->importJobId,
            ]);

            return;
        }

        tenancy()->initialize($claimed->tenant_id);

        $tempPath = null;

        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'student_import_');
            throw_if($tempPath === false, new RuntimeException('Failed to create temp file for student import.'));

            $written = file_put_contents($tempPath, $claimed->file_contents);
            throw_if($written === false, new RuntimeException('Failed to write import file to disk.'));

            $validated = json_decode($claimed->meta, true);
            $validated['dry_run'] = 'false';

            $result = app(ImportStudents::class)->execute($validated, $tempPath, false);

            if (! $result->isSuccess()) {
                $connection
                    ->table('import_jobs')
                    ->where('id', $this->importJobId)
                    ->update([
                        'status' => 'failed',
                        'retain_until' => now()->addDays(3),
                        'updated_at' => now(),
                    ]);

                try {
                    $this->notifyFailure($result, $claimed->tenant_id);
                } catch (Throwable $e) {
                    Log::error('Failed to send student import failure notification', [
                        'import_job_id' => $this->importJobId,
                        'error' => $e->getMessage(),
                    ]);
                }

                return;
            }

            $connection
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->update(['status' => 'completed', 'updated_at' => now()]);

            try {
                $this->notifyComplete($result, $claimed->tenant_id);
            } catch (Throwable $e) {
                Log::error('Failed to send student import completion notification', [
                    'import_job_id' => $this->importJobId,
                    'error' => $e->getMessage(),
                ]);
            }

            $connection
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->delete();
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            tenancy()->end();
            DB::purge($central);
        }
    }

    public function notifyComplete(ImportResult $result, string $tenantId): void
    {
        event(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: $tenantId,
            action: 'student_import_completed',
            description: 'Student import completed.',
            meta: [
                'imported' => $result->getImported(),
                'skipped' => $result->getSkipped(),
                'updated' => $result->getUpdated(),
                'total_rows' => $result->getTotalRows(),
            ],
        ));
    }

    public function notifyFailure(ImportResult $result, string $tenantId): void
    {
        event(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: $tenantId,
            action: 'student_import_failed',
            description: $result->getMessage() ?? 'Student import failed.',
            meta: [
                'errors' => $result->getErrors(),
                'total_rows' => $result->getTotalRows(),
                'retained' => true,
            ],
        ));
    }

    public function failed(Throwable $e): void
    {
        $central = 'pgsql_imports';
    
        DB::purge($central);
    
        $connection = DB::connection($central);
    
        try {
            $connection
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->update([
                    'status' => 'failed',
                    'retain_until' => now()->addDays(3),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $statusError) {
            Log::critical('Could not mark student import as failed', [
                'import_job_id' => $this->importJobId,
                'original_error' => $e->getMessage(),
                'status_error' => $statusError->getMessage(),
            ]);
        }
    
        Log::error('Students import failed permanently', [
            'import_job_id' => $this->importJobId,
            'error' => $e->getMessage(),
        ]);
    }
}
