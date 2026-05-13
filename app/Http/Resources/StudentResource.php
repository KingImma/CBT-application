<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->whenLoaded('studentProfile');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'profile' => $this->when($this->relationLoaded('studentProfile'), fn () => [
                'admission_number' => $profile?->admission_number,
                'gender' => $profile?->gender,
                'date_of_birth' => $profile?->date_of_birth,
                'class_level' => new ClassLevelResource($profile?->classLevel),
                'class_arm' => new ClassArmResource($profile?->classArm),
            ]),
        ];
    }
}
