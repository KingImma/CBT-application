<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\RoleType;
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
        HasUuids,
        SoftDeletes;

    protected $fillable = [
        'title',
        'class_level_id',
        'class_arm_id',
        'created_by',
        'total_marks',
        'duration_minutes',
        'description',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    /** @return HasMany<AssessmentSchedule, Assessment> */
    public function schedules(): HasMany
    {
        return $this->hasMany(AssessmentSchedule::class);
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
