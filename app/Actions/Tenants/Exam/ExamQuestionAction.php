<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\SchoolSetting;
use Illuminate\Support\Facades\DB;

class ExamQuestionAction
{
    public function add(Exam $exam, string $questionId, ?string $marksOverride = null): ExamQuestion
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $question = Question::findOrFail($questionId);
        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        return DB::transaction(function () use ($exam, $question, $marksOverride, $maxOrder) {
            $examQuestion = ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'order' => $maxOrder + 1,
                'marks_override' => $marksOverride,
            ]);

            $this->recomputeTotalMarks($exam);

            return $examQuestion;
        });
    }

    public function updateMarks(Exam $exam, string $questionId, ?string $marksOverride): ExamQuestion
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be modified in draft or scheduled exams.');
        }

        return DB::transaction(function () use ($exam, $questionId, $marksOverride) {
            $examQuestion = ExamQuestion::where('exam_id', $exam->id)
                ->where('question_id', $questionId)
                ->firstOrFail();

            $examQuestion->update(['marks_override' => $marksOverride]);
            $this->recomputeTotalMarks($exam);

            return $examQuestion->fresh();
        });
    }

    public function remove(Exam $exam, string $questionId): void
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be removed from draft or scheduled exams.');
        }

        DB::transaction(function () use ($exam, $questionId) {
            $examQuestion = ExamQuestion::where('exam_id', $exam->id)
                ->where('question_id', $questionId)
                ->firstOrFail();

            $examQuestion->delete();
            $this->recomputeTotalMarks($exam);
        });
    }

    public function reorder(Exam $exam, array $orderMapping): void
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Questions can only be reordered in draft exams.');
        }

        DB::transaction(function () use ($exam, $orderMapping) {
            foreach ($orderMapping as $questionId => $newOrder) {
                ExamQuestion::where('exam_id', $exam->id)
                    ->where('question_id', $questionId)
                    ->update(['order' => $newOrder]);
            }
        });
    }

    public function autoGenerate(Exam $exam, array $rules): void
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $topicIds = $exam->topics()->pluck('topics.id')->toArray();

        if (empty($topicIds)) {
            throw new \RuntimeException('Exam must have at least one topic in the pool.');
        }

        $distribution = $exam->settings->distribution;
        $topicWeights = $exam->settings->topicWeights;

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
        $this->ensureWithinSchoolMaximum($exam, (float) $total);

        $exam->update(['total_marks' => $total]);
    }

    private function ensureWithinSchoolMaximum(Exam $exam, float $total): void
    {
        $settingKey = $exam->type === 'exam' ? 'exam_max_score' : 'assessment_max_score';
        $schoolMax = SchoolSetting::where('key', $settingKey)->value('value');

        if ($schoolMax !== null) {
            $schoolMaxFloat = (float) $schoolMax;

            if ($schoolMaxFloat <= 0) {
                throw new \RuntimeException(
                    "School maximum score for {$exam->type} is not configured correctly."
                );
            }

            if ($total > $schoolMaxFloat) {
                throw new \RuntimeException(
                    "Total marks cannot exceed school maximum of {$schoolMax} for {$exam->type}."
                );
            }
        }
    }
}
