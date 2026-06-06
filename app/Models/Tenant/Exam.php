<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Data\Values\ExamSettings;
use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => ExamStatus::class,
        'scheduled_start' => 'datetime',
        'approved_at' => 'datetime',
        'settings' => ExamSettings::class,
        'duration_minutes' => 'integer',
        'total_marks' => 'decimal:2',
        'pass_mark' => 'decimal:2',
        'max_attempts' => 'integer',
        'expected_attempts' => 'integer',
        'completed_attempts' => 'integer',
        'window_end' => 'datetime',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeBySubject(Builder $query, string $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByClassLevel(Builder $query, string $classLevelId): Builder
    {
        return $query->where('class_level_id', $classLevelId);
    }

    public function scopeByClassArm(Builder $query, string $classArmId): Builder
    {
        return $query->where('class_arm_id', $classArmId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ExamStatus::Active->value);
    }

    public function scopeActiveAndStarted(Builder $query): Builder
    {
        return $query->where('status', ExamStatus::Active->value)
            ->where('scheduled_start', '<=', now());
    }

    public function scopeWindowExpired(Builder $query): Builder
    {
        return $query->where('status', ExamStatus::Active->value)
            ->where('window_end', '<', now());
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id;
        // TODO: v2 — expand to team/department ownership via exam_collaborators pivot
    }

    public function isDraft(): bool
    {
        return $this->status === ExamStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === ExamStatus::Submitted;
    }

    public function isActive(): bool
    {
        return $this->status === ExamStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExamStatus::Completed;
    }
}
