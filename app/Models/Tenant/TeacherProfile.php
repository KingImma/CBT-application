<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'qualification',
        'staff_id',
        'class_level_id',
        'date_of_birth',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    // Note: The subjectAssignments relationship was removed from here.

    public static function generateStaffId(): string
    {
        $year = date('Y');

        $last = static::lockForUpdate()
            ->where('staff_id', 'like', "TCH/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();

        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last->staff_id, $m)) {
            $next = (int) $m[1] + 1;
        }

        return "TCH/{$year}/".str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
