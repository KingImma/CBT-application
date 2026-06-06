<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Data\Values\ExamAttemptSettings;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\Concerns\BelongsToSessionTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    // Scopes

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ExamAttemptStatus::InProgress->value);
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

    public function isExpired(): bool
    {
        return now()->getTimestamp() >= $this->started_at->getTimestamp() + ($this->exam->duration_minutes * 60);
    }

    public function getTimeRemainingSeconds(): int
    {
        $deadline = $this->started_at->getTimestamp() + ($this->exam->duration_minutes * 60);
        $remaining = $deadline - now()->getTimestamp();

        return max(0, $remaining);
    }
}
