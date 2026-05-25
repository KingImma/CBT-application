<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamQuestionResource extends JsonResource
{
    protected bool $showAnswers = true;

    public function showAnswers(bool $show): static
    {
        $this->showAnswers = $show;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $question = $this->question;

        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'question_id' => $question->id,
            'order' => $this->order,
            'marks' => $this->marks,
            'question' => [
                'id' => $question->id,
                'type' => $question->type,
                'content' => $question->content,
                'image_url' => $question->image_url,
                'options' => $question->relationLoaded('options')
                    ? $this->formatOptions($question->options)
                    : [],
            ],
        ];
    }

    protected function formatOptions($options): array
    {
        if ($this->showAnswers) {
            return QuestionOptionResource::collection($options)->resolve(request());
        }

        return $options->map(fn ($option): array => [
            'id' => $option->id,
            'label' => $option->label,
            'content' => $option->content,
            'image_url' => $option->image_url,
            'order' => $option->order,
        ])->values()->all();
    }
}
