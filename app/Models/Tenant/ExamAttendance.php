<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttendance extends Model
{
    use HasUuids;

    protected $table = 'exam_attendance';

    protected $guarded = ['id'];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Exam,ExamAttendance>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<User,ExamAttendance>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return BelongsTo<User,ExamAttendance>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
