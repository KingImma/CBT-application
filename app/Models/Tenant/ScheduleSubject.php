<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSubject extends Model
{
    use HasUuids;

    protected $fillable = [
        'assessment_schedule_id',
        'subject_id',
        'starts_at',
        'ends_at',
        'duration_minutes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    /** @return BelongsTo<AssessmentSchedule, ScheduleSubject> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AssessmentSchedule::class, 'assessment_schedule_id');
    }

    /** @return BelongsTo<Subject, ScheduleSubject> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Effective exam duration: the slot's own value, else the assessment's
     * default, else the slot window length.
     */
    public function optionalDurationMinutes(): int
    {
        return $this->duration_minutes
            ?? $this->schedule->assessment->duration_minutes
            ?? max(1, (int) $this->starts_at->diffInMinutes($this->ends_at));
    }
}
