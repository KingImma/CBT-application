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

class QuestionOption extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['is_correct' => 'boolean'];

    public function question(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}