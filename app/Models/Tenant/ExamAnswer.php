<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Tenant\ExamAnswer\Concerns\HasBroadcasting;
use App\Models\Tenant\ExamAnswer\Concerns\HasScoring;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    use HasBroadcasting, HasScoring, HasUuids;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_ids',
        'ordering_answer',
        'matching_answer',
        'text_answer',
        'is_correct',
        'marks_awarded',
        'time_spent_seconds',
        'is_flagged',
        'answered_at',
    ];

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
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User,ExamAnswer>
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
