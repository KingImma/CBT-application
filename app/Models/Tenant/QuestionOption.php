<?php

// • What: QuestionOption Eloquent model
// • Does: Represents a single answer choice tied to a question
// • Why: Separated from Question to support variable option counts (2 for true/false,
//        4-5 for MCQ) without sparse columns. `is_correct` + `order` drive both
//        grading logic and display ordering.
// • Delivers: Queryable model with question relationship and correct-answer scope
// • Alternative: JSON column on Question for options — simpler schema but kills
//                indexed queries on is_correct and breaks per-option CRUD

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id',
        'content',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order'      => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }
}