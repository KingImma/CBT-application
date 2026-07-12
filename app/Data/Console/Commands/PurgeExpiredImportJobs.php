<?php

declare(strict_types=1);

namespace App\Data\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeExpiredImportJobs extends Command
{
    protected $signature = 'imports:purge-expired';

    protected $description = 'Delete failed import_jobs rows past their retention window (contains student/teacher PII)';

    public function handle(): int
    {
        $central = config('tenancy.database.central_connection');

        $deleted = DB::connection($central)
            ->table('import_jobs')
            ->where('status', 'failed')
            ->whereNotNull('retain_until')
            ->where('retain_until', '<', now())
            ->delete();

        $this->info("Purged {$deleted} expired failed import job(s) containing PII.");

        return self::SUCCESS;
    }
}
