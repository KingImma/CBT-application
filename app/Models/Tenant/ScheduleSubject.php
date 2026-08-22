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
        'assesment_id',
        'subject_id',
        'starts_at',
        'ends_at',
        'duration_minutes'
    ];
    
    protected $casts = [
        'starts_at' => 'dateTime',
        'ends_at' => 'dateTime',
        'duration_minutes' => 'integer'
    ];

    public function assesment(): BelongsTo {
        return $this->belongsTo(Assesment::class);
    }

    public function subject(): BelongsTo {
        return $this->belongsTo(Subject::class);
    }

    public function optionalDurationMinutes(): int {
        return $this->duration_minutes
            ?? $this->assesment->duration_minutes
            ?? max(1, (int) $this->starts_at->diffInMinutes($this->ends_at));
    }
}