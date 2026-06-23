<?php

// - Eloquent model for question_options — covers MCQ, T/F, Matching, Ordering
// - match_pair used only for Matching type questions
// - Chosen: single model handles all option variants via nullable fields
// - Deliverable: option CRUD with label, content, image, match pair
// - Alternative: trait-based type variants — over-engineered for 4 types

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use HasUuids;

    protected $fillable = [
        'question_id',
        'label',
        'content',
        'image_url',
        'is_correct',
        'order',
        'match_pair',
    ];

    protected $appends = [
        'case_sensitive',
    ];

    protected $casts = ['is_correct' => 'boolean'];

    public function getCaseSensitiveAttribute(): ?bool
    {
        if ($this->match_pair === null) {
            return null;
        }

        $matchPair = json_decode($this->match_pair, true);

        if (! is_array($matchPair) || ! array_key_exists('case_sensitive', $matchPair)) {
            return null;
        }

        return (bool) $matchPair['case_sensitive'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
