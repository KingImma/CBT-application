<?php

// • What: Topic Eloquent model
// • Does: Represents a topic within a subject, parent of questions
// • Why: Encapsulates the topic → subject → questions hierarchy.
//        SoftDeletes lets admins deactivate topics without losing question history.
// • Delivers: Queryable model with subject and questions relationships + ordered scope
// • Alternative: No soft deletes + archive boolean — simpler but loses Eloquent's
//                withTrashed/onlyTrashed scoping convenience

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Topic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject_id',
        'name',
        'description',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }
}