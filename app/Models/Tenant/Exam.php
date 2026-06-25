<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Data\Values\ExamSettings;
use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Models\Tenant\Exam\Concerns\HasAttempts;
use App\Models\Tenant\Exam\Concerns\HasBroadcasting;
use App\Models\Tenant\Exam\Concerns\HasLifecycle;
use App\Models\Tenant\Exam\Concerns\HasValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Subject|null $subject
 * @property-read ClassLevel|null $classLevel
 */

class Exam extends Model
{
    use HasAttempts, HasBroadcasting, HasFactory, HasLifecycle, HasUuids, HasValidation, SoftDeletes;

    protected $fillable = [
        'title',
        'subject_id',
        'class_level_id',
        'class_arm_id',
        'term_id',
        'type',
        'status',
        'duration_minutes',
        'total_marks',
        'pass_mark',
        'max_attempts',
        'scheduled_start',
        'instructions',
        'settings',
        'created_by',
    ];

    protected $appends = ['is_published'];

    protected $casts = [
        'status' => ExamStatus::class,
        'scheduled_start' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ExamStatus::Active);
    }

    public function scopeActiveAndStarted(Builder $query): Builder
    {
        return $query
            ->where('status', ExamStatus::Active)
            ->where('scheduled_start', '<=', now());
    }

    public function scopeWindowExpired(Builder $query): Builder
    {
        return $query
            ->where('status', ExamStatus::Active)
            ->where('window_end', '<', now());
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === ExamStatus::Published;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            RoleType::Teacher->value => $query->where('created_by', $user->id),
            default => $query,
        };
    }
}
