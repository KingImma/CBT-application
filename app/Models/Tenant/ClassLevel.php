<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Support\NormalizeName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassLevel extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function classArms(): HasMany
    {
        return $this->hasMany(ClassArm::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_level_subject')
            ->withPivot('is_compulsory')
            ->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['normalized_name'] = NormalizeName::canonical($value);
    }

    public function canDelete(): bool
    {
        return $this->students()->count() === 0
            && $this->exams()->count() === 0;
    }

    public function hasDependencies(): bool
    {
        return $this->students()->count() > 0
            || $this->exams()->count() > 0
            || $this->classArms()->count() > 0;
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    protected static function booted(): void
    {
        static::deleting(function ($level) {
            if ($level->isForceDeleting()) {
                $level->subjects()->detach();
                $level->classArms()->forceDelete();
                $level->teacherAssignments()->forceDelete();
            }
        });
    }
}
