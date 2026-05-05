<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end'   => 'datetime',
        'session_started_at' => 'datetime',
        'settings'        => 'array',
        'duration_minutes'=> 'integer',
        'session_duration_minutes' => 'integer',
        'total_marks'     => 'decimal:2',
        'pass_mark'       => 'decimal:2',
        'max_attempts'    => 'integer',
    ];

    /**
     * @return BelongsTo<Term,Exam>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Subject,Exam>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<ClassLevel,Exam>
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * @return BelongsTo<ClassArm,Exam>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    /**
     * @return BelongsTo<User,Exam>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExamQuestion>
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    /**
     * @return HasMany<ExamAttempt>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * @return HasMany<ExamAttendance>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(ExamAttendance::class);
    }

    /**
     * @return BelongsToMany<Topic>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'exam_topics')
            ->withPivot('weight')
            ->withTimestamps();
    }

    // Scopes

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeBySubject(Builder $query, string $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByClass(Builder $query, string $classLevelId): Builder
    {
        return $query->where('class_level_id', $classLevelId);
    }

    public function scopeByClassArm(Builder $query, string $classArmId): Builder
    {
        return $query->where('class_arm_id', $classArmId);
    }
}