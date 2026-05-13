<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherProfile extends Model
{
    use HasUuids, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        "date_of_birth" => "date",
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // Note: The subjectAssignments relationship was removed from here.
}