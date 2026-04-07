<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\TeacherProfile;

/**
 * Tenant-scoped user. Completely separate from SuperAdmin.
 * Lives in the tenant database, authenticated via tenant guard.
 * Roles: school_admin, teacher, student.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasUuids, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password'          => 'hashed',
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
    ];
    /**
     * @return HasOne<StudentProfile,User>
     */
    public function studentProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }
    /**
     * @return HasOne<TeacherProfile,User>
     */
    public function teacherProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }
}