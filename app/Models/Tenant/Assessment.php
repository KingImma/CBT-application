<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Assessment definition — school-wide ("End of Term Exam"), stable across
 * sessions and bound to no class. Class levels attach to the schedules that
 * occur under this definition, so every staff member can read definitions;
 * writes stay admin-only (AssessmentPolicy).
 */
class Assessment extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes;

    protected $fillable = [
        'title',
        'created_by',
        'total_marks',
        'duration_minutes',
        'description',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    /** @return HasMany<AssessmentSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(AssessmentSchedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
