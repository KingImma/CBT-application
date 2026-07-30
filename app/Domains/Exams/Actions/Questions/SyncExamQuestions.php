<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Data\Input\SyncExamQuestionsData;
use App\Domains\Exams\Support\MarksDistributor;
use App\Domains\Exams\Exceptions\ExamStateTransitionException;
use App\Domains\Exams\Data\Input\SyncExamQuestionItemData;
use App\Domains\Tenancy\Exceptions\BaseDomainException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\SchoolSetting;
use App\Enums\ExamType;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncExamQuestions
{
    public function __construct(
        protected readonly MarksDistributor $marksDistributor
    ) {}

    /**
     * @throws BaseDomainException
     */
    public function execute(Exam $exam, SyncExamQuestionsData $data, string $userId): array
    {
        $this->assertExamIsDraft($exam);

        $items = $data->questions->toCollection();

        $this->assertNotDuplicateQuestionIds($items);
        $this->assertQuestionsOwnedByTeacher($items, $userId);
        $this->assertWithinSchoolMax($exam);

        [$lockedItems, $unlockedItems] = $items->partition(
            fn (SyncExamQuestionItemData $item) => $item->is_marks_locked
        );

        $lockedSum = (float) $lockedItems->sum('marks');
        $this->assertLockedSumWithinTotal($lockedSum, (float) $exam->total_marks);

        $remaining = max(0.0, (float) $exam->total_marks - $lockedSum);
        $distributedMarks = $unlockedItems->isNotEmpty()
            ? $this->marksDistributor->distribute($remaining, $unlockedItems->count())
            : [];

        $rows = $this->buildRows($items, $lockedItems, $unlockedItems, $distributedMarks, $exam->id);

        DB::transaction(function () use ($exam, $rows) {
            $exam->examQuestions()->delete();
            ExamQuestion::insert($rows);
        });

        return $exam->examQuestions()->with('question.options')->orderBy('order')->get()->all();
    }

    private function assertExamIsDraft(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            new ExamStateTransitionException('Cannot sync questions to a non-draft exam')
        );
    }

    private function assertNotDuplicateQuestionIds($items) :void
    {
      $ids = $items->pluck('question_id');
      throw_if(
        $ids->count() !== $ids->unique()->count(),
        new BaseDomainException('Duplicate question IDs found in the payload. Each question ID must be unique within the exam.')
      );
    }

    private function assertQuestionsOwnedByTeacher($items, string $userId) :void
    {
        $questionIds = $items->pluck('question_id')->all();

        $owned = Question::whereIn('id', $questionIds)
              ->where('created_by', $userId)
              ->pluck('id')
              ->all();

        $missing = array_diff($questionIds, $owned);

        throw_if(
          $missing !== [],
          new BaseDomainException('One or more questions do not belong to your question bank.: ' . implode(', ', $missing))
        );
    }

    private function assertLockedSumWithinTotal(float $lockedSum, float $totalMarks): void
    {
        throw_if(
            $lockedSum > $totalMarks,
            new BaseDomainException(
                "Locked marks ({$lockedSum}) exceed the exam's total marks ({$totalMarks})."
            )
        );
    }

    private function assertWithinSchoolMax(Exam $exam): void
    {
        $key = $exam->type === ExamType::Exam->value ? 'exam_max_score' : 'assessment_max_score';
        $max = SchoolSetting::where('key', $key)->value('value');

        if ($max === null) {
            return;
        }

        $max = (float) $max;

        throw_if($max <= 0, new BaseDomainException("School max score for {$exam->type} is not configured."));
        throw_if(
            (float) $exam->total_marks > $max,
            new BaseDomainException("Total marks ({$exam->total_marks}) exceeds school max of {$max} for {$exam->type}.")
        );
    }

    private function buildRows($items, $lockedItems, $unlockedItems, array $distributedMarks, string $examId): array
    {
        $now = now();
        $unlockedIndex = 0;
        $rows = [];

        // preserve original payload order for the `order` column
        foreach ($items as $item) {
            $isLocked = (bool) $item->is_marks_locked;

            $marks = $isLocked
                ? $item->marks // §05-01: trust locked value verbatim
                : ($distributedMarks[$unlockedIndex++] ?? 0.0); // §05-02: server-calculated, client value discarded

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'exam_id' => $examId,
                'question_id' => $item->question_id,
                'order' => $item->order,
                'marks' => $marks,
                'is_marks_locked' => $isLocked,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }
}
