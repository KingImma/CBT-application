<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\SubmissionQuestionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionQuestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'submission_id',
        'type',
        'order',
        'content',
        'explanation',
        'marks',
        'image_url',
    ];

    protected $casts = [
        'type' => SubmissionQuestionType::class,
        'order' => 'integer',
        'marks' => 'float',
    ];

    /** @return BelongsTo<Submission, SubmissionQuestion> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return HasMany<SubmissionQuestionOption, SubmissionQuestion> */
    public function options(): HasMany
    {
        return $this->hasMany(SubmissionQuestionOption::class)->orderBy('order');
    }
}
