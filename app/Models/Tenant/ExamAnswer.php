<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'selected_option_ids' => 'array',
        'ordering_answer' => 'array',
        'matching_answer' => 'array',
        'is_correct' => 'boolean',
        'marks_awarded' => 'decimal:2',
        'is_flagged' => 'boolean',
        'time_spent_seconds' => 'integer',
        'answered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ExamAttempt,ExamAnswer>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<Question,ExamAnswer>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User,ExamAnswer>
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
