<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Data\Input\AddQuestionData;
use App\Domains\Exams\Support\ExamQuestionRules;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

final class AddExamQuestion
{
    public function __construct(private RecomputeExamTotalMarks $recompute) {}

    public function execute(Exam $exam, Question $question, AddQuestionData $data, string $userId): ExamQuestion
    {
        ExamQuestionRules::ownsQuestion($question, $userId)($exam);
        ExamQuestionRules::isDraft('Questions can only be added to a draft exam.')($exam);
        ExamQuestionRules::notDuplicate($question)($exam);

        $examQuestion = DB::transaction(function () use ($exam, $question, $data) {
            $eq = ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'order' => ($exam->examQuestions()->max('order') ?? 0) + 1,
                'marks' => $data->marks_override ?? $question->default_marks,
            ]);

            $this->recompute->execute($exam);

            return $eq;
        });

        return $examQuestion;
    }
}
