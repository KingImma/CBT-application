<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\AssessmentStatus;
use App\Enums\RoleType;
use App\Models\Tenant\Assessment\Concerns\HasLifecycle;
use App\Models\Tenant\Assessment\Concerns\HasValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory,
        HasLifecycle,
        HasUuids,
        HasValidation,
        SoftDeletes;

    protected $fillable = [
        'title',
        'class_level_id',
        'class_arm_id',
        'term_id',
        'created_by',
        'status',
        'total_marks',
        'duration_minutes',
        'submission_opens_at',
        'submission_closes_at',
        'student_starts_at',
        'student_ends_at',
        'activated_at',
        'instructions',
    ];

    protected $casts = [
        'status' => AssessmentStatus::class,
        'total_marks' => 'decimal:2',
        'duration_minutes' => 'integer',
        'submission_opens_at' => 'datetime',
        'submission_closes_at' => 'datetime',
        'student_starts_at' => 'datetime',
        'student_ends_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    /** @return HasMany<Submission, Assessment> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** @return BelongsTo<ClassLevel, Assessment> */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /** @return BelongsTo<ClassArm, Assessment> */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    /** @return BelongsTo<Term, Assessment> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<User, Assessment> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Admins see every assessment; a teacher only sees the ones whose class
     * level they hold a subject assignment for (decision #1) — those are the
     * only assessments they could ever author a submission against.
     *
     * @param  Builder<Assessment>  $query
     * @return Builder<Assessment>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return $query;
        }

        return $query->whereIn(
            'class_level_id',
            TeacherSubjectAssignment::query()
                ->select('class_level_id')
                ->where('user_id', $user->id)
        );
    }

    /**
     * Row-level counterpart of scopeVisibleTo: may this teacher author against
     * this assessment's class level at all?
     */
    public function isOpenToTeacher(User $user): bool
    {
        return TeacherSubjectAssignment::where('user_id', $user->id)
            ->where('class_level_id', $this->class_level_id)
            ->exists();
    }
}
