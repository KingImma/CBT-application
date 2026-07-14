<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Import\Jobs\ImportStudentsJob;
use App\Domains\Import\Jobs\ImportTeachersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverStuckImports extends Command
{
    protected $signature = 'imports:recover-stuck {--minutes=15 : Age threshold before considering a pending import stuck}';

    protected $description = 'Re-dispatch import jobs whose row is still pending/processing past the age threshold';

    public function handle(): int
    {
        $central = config('tenancy.database.central_connection');
        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $stuck = DB::connection($central)
            ->table('import_jobs')
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck imports found.');

            return self::SUCCESS;
        }

        foreach ($stuck as $row) {
            if (! in_array($row->type, ['teacher', 'student'], true)) {
                $this->warn("  Unknown import type '{$row->type}' for {$row->id}, skipped.");
                continue;
            }

            match ($row->type) {
                'teacher' => ImportTeachersJob::dispatch($row->id),
                'student' => ImportStudentsJob::dispatch($row->id),
                default => $this->warn("  Unknown import type '{$row->type}' for {$row->id}, skipped."),
            };

            $this->line("  Re-dispatched {$row->type} import {$row->id}");
        }

        $this->info("Recovered {$stuck->count()} stuck import job(s).");

        return self::SUCCESS;
    }
}
