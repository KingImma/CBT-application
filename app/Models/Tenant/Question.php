<?php

// • What: Question Eloquent model
// • Does: Represents a question in the bank, typed (mcq/multi_select/true_false/short_answer)
// • Why: Enum-based type enforces valid values at DB level; difficulty + marks support
//        weighted, adaptive exam construction. SoftDeletes preserves exam history integrity.
// • Delivers: Queryable model with topic + options relationships, typed scopes
// • Alternative: Polymorphic questions table per type — cleaner single-responsibility
//                but massive migration overhead for MVP; enum is the right MVP call

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'topic_id',
        'content',
        'type',
        'difficulty',
        'marks',
        'explanation',
    ];

    protected $casts = [
        'marks' => 'integer',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order');
    }

    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}