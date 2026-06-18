<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class StressProvisionCommand extends Command
{
    protected $signature = 'stress:provision
        {--port=8899 : Port for the dev server}
        {--output=storage/app/stress-provision.json : Path to write provisioning data}';

    protected $description = 'Provision a tenant and seed data for stress testing';

    public function handle(): int
    {
        $plan = SubscriptionPlan::factory()->create();

        $suffix = Str::random(6);
        $tenantId = 'stress-test-'.$suffix;
        $dbName = 'tenant_stress_test_'.$suffix;

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Stress Test School',
            'handle' => 'stress-test-handle-'.$suffix,
            'slug' => 'stress-test-slug-'.$suffix,
            'database' => $dbName,
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // Set explicit database name
        $tenant->setInternal('db_name', $dbName);
        $tenant->save();

        $manager = $tenant->database()->manager();

        if ($manager->databaseExists($dbName)) {
            $manager->deleteDatabase($tenant);
        }

        $manager->createDatabase($tenant);

        // Initialize tenancy then run migrations directly
        tenancy()->initialize($tenant);

        $this->info('Running tenant migrations...');
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => true,
            '--force' => true,
        ]);
        $this->info('Migrations complete: '.Artisan::output());

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'tenant']);

        $teacher = User::create([
            'first_name' => 'Teacher', 'last_name' => 'Stress',
            'email' => 'teacher-stress@example.com', 'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value, 'is_active' => true,
        ]);

        $student = User::create([
            'first_name' => 'Student', 'last_name' => 'Stress',
            'email' => 'student-stress@example.com', 'password' => bcrypt('password'),
            'role' => RoleType::Student->value, 'is_active' => true,
        ]);
        $student->assignRole('student');

        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MTH']);
        $classLevel = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10']);
        $academicSession = AcademicSession::create([
            'name' => '2025/2026', 'start_date' => '2025-09-01',
            'end_date' => '2026-08-31', 'is_current' => true,
        ]);
        $term = Term::create([
            'name' => 'First Term', 'academic_session_id' => $academicSession->id,
            'start_date' => '2025-09-01', 'end_date' => '2025-12-20', 'is_current' => true,
        ]);

        $exam = Exam::create([
            'title' => 'Stress Test Exam', 'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id, 'term_id' => $term->id,
            'created_by' => $teacher->id,
            'type' => ExamType::Exam->value, 'status' => ExamStatus::Active->value,
            'duration_minutes' => 60, 'total_marks' => 1, 'pass_mark' => 50,
            'max_attempts' => 5,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);

        $question = Question::create([
            'subject_id' => $subject->id, 'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::McqSingle->value,
            'content' => 'What is 2 + 2?', 'default_marks' => 1, 'is_active' => true,
        ]);
        $question->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id, 'question_id' => $question->id,
            'order' => 1, 'marks' => 1,
        ]);

        $correctOption = $question->options()->where('is_correct', true)->first();

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id, 'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now(),
        ]);

        $token = $student->createToken('stress-test')->plainTextToken;

        tenancy()->end();

        // Start the dev server (backgrounded so it survives command exit)
        $port = $this->option('port');
        $serverLog = storage_path('logs/stress-server.log');
        $cmd = "nohup php artisan serve --port={$port} > {$serverLog} 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        usleep(1_000_000);

        // Write provisioning data
        $outputPath = $this->option('output');
        file_put_contents(
            $outputPath,
            json_encode([
                'success' => true,
                'tenant_id' => $tenant->id,
                'tenant_handle' => $tenant->handle,
                'student_id' => $student->id,
                'student_token' => $token,
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'option_id' => $correctOption->id,
                'attempt_id' => $attempt->id,
                'server_port' => $port,
                'server_pid' => $pid,
            ], JSON_PRETTY_PRINT),
        );

        $this->info('Stress test environment provisioned successfully!');
        $this->info("Tenant: {$tenant->id} (handle: {$tenant->handle})");
        $this->info("Server running on port {$port} (PID: {$pid})");
        $this->info("Output: {$outputPath}");

        return self::SUCCESS;
    }
}
