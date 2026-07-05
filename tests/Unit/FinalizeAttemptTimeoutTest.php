<?php

namespace Tests\Unit;

use App\Actions\Base\UpdateAction;
use App\Actions\Tenants\Exam\Attempts\FinalizeAttempt;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Support\Exam\ExamSessionStateStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinalizeAttemptTimeoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('class_level_id')->nullable();
            $table->uuid('class_arm_id')->nullable();
            $table->uuid('term_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->decimal('total_marks', 6, 2)->default(0);
            $table->decimal('pass_mark', 6, 2)->nullable();
            $table->integer('max_attempts')->default(1);
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->integer('expected_attempts')->default(0);
            $table->integer('completed_attempts')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->integer('attempt_number')->default(1);
            $table->string('status')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->integer('time_spent_seconds')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exams');

        parent::tearDown();
    }

    public function test_timed_out_attempt_updates_exam_completion_counts_and_emits_event(): void
    {
        Event::fake();

        $exam = Exam::create([
            'id' => 'exam-1',
            'status' => ExamStatus::Active,
            'completed_attempts' => 0,
            'expected_attempts' => 2,
        ]);

        $attempt = ExamAttempt::create([
            'id' => 'attempt-1',
            'exam_id' => $exam->id,
            'student_id' => '019f2d03-b490-739b-a977-33f64d34c6bd',
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now(),
        ]);
        $attempt->status = ExamAttemptStatus::InProgress;

        $updateAction = new UpdateAction;
        $stateStore = new ExamSessionStateStore;
        $action = new FinalizeAttempt($updateAction, $stateStore);

        $action->execute($attempt, null, 'stale_heartbeat');

        $exam->refresh();

        $this->assertSame(1, $exam->completed_attempts);

        Event::assertDispatched(ExamAttemptsUpdated::class, function (ExamAttemptsUpdated $event) use ($exam): bool {
            return $event->examId === $exam->id
                && $event->completedAttempts === 1
                && $event->expectedAttempts === 2
                && $event->status === ExamStatus::Active;
        });
    }
}
