<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Import\ImportTeachers;
use App\Data\Results\ImportResult;
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

    public string $queue = 'imports';

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly string $importJobId) {}

    public function handle(): void
    {
        $central = config('tenancy.database.central_connection');

        // Claim under row lock — mirrors GradeExamAttemptJob's claim pattern.
        // Prevents: (a) two workers racing the same row, (b) a late retry
        // re-running an import that already finished.
        $claimed = DB::connection($central)->transaction(function () use ($central) {
            $row = DB::connection($central)
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->status === 'completed') {
                return null;
            }

            DB::connection($central)
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->update(['status' => 'processing', 'updated_at' => now()]);

            return $row;
        });

        if ($claimed === null) {
            Log::info('ImportTeachersJob: skipped — already completed or row missing', [
                'import_job_id' => $this->importJobId,
            ]);

            return;
        }

        tenancy()->initialize($claimed->tenant_id);

        $tempPath = null;

        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'teacher_import_');
            throw_if($tempPath === false, new RuntimeException('Failed to create temp file for teacher import.'));

            $written = file_put_contents($tempPath, $claimed->file_contents);
            throw_if($written === false, new RuntimeException('Failed to write import file to disk.'));

            $validated = json_decode($claimed->meta, true);
            $validated['dry_run'] = 'false';

            $result = app(ImportTeachers::class)->execute($validated, $tempPath, false);

            // Mark completed BEFORE notifying — if notify throws, a retry
            // sees 'completed' and skips re-running the CSV, avoiding duplicate imports.
            DB::connection($central)
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->update(['status' => 'completed', 'updated_at' => now()]);

            $this->notifyComplete($result, $claimed->tenant_id);

            DB::connection($central)
                ->table('import_jobs')
                ->where('id', $this->importJobId)
                ->delete();
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            tenancy()->end();
        }
    }

    public function notifyComplete(ImportResult $result, string $tenantId): void
    {
        event(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: $tenantId,
            action: 'teacher_import_completed',
            description: 'Teacher import completed.',
            meta: [
                'imported' => $result->getImported(),
                'skipped' => $result->getSkipped(),
                'updated' => $result->getUpdated(),
                'total_rows' => $result->getTotalRows(),
            ],
        ));
    }

    public function failed(Throwable $e): void
    {
        $central = config('tenancy.database.central_connection');

        DB::connection($central)
            ->table('import_jobs')
            ->where('id', $this->importJobId)
            ->update([
                'status' => 'failed',
                'retain_until' => now()->addDays(3),
                'updated_at' => now(),
            ]);

        Log::error('Teachers import failed permanently', [
            'import_job_id' => $this->importJobId,
            'error' => $e->getMessage(),
            'retained_until' => now()->addDays(3)->toIso8601String(),
        ]);
    }
}
