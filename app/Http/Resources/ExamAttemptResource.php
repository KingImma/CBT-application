<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'attempt_number' => $this->attempt_number,
            'total_score' => $this->total_score === null ? null : (float) $this->total_score,
            'percentage_score' => $this->percentage_score === null ? null : (float) $this->percentage_score,
            'objective_score' => $this->objective_score === null ? null : (float) $this->objective_score,
            'theory_score' => $this->theory_score === null ? null : (float) $this->theory_score,
            'started_at' => $this->started_at,
            'submitted_at' => $this->submitted_at,
            'time_spent_seconds' => $this->time_spent_seconds,
            'student' => $this->when($this->relationLoaded('student'), fn () => [
                'id' => $this->student->id,
                'first_name' => $this->student->first_name,
                'last_name' => $this->student->last_name,
            ]),
        ];
    }
}
