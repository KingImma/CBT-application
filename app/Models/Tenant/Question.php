<?php
// - Eloquent model matching the full questions schema
// - metadata cast as array (JSONB), default_marks as decimal string-safe cast
// - scopeForBank(): chainable scope used by exam builder to filter question pool
// - Deliverable: full ORM surface for question bank queries
// - Alternative: raw DB::table queries — faster but loses relationship loading

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'metadata'             => 'array',
        'is_active'            => 'boolean',
        'default_marks'        => 'decimal:2',
        'time_estimate_seconds'=> 'integer',
        'usage_count'          => 'integer',
    ];

    public function subject(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function classLevel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function fillBlankAnswers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FillBlankAnswer::class);
    }

    // Reusable scope for exam builder filtering
    public function scopeForBank(
        Builder $q,
        string $subjectId,
        string $classLevelId
    ): Builder {
        return $q->where('subject_id', $subjectId)
                 ->where('class_level_id', $classLevelId)
                 ->where('is_active', true);
    }
}