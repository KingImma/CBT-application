<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'teacher_profile' => $this->whenLoaded('teacherProfile', fn () => [
                'staff_id' => $this->teacherProfile?->staff_id,
                'qualification' => $this->teacherProfile?->qualification,
                'department' => $this->teacherProfile?->department,
                'class_level' => new ClassLevelResource($this->teacherProfile?->classLevel),
            ]),
            'assigned_classes' => ClassArmResource::collection($this->whenLoaded('assignedClasses')),
            'assigned_subjects' => $this->whenLoaded('teacherAssignments', function () {
                return $this->teacherAssignments->map(fn ($assignment) => [
                    'subject' => new SubjectResource($assignment->subject),
                    'class_level' => new ClassLevelResource($assignment->classLevel),
                ]);
            }),
        ];
    }
}
