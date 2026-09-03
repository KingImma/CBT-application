<?php

declare(strict_types=1);

namespace App\Domains\Import\Jobs;

use App\Domains\Import\Actions\ImportTeachers;
use App\Domains\Import\Data\ImportResult;
use App\Events\ActivityFeedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImportTeachersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(private readonly string $importJobId)
    {
        $this->onConnection('horizon-redis');
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $central = 'pgsql_imports';
    
        DB::purge($central);
    
        $connection = DB::connection($central);
    
        $claimed = $connection->transaction(function () use ($connection) {
            try {
                $row = $connection
                    ->table('import_jobs')
                    ->where('id', $this->importJobId)
                    ->lockForUpdate()
                    ->first();
            } catch (Throwable $e) {
                Log::error('Import claim SELECT failed', [
                    'import_job_id' => $this->importJobId,
                    'connection' => $connection->getName(),
                    'database' => $connection->getDatabaseName(),
                    'host' => $connection->getConfig('host'),
                    'transaction_level' => $connection->transactionLevel(),
                    'error' => $e->getMessage(),
                    'previous' => $e->getPrevious()?->getMessage(),
                ]);
        
                throw $e;
            }
        
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
            Log::info('ImportTeachersJob: skipped — already completed or row missing', [
                'import_job_id' => $this->importJobId,
            ]);
    
            return;
        }
    
        tenancy()->initialize($claimed->tenant_id);
        
        DB::purge('tenant');
        DB::reconnect('tenant');
        
        $tempPath = null;
    
        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'teacher_import_');
    
            if ($tempPath === false) {
                throw new RuntimeException(
                    'Failed to create temp file for teacher import.'
                );
            }
    
            if (file_put_contents($tempPath, $claimed->file_contents) === false) {
                throw new RuntimeException(
                    'Failed to write import file to disk.'
                );
            }
    
            $validated = json_decode(
                $claimed->meta,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
    
            $validated['dry_run'] = false;
    
            $result = app(ImportTeachers::class)
                ->execute($validated, $tempPath, false);
    
            DB::purge($central);
    
            $connection
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
    
            try {
                $this->notifyComplete($result, $claimed->tenant_id);
            } catch (Throwable $e) {
                Log::warning('Import completed but notification failed', [
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

    public function failed(Throwable $e): void
    {
        $connectionName = 'pgsql_imports';
    
        DB::purge($connectionName);
    
        $connection = DB::connection($connectionName);
    
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
            Log::critical('Could not mark import as failed', [
                'import_job_id' => $this->importJobId,
                'original_error' => $e->getMessage(),
                'status_error' => $statusError->getMessage(),
            ]);
        }
    
        Log::error('Teachers import failed permanently', [
            'import_job_id' => $this->importJobId,
            'error' => $e->getMessage(),
        ]);
    }
}
