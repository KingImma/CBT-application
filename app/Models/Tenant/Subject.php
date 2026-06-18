<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Support\NormalizeName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['normalized_name'] = NormalizeName::canonical($value);
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function classLevels(): BelongsToMany
    {
        return $this->belongsToMany(ClassLevel::class, 'class_level_subject')
            ->withPivot('is_compulsory')
            ->withTimestamps();
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }
}
