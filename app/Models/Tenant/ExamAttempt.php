<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Data\Values\ExamAttemptSettings;
use App\Enums\ExamAttemptStatus;
use App\Enums\SuspiciousEventType;
use App\Models\Tenant\Concerns\BelongsToSessionTerm;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use BelongsToSessionTerm, HasUuids;

    private const SECONDS_PER_MINUTE = 60;

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

    /**
     * Append a suspicious event to the attempt's event log.
     *
     * Does NOT persist — caller is responsible for calling save().
     * This keeps the domain method pure and side-effect-free.
     */
    public function logSuspiciousEvent(SuspiciousEventType $type, array $metadata = []): void
    {
        $events = $this->suspicious_events ?? [];
        $events[] = [
            'type' => $type->value,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];
        $this->suspicious_events = $events;
    }

    /**
     * Check if this attempt has exceeded its time limit.
     *
     * @param  DateTimeInterface|null  $now  Injectable clock for testability. Defaults to now().
     */
    public function isExpired(?DateTimeInterface $now = null): bool
    {
        $currentTimestamp = ($now ?? now())->getTimestamp();

        return $currentTimestamp >= $this->getDeadlineTimestamp();
    }

    /**
     * Get the number of seconds remaining before the attempt deadline.
     *
     * @param  DateTimeInterface|null  $now  Injectable clock for testability. Defaults to now().
     */
    public function getTimeRemainingSeconds(?DateTimeInterface $now = null): int
    {
        $currentTimestamp = ($now ?? now())->getTimestamp();
        $remaining = $this->getDeadlineTimestamp() - $currentTimestamp;

        return max(0, $remaining);
    }

    /**
     * Calculate the absolute deadline timestamp for this attempt.
     */
    private function getDeadlineTimestamp(): int
    {
        return $this->started_at->getTimestamp()
            + ($this->exam->duration_minutes * self::SECONDS_PER_MINUTE);
    }
}
