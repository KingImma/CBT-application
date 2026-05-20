<?php

// - Eloquent model matching the topics schema with self-referencing parent
// - children() + parent() cover the sub-topic hierarchy
// - Chosen: recursive relationship kept simple with two HasMany/BelongsTo methods
// - Deliverable: full topic tree navigation from any node
// - Alternative: nested set (kalnoy/nestedset) — better for deep trees, overkill for 2 levels

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Tenant\Concerns\BelongsToSessionTerm;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    use BelongsToSessionTerm, HasUuids;

    protected $guarded = ['id'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Topic::class, 'parent_id')->orderBy('order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
