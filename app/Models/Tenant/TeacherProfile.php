<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherProfile extends Model
{
    use HasUuids, HasFactory;

    protected $guarded = [];

    protected $casts = [
        "date_of_birth" => "date",
    ];

    /**
     * @return BelongsTo<User,TeacherProfile>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * @return HasMany<TeacherSubjectAssignment,TeacherProfile>
     */
    public function subjectAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }
}
