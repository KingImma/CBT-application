<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'class_level' => new ClassLevelResource($this->whenLoaded('classLevel')),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'assigned_teacher' => $this->whenLoaded('assignedTeacher', fn () => [
                'id' => $this->assignedTeacher?->id,
                'first_name' => $this->assignedTeacher?->first_name,
                'last_name' => $this->assignedTeacher?->last_name,
            ]),
        ];
    }
}
