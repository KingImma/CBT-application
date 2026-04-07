<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class StudentProfile extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $guarded = [];
    
    protected $casts = [
        'date_of_birth' => 'date',
    ];
    /**
     * @return BelongsTo<User,StudentProfile>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    /**
     * @return BelongsTo<ClassLevel,StudentProfile>
     */
    public function classLevel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
       {
           return $this->belongsTo(ClassLevel::class);
       }
    /**
     * @return BelongsTo<ClassArm,StudentProfile>
     */
    public function classArm(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }
}
