<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;

class StudentQuery
{
    public function forList(): Builder
    {
        return User::role('student')
            ->withTrashed()
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'is_active')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm']);
    }

    public function search(Builder $query, ?string $search): Builder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $search = trim($search);

        return $query->where(function (Builder $q) use ($search): void {
            $q->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhereHas('studentProfile', fn ($p) => $p->where('admission_number', 'ilike', "%{$search}%"));
        });
    }

    public function filterByClass(Builder $query, ?string $classLevelId, ?string $classArmId): Builder
    {
        return $query
            ->when($classLevelId, fn ($q) => $q->whereHas('studentProfile', fn ($p) => $p->where('class_level_id', $classLevelId)))
            ->when($classArmId, fn ($q) => $q->whereHas('studentProfile', fn ($p) => $p->where('class_arm_id', $classArmId)));
    }
}
