<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Domains\Assessments\Exceptions\ScheduleSubjectOverlapException;
use App\Domains\Assessments\Support\ScheduleSubjectRules;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\ScheduleSubject;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\BaseTenantTest;

class ScheduleSubjectRulesTest extends BaseTenantTest
{
    protected Subject $subject;

    protected Subject $otherSubject;

    protected AssessmentSchedule $schedule;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $this->otherSubject = Subject::create(['name' => 'English', 'code' => 'ENG101']);

        $classLevel = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10']);
        $this->academicSession = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);
        $this->term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $this->academicSession->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);
        $this->assessment = Assessment::create([
            'title' => 'End of Term Exam',
            'created_by' => $this->admin->id,
            'total_marks' => 100,
            'duration_minutes' => 90,
        ]);

        $this->schedule = AssessmentSchedule::create([
            'assessment_id' => $this->assessment->id,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
            'class_level_id' => $classLevel->id,
            'question_submission_ends' => Carbon::parse('2026-09-01 09:00'),
            'assessment_starts' => Carbon::parse('2026-09-08 09:00'),
            'assessment_ends' => Carbon::parse('2026-09-08 11:00'),
        ]);
    }

    #[Test]
    public function a_slot_window_passes_when_the_schedule_has_no_conflicting_slots(): void
    {
        ScheduleSubjectRules::canAssignWindow()(
            $this->schedule,
            Carbon::parse('2026-09-08 09:00'),
            Carbon::parse('2026-09-08 10:00'),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function a_slot_window_fails_with_an_overlap_exception_naming_the_conflict(): void
    {
        $conflict = $this->makeSlot('2026-09-08 09:30', '2026-09-08 10:30');

        try {
            ScheduleSubjectRules::canAssignWindow()(
                $this->schedule,
                Carbon::parse('2026-09-08 10:00'),
                Carbon::parse('2026-09-08 11:00'),
            );
            $this->fail('ScheduleSubjectOverlapException was not thrown.');
        } catch (ScheduleSubjectOverlapException $exception) {
            self::assertSame(
                sprintf(
                    "Time slot overlaps with '%s' (%s to %s) already scheduled under this class.",
                    $this->subject->name,
                    $conflict->starts_at->toDateTimeString(),
                    $conflict->ends_at->toDateTimeString(),
                ),
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function an_existing_slot_can_be_moved_without_overlapping_itself(): void
    {
        $slot = $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');

        ScheduleSubjectRules::canAssignWindow($slot->id)(
            $this->schedule,
            Carbon::parse('2026-09-08 09:30'),
            Carbon::parse('2026-09-08 10:30'),
        );

        $this->expectNotToPerformAssertions();
    }

    protected function makeSlot(string $startsAt, string $endsAt): ScheduleSubject
    {
        return ScheduleSubject::create([
            'assessment_schedule_id' => $this->schedule->id,
            'subject_id' => $this->subject->id,
            'starts_at' => Carbon::parse($startsAt),
            'ends_at' => Carbon::parse($endsAt),
            'duration_minutes' => 60,
        ]);
    }

    #[Test]
    public function touching_boundaries_do_not_count_as_overlap(): void
    {
        $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');

        ScheduleSubjectRules::canAssignWindow()(
            $this->schedule,
            Carbon::parse('2026-09-08 10:00'),
            Carbon::parse('2026-09-08 11:00'),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function overlapping_ranges_are_allowed_on_a_different_class_schedule(): void
    {
        $otherLevel = ClassLevel::create(['name' => 'Grade 11', 'slug' => 'grade-11']);
        $otherSchedule = AssessmentSchedule::create([
            'assessment_id' => $this->assessment->id,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
            'class_level_id' => $otherLevel->id,
            'question_submission_ends' => Carbon::parse('2026-09-01 09:00'),
            'assessment_starts' => Carbon::parse('2026-09-08 09:00'),
            'assessment_ends' => Carbon::parse('2026-09-08 11:00'),
        ]);

        $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');

        ScheduleSubjectRules::canAssignWindow()(
            $otherSchedule,
            Carbon::parse('2026-09-08 09:30'),
            Carbon::parse('2026-09-08 10:30'),
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function creating_an_overlapping_slot_returns_422_naming_the_conflict(): void
    {
        $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');

        $response = $this->postJson(
            "/api/assessment-schedules/{$this->schedule->id}/schedule-subjects",
            [
                'subject_id' => $this->otherSubject->id,
                'starts_at' => '2026-09-08 09:30:00',
                'ends_at' => '2026-09-08 10:30:00',
                'duration_minutes' => 60,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString($this->subject->name, $response->json('message'));
    }

    #[Test]
    public function updating_a_slot_into_an_overlap_returns_422(): void
    {
        $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');
        $moving = ScheduleSubject::create([
            'assessment_schedule_id' => $this->schedule->id,
            'subject_id' => $this->otherSubject->id,
            'starts_at' => Carbon::parse('2026-09-08 10:00'),
            'ends_at' => Carbon::parse('2026-09-08 11:00'),
            'duration_minutes' => 60,
        ]);

        $response = $this->patchJson(
            "/api/assessment-schedules/{$this->schedule->id}/schedule-subjects/{$moving->id}",
            [
                'subject_id' => $this->otherSubject->id,
                'starts_at' => '2026-09-08 09:30:00',
                'ends_at' => '2026-09-08 10:30:00',
                'duration_minutes' => 60,
            ]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString($this->subject->name, $response->json('message'));
    }

    #[Test]
    public function updating_a_slot_ignores_itself_and_succeeds(): void
    {
        $slot = $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');

        $response = $this->patchJson(
            "/api/assessment-schedules/{$this->schedule->id}/schedule-subjects/{$slot->id}",
            [
                'subject_id' => $this->subject->id,
                'starts_at' => '2026-09-08 09:30:00',
                'ends_at' => '2026-09-08 10:30:00',
                'duration_minutes' => 60,
            ]
        );

        $response->assertSuccessful();
    }

    #[Test]
    public function touching_boundaries_are_accepted_on_create(): void
    {
        $this->makeSlot('2026-09-08 09:00', '2026-09-08 10:00');

        $this->postJson(
            "/api/assessment-schedules/{$this->schedule->id}/schedule-subjects",
            [
                'subject_id' => $this->otherSubject->id,
                'starts_at' => '2026-09-08 10:00:00',
                'ends_at' => '2026-09-08 11:00:00',
                'duration_minutes' => 60,
            ]
        )->assertStatus(201);
    }
}
