<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'student_profile' => $this->whenLoaded('studentProfile', fn () => [
                'admission_number' => $this->studentProfile?->admission_number,
                'gender' => $this->studentProfile?->gender,
                'date_of_birth' => $this->studentProfile?->date_of_birth,
                'class_level' => new ClassLevelResource($this->studentProfile?->classLevel),
                'class_arm' => new ClassArmResource($this->studentProfile?->classArm),
            ]),
        ];
    }
}
