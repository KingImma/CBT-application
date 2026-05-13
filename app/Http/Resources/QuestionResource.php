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
            'explanation' => $this->explanation,
            'default_marks' => $this->default_marks,
            'time_estimate_seconds' => $this->time_estimate_seconds,
            'image_url' => $this->image_url,
            'metadata' => $this->metadata,
            'is_active' => $this->is_active,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
            'fill_blank_answers' => $this->whenLoaded('fillBlankAnswers'),
            'topic' => new TopicResource($this->whenLoaded('topic')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'class_level' => new ClassLevelResource($this->whenLoaded('classLevel')),
            'created_by' => $this->creator?->id,
        ];
    }
}
