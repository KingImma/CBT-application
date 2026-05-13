<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\BelongsToSessionTerm;
use App\Values\ExamAttemptSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ExamAttempt extends Model
{
    use BelongsToSessionTerm, HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'time_spent_seconds' => 'integer',
        'total_score' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'objective_score' => 'decimal:2',
        'theory_score' => 'decimal:2',
        'is_theory_graded' => 'boolean',
        'suspicious_events' => 'array',
        'settings' => ExamAttemptSettings::class,
    ];

    /**
     * @return BelongsTo<Exam,ExamAttempt>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<User,ExamAttempt>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return HasMany<ExamAnswer>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<User,ExamAttempt>
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // Scopes

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereIn('status', ['submitted', 'timed_out']);
    }

    public function scopeNeedsGrading(Builder $query): Builder
    {
        return $query->where('status', 'grading');
    }

    public function scopeForExam(Builder $query, string $examId): Builder
    {
        return $query->where('exam_id', $examId);
    }

    public function scopeForStudent(Builder $query, string $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    // Helper

    public function logSuspiciousEvent(string $type, array $metadata = []): void
    {
        $events = $this->suspicious_events ?? [];
        $events[] = [
            'type' => $type,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];
        $this->suspicious_events = $events;
        $this->save();
    }

    public function getTimeRemainingSeconds(): int
    {
        $exam = $this->exam;
        $attemptDeadline = $this->started_at->copy()->addMinutes($exam->duration_minutes);
        $sessionDeadline = null;

        if ($exam->session_started_at) {
            $sessionDeadline = $exam->session_started_at->copy()->addMinutes($exam->session_duration_minutes);
        }

        $deadline = $sessionDeadline ? min($attemptDeadline, $sessionDeadline) : $attemptDeadline;
        $remaining = $deadline->getTimestamp() - now()->getTimestamp();

        return max(0, $remaining);
    }
}
