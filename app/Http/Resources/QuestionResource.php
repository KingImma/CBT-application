<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            'default_marks' => $this->default_marks,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'class_level' => new ClassLevelResource($this->whenLoaded('classLevel')),
            'created_by' => $this->creator?->id,
        ];
    }
}
