<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'class_level' => new ClassLevelResource($this->whenLoaded('classLevel')),
            'class_arm' => new ClassArmResource($this->whenLoaded('classArm')),
            'term' => new TermResource($this->whenLoaded('term')),
            'total_marks' => (float) $this->total_marks,
            'pass_mark' => $this->pass_mark === null ? null : (float) $this->pass_mark,
            'duration_minutes' => $this->duration_minutes,
            'max_attempts' => $this->max_attempts,
            'question_count' => $this->exam_questions_count ?? $this->whenLoaded('examQuestions', fn () => $this->examQuestions->count()),
            'scheduled_start' => $this->scheduled_start,
            'scheduled_end' => $this->scheduled_end,
            'instructions' => $this->instructions,
            'created_by' => $this->creator?->id,
        ];
    }
}
