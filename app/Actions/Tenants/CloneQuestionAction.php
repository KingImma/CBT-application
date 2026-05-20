<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Tenant\FillBlankAnswer;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use Illuminate\Support\Facades\DB;

class CloneQuestionAction
{
    /**
     * @param  array<string,string>  $topicMap  old topic ID => new topic ID
     */
    public function cloneToTerm(
        string $sourceSessionId,
        string $sourceTermId,
        string $targetSessionId,
        string $targetTermId,
        string $subjectId,
        string $classLevelId,
        array $topicMap = [],
        ?string $createdBy = null,
    ): int {
        $sourceQuestions = Question::forTerm($sourceSessionId, $sourceTermId)
            ->where('subject_id', $subjectId)
            ->where('class_level_id', $classLevelId)
            ->with(['options', 'fillBlankAnswers'])
            ->get();

        if ($sourceQuestions->isEmpty()) {
            return 0;
        }

        $cloned = 0;

        DB::transaction(function () use ($sourceQuestions, $targetSessionId, $targetTermId, $topicMap, $createdBy, &$cloned) {
            foreach ($sourceQuestions as $question) {
                $newQuestion = Question::create([
                    'subject_id' => $question->subject_id,
                    'class_level_id' => $question->class_level_id,
                    'topic_id' => $topicMap[$question->topic_id] ?? $question->topic_id,
                    'type' => $question->type,
                    'content' => $question->content,
                    'explanation' => $question->explanation,
                    'default_marks' => $question->default_marks,
                    'time_estimate_seconds' => $question->time_estimate_seconds,
                    'image_url' => $question->image_url,
                    'metadata' => $question->metadata,
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
                        'match_pair' => $option->match_pair,
                    ]);
                }

                foreach ($question->fillBlankAnswers as $answer) {
                    FillBlankAnswer::create([
                        'question_id' => $newQuestion->id,
                        'answer_text' => $answer->answer_text,
                        'is_primary' => $answer->is_primary,
                    ]);
                }

                $cloned++;
            }
        });

        return $cloned;
    }
}
