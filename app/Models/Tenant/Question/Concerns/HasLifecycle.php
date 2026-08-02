<?php

declare(strict_types=1);

namespace App\Models\Tenant\Question\Concerns;

use App\Models\Tenant\QuestionOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasLifecycle
{
    public function cloneTo(string $sessionId, string $termId, ?string $createdBy = null): Model
    {
        return DB::transaction(function () use ($sessionId, $termId, $createdBy) {
            $newQuestion = $this->newQuery()->create([
                'subject_id' => $this->subject_id,
                'class_level_id' => $this->class_level_id,
                'type' => $this->type,
                'content' => $this->content,
                'image_url' => $this->image_url,
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => $createdBy ?? $this->created_by,
                'academic_session_id' => $sessionId,
                'term_id' => $termId,
            ]);

            foreach ($this->options as $option) {
                QuestionOption::create([
                    'question_id' => $newQuestion->id,
                    'label' => $option->label,
                    'content' => $option->content,
                    'image_url' => $option->image_url,
                    'is_correct' => $option->is_correct,
                    'order' => $option->order,
                    'match_pair' => $option->match_pair,
                ]);
            }

            return $newQuestion;
        });
    }
}
