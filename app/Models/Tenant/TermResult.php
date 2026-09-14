<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id',
        'subject_id',
        'term_id',
        'class_level_id',
        'ca_score',
        'exam_score',
        'total_score',
        'grade',
        'rank_in_class',
        'rank_in_level',
        'teacher_remark',
    ];

    protected $casts = [
        'ca_score' => 'decimal:2',
        'exam_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'rank_in_class' => 'integer',
        'rank_in_level' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }
}
