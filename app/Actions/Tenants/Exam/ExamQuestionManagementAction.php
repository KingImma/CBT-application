<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\SchoolSetting;
use Illuminate\Support\Facades\DB;

class ExamQuestionManagementAction
{
    public function add(Exam $exam, string $questionId, ?string $marksOverride = null, ?string $userId = null): ExamQuestion
    {
        $this->ensureDraftOrScheduled($exam, 'added');

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
        $this->ensureDraftOrScheduled($exam, 'modified');

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
        $this->ensureDraftOrScheduled($exam, 'removed');

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
        if ($exam->status !== ExamStatus::Draft) {
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

    private function ensureDraftOrScheduled(Exam $exam, string $action): void
    {
        if (! in_array($exam->status, [ExamStatus::Draft, ExamStatus::Scheduled])) {
            throw new \RuntimeException("Questions can only be {$action} to draft or scheduled exams.");
        }
    }

    public function recomputeTotalMarks(Exam $exam): void
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
