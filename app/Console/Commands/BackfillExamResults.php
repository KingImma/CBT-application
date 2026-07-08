<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamResult;
use Illuminate\Console\Command;

class BackfillExamResults extends Command
{
    protected $signature = 'exams:backfill-results
        {--dry-run : List attempts that would be processed without inserting}
        {--tenant= : Process only a specific tenant ID}';

    protected $description = 'Create exam_results rows from existing graded exam_attempts';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->where('is_active', true)->get()
            : Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use (&$totalCreated) {
                ExamAttempt::where('status', 'graded')
                    ->whereDoesntHave('result')
                    ->chunkById(100, function ($attempts) use (&$totalCreated) {
                        foreach ($attempts as $attempt) {
                            $exam = $attempt->exam;

                            if ($exam === null) {
                                $this->warn("Attempt {$attempt->id}: exam not found, skipping");

                                continue;
                            }

                            $passed = match (true) {
                                $exam->pass_mark === null => null,
                                $attempt->percentage_score === null => false,
                                default => (float) $attempt->percentage_score >= (float) $exam->pass_mark,
                            };

                            if ($this->option('dry-run')) {
                                $this->line("Would create result for attempt {$attempt->id}, exam {$exam->id}, student {$attempt->student_id}");

                                continue;
                            }

                            ExamResult::create([
                                'exam_attempt_id' => $attempt->id,
                                'student_id' => $attempt->student_id,
                                'exam_id' => $exam->id,
                                'subject_id' => $exam->subject_id,
                                'term_id' => $exam->term_id,
                                'academic_session_id' => $attempt->academic_session_id ?? $exam->term?->academic_session_id,
                                'total_score' => $attempt->total_score,
                                'percentage_score' => $attempt->percentage_score,
                                'grade' => $attempt->grade,
                                'objective_score' => $attempt->objective_score,
                                'theory_score' => $attempt->theory_score,
                                'is_theory_graded' => $attempt->is_theory_graded,
                                'rank_in_class' => $attempt->rank_in_class,
                                'passed' => $passed,
                                'graded_at' => $attempt->submitted_at ?? $attempt->updated_at,
                            ]);

                            $totalCreated++;
                        }
                    });
            });
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No rows created.');
        } else {
            $this->info("Created {$totalCreated} exam result(s).");
        }

        return self::SUCCESS;
    }
}
