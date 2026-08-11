<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionQuestionOption extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'submission_question_id',
        'label',
        'content',
        'image_url',
        'is_correct',
        'order',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'order' => 'integer',
    ];

    /** @return BelongsTo<SubmissionQuestion, SubmissionQuestionOption> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SubmissionQuestion::class, 'submission_question_id');
    }
}
