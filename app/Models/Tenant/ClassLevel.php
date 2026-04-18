<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassLevel extends Model
{
    use HasUuids, HasFactory;

    protected $guarded = [];


    public function classArms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClassArm::class);
    }

    public function subjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_level_subject')
            ->withPivot('is_compulsory')
            ->withTimestamps();
    }

    public function students(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }
    
    public function teacherAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    protected static function booted(): void
    {
        static::deleting(function ($level) {
            $level->subjects()->detach();
            $level->classArms()->delete(); 
            $level->teacherAssignments()->delete();
        });
    }
}