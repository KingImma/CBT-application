<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Attempts;

use App\Data\Values\ExamAttemptSettings;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Collection;
use App\Actions\Tenants\Exam\Attempts\ExamAttemptGuards;

final class GetExamQuestions
{
    public function execute(ExamAttempt $attempt): array
    {
        $questions = ExamQuestion::where('exam_id', $attempt->exam_id)
            ->with('question.options')
            ->orderBy('order')
            ->get();

        $questionIds = $questions->pluck('question.id')->toArray();

        if (! $attempt->exam->settings->getRandomizeQuestions()) {
            return $this->result($questions, $questionIds);
        }

        return $this->randomizedResult($attempt, $questions, $questionIds);
    }

    private function randomizedResult(ExamAttempt $attempt, Collection $questions, array $questionIds): array
    {
        $savedOrder = $attempt->settings?->getQuestionOrder();

        // Order already persisted for this attempt — honour it (reconnect path)
        if (! empty($savedOrder)) {
            return $this->result($this->sortByOrder($questions, $savedOrder), $savedOrder);
        }

        // First load — shuffle and persist so order is stable across reconnects
        shuffle($questionIds);

        $attempt->settings = new ExamAttemptSettings(questionOrder: $questionIds);
        $attempt->save();

        return $this->result($this->sortByOrder($questions, $questionIds), $questionIds);
    }

    private function sortByOrder(Collection $questions, array $order): Collection
    {
        return $questions
            ->sortBy(fn (ExamQuestion $eq) => array_search($eq->question->id, $order, true))
            ->values();
    }

    private function result(Collection $questions, array $order): array
    {
        return ['questions' => $questions, 'order' => $order];
    }
}
