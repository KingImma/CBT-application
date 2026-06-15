<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Exceptions\Business\ExamQuestionBelongsToDifferentExamException;
use App\Exceptions\Business\ExamQuestionNotFoundException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\SchoolSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExamQuestionManagementAction
{
    private const SETTING_KEY_EXAM_MAX_SCORE = 'exam_max_score';

    private const SETTING_KEY_ASSESSMENT_MAX_SCORE = 'assessment_max_score';

    public function add(
        Exam $exam,
        string $questionId,
        ?string $marksOverride = null,
        ?string $userId = null
    ): ExamQuestion {
        $this->ensureDraft($exam, 'added');

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
        $this->ensureDraft($exam, 'modified');

        return DB::transaction(function () use ($exam, $questionId, $marksOverride) {
            $examQuestion = $this->resolveExamQuestion($exam, $questionId);

            $examQuestion->update(['marks' => $marksOverride ?? $examQuestion->question->default_marks]);
            $this->recomputeTotalMarks($exam);

            return $examQuestion->fresh();
        });
    }

    public function remove(Exam $exam, string $questionId): void
    {
        $this->ensureDraft($exam, 'removed');

        DB::transaction(function () use ($exam, $questionId) {
            $examQuestion = $this->resolveExamQuestion($exam, $questionId);

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

    private function ensureDraft(Exam $exam, string $action): void
    {
        if (! $exam->isDraft()) {
            throw new \RuntimeException("Questions can only be {$action} to draft exams.");
        }
    }

    public function recomputeTotalMarks(Exam $exam): void
    {
        $total = $exam->examQuestions()->get()->sum(fn ($eq) => $eq->getEffectiveMarks());
        $this->ensureWithinSchoolMaximum($exam, (float) $total);

        $exam->update(['total_marks' => $total]);
    }

    private function resolveExamQuestion(Exam $exam, string $identifier): ExamQuestion
    {
        $examQuestionByRowId = ExamQuestion::find($identifier);
        $examQuestionByQuestionId = ExamQuestion::where('exam_id', $exam->id)
            ->where('question_id', $identifier)
            ->first();

        if ($examQuestionByRowId !== null && $examQuestionByQuestionId !== null) {
            Log::warning('Ambiguous question identifier matched both exam_questions.id and question_id.', [
                'identifier' => $identifier,
                'exam_id' => $exam->id,
                'exam_question_id' => $examQuestionByRowId->id,
                'question_exam_question_id' => $examQuestionByQuestionId->id,
            ]);
        }

        if ($examQuestionByRowId !== null && $examQuestionByRowId->exam_id === $exam->id) {
            return $examQuestionByRowId;
        }

        if ($examQuestionByQuestionId !== null) {
            return $examQuestionByQuestionId;
        }

        if ($examQuestionByRowId !== null) {
            throw new ExamQuestionBelongsToDifferentExamException;
        }

        throw new ExamQuestionNotFoundException;
    }

    private function ensureWithinSchoolMaximum(Exam $exam, float $total): void
    {
        $settingKey = $exam->type === ExamType::Exam->value
            ? self::SETTING_KEY_EXAM_MAX_SCORE
            : self::SETTING_KEY_ASSESSMENT_MAX_SCORE;
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
