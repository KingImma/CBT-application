<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    use HasUuids;

    protected $fillable = [
        'exam_id',
        'question_id',
        'order',
        'marks',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<Exam,ExamQuestion>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<Question,ExamQuestion>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function getEffectiveMarks(): string
    {
        return (string) ($this->marks ?? $this->question->default_marks);
    }
}
