<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\BelongsToSessionTerm;
use App\Models\Tenant\Question\Concerns\HasLifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Subject|null $subject
 * @property-read ClassLevel|null $classLevel
 */
class Question extends Model
{
    use BelongsToSessionTerm, HasLifecycle, HasUuids, SoftDeletes;

    protected $fillable = [
        'subject_id',
        'class_level_id',
        'type',
        'content',
        'content_format',
        'default_marks',
        'image_url',
        'is_active',
        'academic_session_id',
        'term_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_marks' => 'decimal:2',
        'usage_count' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

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
