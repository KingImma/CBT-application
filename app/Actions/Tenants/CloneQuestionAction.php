<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use Illuminate\Support\Facades\DB;

class CloneQuestionAction
{
    public function cloneToTerm(
        string $sourceSessionId,
        string $sourceTermId,
        string $targetSessionId,
        string $targetTermId,
        string $subjectId,
        string $classLevelId,
        ?string $createdBy = null,
    ): int {
        $sourceQuestions = Question::forTerm($sourceSessionId, $sourceTermId)
            ->where('subject_id', $subjectId)
            ->where('class_level_id', $classLevelId)
            ->with('options')
            ->get();

        if ($sourceQuestions->isEmpty()) {
            return 0;
        }

        $cloned = 0;

        DB::transaction(function () use ($sourceQuestions, $targetSessionId, $targetTermId, $createdBy, &$cloned) {
            foreach ($sourceQuestions as $question) {
                $newQuestion = Question::create([
                    'subject_id' => $question->subject_id,
                    'class_level_id' => $question->class_level_id,
                    'type' => $question->type,
                    'content' => $question->content,
                    'default_marks' => $question->default_marks,
                    'image_url' => $question->image_url,
                    'is_active' => true,
                    'usage_count' => 0,
                    'created_by' => $createdBy ?? $question->created_by,
                    'academic_session_id' => $targetSessionId,
                    'term_id' => $targetTermId,
                ]);

                foreach ($question->options as $option) {
                    QuestionOption::create([
                        'question_id' => $newQuestion->id,
                        'label' => $option->label,
                        'content' => $option->content,
                        'image_url' => $option->image_url,
                        'is_correct' => $option->is_correct,
                        'order' => $option->order,
                    ]);
                }

                $cloned++;
            }
        });

        return $cloned;
    }
}
