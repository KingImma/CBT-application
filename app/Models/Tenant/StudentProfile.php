<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Tenant\Student\Concerns\RecalculatesStudentExamResults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    use HasFactory, HasUuids, RecalculatesStudentExamResults;

    protected $fillable = [
        'user_id',
        'class_level_id',
        'class_arm_id',
        'admission_number',
        'date_of_birth',
        'gender',
        'guardian_email',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    /**
     * @return BelongsTo<User,StudentProfile>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ClassLevel,StudentProfile>
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * @return BelongsTo<ClassArm,StudentProfile>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }

    public function latestCompletedExamAttempt(): BelongsTo
    {
        return $this->belongsTo(
            ExamAttempt::class,
            'user_id',
            'student_id',
        )->ofMany(
            [
                'submitted_at' => 'max',
                'id' => 'max',
            ],
            fn ($query) => $query->completed(),
        );
    }

    public static function generateAdmissionNumber(): string
    {
        $year = date('Y');

        $lastProfile = static::lockForUpdate()
            ->where('admission_number', 'like', "STU/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();

        $nextCount = 1;

        if (
            $lastProfile &&
            preg_match('/(\d+)$/', $lastProfile->admission_number, $matches)
        ) {
            $nextCount = (int) $matches[1] + 1;
        }

        return "STU/{$year}/".
            str_pad((string) $nextCount, 4, '0', STR_PAD_LEFT);
    }
}
