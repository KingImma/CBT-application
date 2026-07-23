<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'exam_attempt_id',
        'student_id',
        'exam_id',
        'subject_id',
        'term_id',
        'academic_session_id',
        'total_score',
        'percentage_score',
        'grade',
        'is_theory_graded',
        'rank_in_class',
        'passed',
        'graded_at',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'is_theory_graded' => 'boolean',
        'passed' => 'boolean',
        'graded_at' => 'datetime',
    ];

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
