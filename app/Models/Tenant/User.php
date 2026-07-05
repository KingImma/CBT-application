<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Tenant\User\Concerns\HasBroadcasting;
use App\Models\Tenant\User\Concerns\HasLifecycle;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens,
        HasBroadcasting,
        HasFactory,
        HasLifecycle,
        HasRoles,
        HasUuids,
        Notifiable,
        SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'is_active',
        'role',
    ];

    protected $guard_name = 'tenant';

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Generic role checker using Spatie Permissions.
     */
    public function isRole(string $role): bool
    {
        if (method_exists($this, 'hasRole')) {
            return $this->hasRole(strtolower($role));
        }

        return false;
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /**
     * Maps to the TeacherSubjectAssignment bridge table.
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'user_id');
    }

    public function assignedClasses(): HasMany
    {
        return $this->hasMany(ClassArm::class, 'assigned_teacher_id');
    }

    public function assignedLevels(): HasMany
    {
        return $this->hasMany(TeacherLevelAssignment::class, 'user_id');
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return "tenant.{$this->tenant_id}.users.{$this->id}";
    }
}
