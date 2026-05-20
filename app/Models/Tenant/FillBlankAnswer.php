<?php

// - Eloquent model for fill_blank_answers
// - is_primary flags the canonical answer shown in result feedback
// - Chosen: is_primary field allows grading engine to show one "best" answer
// - Deliverable: multiple accepted spellings/forms per blank
// - Alternative: is_primary as array index 0 in JSON — not queryable

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FillBlankAnswer extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = ['is_primary' => 'boolean'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
