<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\SubmissionStatus;
use App\Models\Tenant\Submission\Concerns\HasLifecycle;
use App\Models\Tenant\Submission\Concerns\HasValidation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use HasFactory,
        HasLifecycle,
        HasUuids,
        HasValidation,
        SoftDeletes;

    /** Papers are occurrence-scoped: one teacher's paper inside one AssessmentSchedule. */
    protected $table = 'teacher_submissions';

    protected $fillable = [
        'assessment_schedule_id',
        'teacher_id',
        'subject_id',
        'title',
        'description',
        'status',
        'total_marks',
        'submitted_at',
        'returned_at',
        'approved_at',
        'approved_by',
        'exam_id',
    ];

    protected $casts = [
        'status' => SubmissionStatus::class,
        'total_marks' => 'decimal:2',
        'submitted_at' => 'datetime',
        'returned_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<AssessmentSchedule, Submission> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AssessmentSchedule::class, 'assessment_schedule_id');
    }

    /** @return BelongsTo<User, Submission> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return BelongsTo<Subject, Submission> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<User, Submission> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<Exam, Submission> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return HasMany<SubmissionQuestion, Submission> */
    public function submissionQuestions(): HasMany
    {
        return $this->hasMany(SubmissionQuestion::class)->orderBy('order');
    }

    /** @return HasMany<SubmissionComment, Submission> */
    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionComment::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->teacher_id === $user->id;
    }
}
