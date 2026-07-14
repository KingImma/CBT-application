<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Exams\Actions\CalculateScore;
use App\Domains\Exams\Actions\ResolveGrade;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use Illuminate\Console\Command;

class RecomputeGradesForGradedAttempts extends Command
{
    protected $signature = 'exams:recompute-grades
                           {--tenant= : Process a single tenant by slug}
                           {--dry-run : Preview without writing}';

    protected $description = 'Recompute grade for already-graded attempts stuck at N/A (run after backfilling default grading scale)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        $query = Tenant::query();
        if ($tenantSlug) {
            $query->where('id', $tenantSlug);
        }

        $tenants = $query->get();
        $totalFixed = 0;

        foreach ($tenants as $tenant) {
            $this->info("▶ Tenant: {$tenant->name}");

            try {
                tenancy()->initialize($tenant);

                $grades = GradingScale::where('is_default', true)->first()?->grades;

                if ($grades === null) {
                    $this->warn('  → No default grading scale found, skipping tenant.');
                    continue;
                }

                $count = 0;

                ExamAttempt::where('status', ExamAttemptStatus::Graded->value)
                    ->where('grade', 'N/A')
                    ->whereNotNull('percentage_score')
                    ->chunkById(100, function ($attempts) use ($grades, $isDryRun, &$count) {
                        foreach ($attempts as $attempt) {
                            $newGrade = ResolveGrade::execute((float) $attempt->percentage_score, $grades);

                            $this->line("  {$attempt->id}: N/A -> {$newGrade} ({$attempt->percentage_score}%)".($isDryRun ? ' (dry run)' : ''));

                            if (! $isDryRun) {
                                $attempt->update(['grade' => $newGrade]);
                            }

                            $count++;
                        }
                    });

                $this->info("  ✓ {$count} attempt(s) ".($isDryRun ? 'would be fixed' : 'fixed'));
                $totalFixed += $count;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info(($isDryRun ? 'Would fix' : 'Fixed')." {$totalFixed} attempt(s) total.");

        return self::SUCCESS;
    }
}
