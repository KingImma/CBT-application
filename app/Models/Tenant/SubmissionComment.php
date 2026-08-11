<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionComment extends Model
{
    use HasUuids;

    protected $fillable = [
        'submission_id',
        'author_id',
        'parent_id',
        'body',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Submission, SubmissionComment> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<User, SubmissionComment> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<SubmissionComment, SubmissionComment> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SubmissionComment::class, 'parent_id');
    }

    /** @return HasMany<SubmissionComment, SubmissionComment> */
    public function replies(): HasMany
    {
        return $this->hasMany(SubmissionComment::class, 'parent_id');
    }
}
