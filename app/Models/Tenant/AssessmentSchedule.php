<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\AssessmentStatus;
use App\Enums\QuestionSubmissionStatus;
use App\Enums\RoleType;
use App\Models\Tenant\AssessmentSchedule\Concerns\HasLifecycle;
use App\Models\Tenant\AssessmentSchedule\Concerns\HasValidation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentSchedule extends Model
{
    use HasFactory,
        HasLifecycle,
        HasUuids,
        HasValidation;

    protected $fillable = [
        'assessment_id',
        'academic_session_id',
        'term_id',
        'class_level_id',
        'class_arm_id',
        'question_submission_ends',
        'assessment_starts',
        'assessment_ends',
        'activated_at',
        'published_at',
        'question_submission_status',
        'assessment_status',
    ];

    /**
     * Mirror the DB defaults so freshly built (unsaved-refreshed) instances
     * already report draft/open.
     */
    protected $attributes = [
        'question_submission_status' => QuestionSubmissionStatus::Open->value,
        'assessment_status' => AssessmentStatus::Draft->value,
    ];

    protected $casts = [
        'question_submission_status' => QuestionSubmissionStatus::class,
        'assessment_status' => AssessmentStatus::class,
        'question_submission_ends' => 'datetime',
        'assessment_starts' => 'datetime',
        'assessment_ends' => 'datetime',
        'activated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /** @return BelongsTo<Assessment, AssessmentSchedule> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<AcademicSession, AssessmentSchedule> */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /** @return BelongsTo<Term, AssessmentSchedule> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<ClassLevel, AssessmentSchedule> */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /** @return BelongsTo<ClassArm, AssessmentSchedule> */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    /**
     * The schedule's class level is what gates authoring now: a teacher may
     * read (and author against) any schedule whose class level they hold a
     * subject assignment for.
     */
    public function isOpenToTeacher(User $user): bool
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return true;
        }

        return TeacherSubjectAssignment::where('user_id', $user->id)
            ->where('class_level_id', $this->class_level_id)
            ->exists();
    }

    /** @return HasMany<ScheduleSubject, AssessmentSchedule> */
    public function scheduleSubjects(): HasMany
    {
        return $this->hasMany(ScheduleSubject::class);
    }

    /** @return HasMany<Submission, AssessmentSchedule> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
