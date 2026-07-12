<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Import\ImportTeachers;
use App\Data\Results\ImportResult;
use App\Events\ActivityFeedEvent;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue transport for the teacher CSV import. Re-establishes the tenant
 * database context lost when the job leaves the request, then runs the
 * existing ImportTeachers action unchanged and notifies the school admin
 * when the work is done.
 */
class ImportTeachersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $path,
        public readonly array $validated,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('ImportTeachersJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        $tenant->run(function () {
            $result = app(ImportTeachers::class)->execute($this->validated, $this->path, false);

            $this->notifyComplete($result);
        });

        $this->cleanupFile();
    }

    public function notifyComplete(ImportResult $result): void
    {
        event(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: $this->tenantId,
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
        Log::error('ImportTeachersJob failed permanently', [
            'tenant_id' => $this->tenantId,
            'path' => $this->path,
            'error' => $e->getMessage(),
        ]);

        $this->cleanupFile();
    }

    private function cleanupFile(): void
    {
        if (file_exists($this->path)) {
            @unlink($this->path);
        }
    }
}
