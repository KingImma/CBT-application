<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Topic;
use Illuminate\Support\Facades\DB;

class AutoGenerateQuestionsAction
{
    public function execute(Exam $exam, array $rules): void
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $topicIds = $exam->topics()->pluck('topics.id')->toArray();

        if (empty($topicIds)) {
            throw new \RuntimeException('Exam must have at least one topic in the pool.');
        }

        $settings = $exam->settings ?? [];
        $distribution = $settings['distribution'] ?? 'pooled';
        $topicWeights = $settings['topic_weights'] ?? [];

        DB::transaction(function () use ($exam, $rules, $topicIds, $distribution, $topicWeights) {
            foreach ($rules as $rule) {
                $this->processRule($exam, $rule, $topicIds, $distribution, $topicWeights);
            }

            $this->recomputeTotalMarks($exam);
        });
    }

    private function processRule(Exam $exam, array $rule, array $topicIds, string $distribution, array $topicWeights): void
    {
        $query = Question::whereIn('topic_id', $topicIds)
            ->where('is_active', true);

        if (! empty($rule['type'])) {
            $query->where('type', $rule['type']);
        }

        if (! empty($rule['difficulty'])) {
            $query->where('difficulty', $rule['difficulty']);
        }

        $availableCount = $query->count();
        $requestedCount = $rule['count'];

        if ($availableCount < $requestedCount) {
            throw new \RuntimeException(
                "Only {$availableCount} questions found for the given filters, but {$requestedCount} requested."
            );
        }

        $questions = $query->inRandomOrder()->limit($requestedCount)->get();
        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        foreach ($questions as $index => $question) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'order' => $maxOrder + $index + 1,
                'marks_override' => null,
            ]);
        }
    }

    private function recomputeTotalMarks(Exam $exam): void
    {
        $total = $exam->examQuestions()->get()->sum(fn ($eq) => $eq->getEffectiveMarks());
        $exam->update(['total_marks' => $total]);
    }
}
