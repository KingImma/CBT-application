<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Support;

use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "what classes and subjects does this teacher hold".
 *
 * Four independent assignment sources feed it:
 *  - teacher_profiles.class_level_id          (level overseer)
 *  - teacher_level_assignments                (level-wide assignment)
 *  - teacher_subject_assignments              (subject on a level, or on one arm)
 *  - class_arms.assigned_teacher_id           (class / form teacher on one arm)
 *
 * A subject assignment with a null class_arm_id is level-wide; with an arm it
 * is scoped to that arm.
 */
final class TeacherAssignmentScope
{
    public function __construct(private readonly User $teacher)
    {
    }

    /**
     * Levels the teacher covers in full (every arm in the level).
     *
     * @return array<int, string>
     */
    public function levelWideLevelIds(): array
    {
        return collect([$this->teacher->teacherProfile?->class_level_id])
            ->filter()
            ->merge($this->teacher->assignedLevels()->pluck('class_level_id'))
            ->merge(
                $this->teacher->teacherAssignments()
                    ->whereNull('class_arm_id')
                    ->pluck('class_level_id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Specific class arms the teacher holds.
     *
     * @return array<int, string>
     */
    public function classArmIds(): array
    {
        return $this->teacher->assignedClasses()
            ->pluck('id')
            ->merge(
                $this->teacher->teacherAssignments()
                    ->whereNotNull('class_arm_id')
                    ->pluck('class_arm_id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every level the teacher can see, including levels reached through an arm.
     *
     * @return array<int, string>
     */
    public function classLevelIds(): array
    {
        $armIds = $this->classArmIds();

        $armLevels = $armIds === []
            ? collect()
            : ClassArm::whereIn('id', $armIds)->pluck('class_level_id');

        return collect($this->levelWideLevelIds())
            ->merge($armLevels)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, ClassLevel> */
    public function classLevels(): Collection
    {
        return ClassLevel::whereIn('id', $this->classLevelIds())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /** @return Collection<int, ClassArm> */
    public function classArms(): Collection
    {
        return ClassArm::whereIn('id', $this->classArmIds())
            ->with('classLevel:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'class_level_id']);
    }

    /**
     * @return array<int, string>
     */
    public function subjectIds(): array
    {
        return $this->teacher->teacherAssignments()
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Subject ids the teacher holds, optionally narrowed to a schedule's class
     * level / arm. A level-wide assignment (class_arm_id null) matches any arm.
     *
     * @return array<int, string>
     */
    public function subjectIdsFor(?string $classLevelId = null, ?string $classArmId = null): array
    {
        return $this->teacher->teacherAssignments()
            ->when($classLevelId, fn ($q) => $q->where('class_level_id', $classLevelId))
            ->when(
                $classArmId,
                fn ($q) => $q->where(
                    fn ($q) => $q->whereNull('class_arm_id')->orWhere('class_arm_id', $classArmId)
                )
            )
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();
    }

    public function hasAnyAssignment(): bool
    {
        return $this->levelWideLevelIds() !== [] || $this->classArmIds() !== [];
    }

    /**
     * Constrain a student (User) query to the classes this teacher holds.
     *
     * Level-wide levels match every arm in that level; arm ids match only that
     * arm. A teacher with no assignment sees nothing.
     */
    public function scopeStudents(Builder $query): Builder
    {
        $levelIds = $this->levelWideLevelIds();
        $armIds = $this->classArmIds();

        if ($levelIds === [] && $armIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('studentProfile', function (Builder $profile) use ($levelIds, $armIds): void {
            $profile->where(function (Builder $q) use ($levelIds, $armIds): void {
                if ($levelIds !== []) {
                    $q->whereIn('class_level_id', $levelIds);

                    if ($armIds !== []) {
                        $q->orWhereIn('class_arm_id', $armIds);
                    }

                    return;
                }

                $q->whereIn('class_arm_id', $armIds);
            });
        });
    }

    /**
     * Constrain a Subject query to the teacher's subject assignments.
     */
    public function scopeSubjects(
        Builder $query,
        ?string $classLevelId = null,
        ?string $classArmId = null,
    ): Builder {
        return $query->whereIn('id', $this->subjectIdsFor($classLevelId, $classArmId));
    }
}
