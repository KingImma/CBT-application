<?php

declare(strict_types=1);

namespace App\Models\Tenant;

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
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $with = ['teacherProfile.classLevel'];

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

    /**
     * Scope to filter users by status (active, inactive, archived, all).
     */
    public function scopeWithStatus($query, string $status): void
    {
        switch ($status) {
            case 'archived':
                $query->onlyTrashed();
                break;
            case 'inactive':
                $query->where('is_active', false);
                break;
            case 'active':
                $query->where('is_active', true);
                break;
            case 'all':
                $query->withTrashed();
                break;
        }
    }

    /**
     * Scope to search users by multiple fields.
     */
    public function scopeSearch($query, ?string $search, array $searchFields = ['first_name', 'last_name', 'email']): void
    {
        if (! $search) {
            return;
        }

        $query->where(function ($q) use ($search, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'ilike', "%{$search}%");
            }
        });
    }
}
