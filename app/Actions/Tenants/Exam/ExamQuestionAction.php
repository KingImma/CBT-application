<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\SchoolSetting;
use Illuminate\Support\Facades\DB;

class ExamQuestionAction
{
    public function add(Exam $exam, string $questionId, ?string $marksOverride = null, ?string $userId = null): ExamQuestion
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $question = Question::findOrFail($questionId);

        if ($userId !== null && $question->created_by !== $userId) {
            throw new \RuntimeException('Question does not belong to your question bank.');
        }

        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        return DB::transaction(function () use ($exam, $question, $marksOverride, $maxOrder) {
            $marks = $marksOverride ?? $question->default_marks;
            $examQuestion = ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'order' => $maxOrder + 1,
                'marks' => $marks,
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

            $examQuestion->update(['marks' => $marksOverride ?? $examQuestion->question->default_marks]);
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

    public function randomizeQuestions(Exam $exam, int $count): void
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $availableCount = Question::where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->where('is_active', true)
            ->count();

        if ($availableCount < $count) {
            throw new \RuntimeException(
                "Only {$availableCount} questions available, but {$count} requested."
            );
        }

        DB::transaction(function () use ($exam, $count) {
            $questions = Question::where('subject_id', $exam->subject_id)
                ->where('class_level_id', $exam->class_level_id)
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit($count)
                ->get();

            $maxOrder = $exam->examQuestions()->max('order') ?? 0;

            foreach ($questions as $index => $question) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'question_id' => $question->id,
                    'order' => $maxOrder + $index + 1,
                    'marks' => $question->default_marks,
                ]);
            }

            $this->recomputeTotalMarks($exam);
        });
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
