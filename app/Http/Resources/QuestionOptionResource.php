<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'content' => $this->content,
            'image_url' => $this->image_url,
            'is_correct' => $this->is_correct,
            'order' => $this->order,
            'match_pair' => $this->match_pair,
        ];
    }
}
