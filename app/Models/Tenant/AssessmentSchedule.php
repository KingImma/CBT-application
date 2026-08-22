<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\AssessmentStatus;
use App\Enums\QuestionSubmissionStatus;
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
        'question_submission_ends',
        'assessment_starts',
        'assessment_ends',
        'activated_at',
    ];

    protected $casts = [
        'question_submission_status' => QuestionSubmissionStatus::class,
        'assessment_status' => AssessmentStatus::class,
        'question_submission_ends' => 'datetime',
        'assessment_starts' => 'datetime',
        'assessment_ends' => 'datetime',
        'activated_at' => 'datetime',
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
