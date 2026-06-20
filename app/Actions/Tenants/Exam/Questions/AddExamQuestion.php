<?php 

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Data\Exam\Input\AddQuestionData;
use App\Exceptions\Domain\Exam\DuplicateExamQuestionException;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;
use DomainException;

class AddExamQuestion
{
    public function __construct(
        private RecomputeExamTotalMarks $recomputeMarks
    )
    {}

    public function execute(Exam $exam, Question $question, AddQuestionData $data, string $userId): ExamQuestion
    {
        $this->validateState($exam, $question, $userId);

        return DB::transaction(fn () => $this->performAddition($exam, $question, $data));
    }

    private function validateState(Exam $exam, Question $question, string $userId): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            'Questions can only be added to a draft exam.'
        );

        throw_if(
            $question->created_by !== $userId,
            DomainException::class,
            'Question does not belong to your question bank.'
        );

        $isDuplicate = $exam->examQuestions()->where('question_id', $question->id)->exists();

        throw_if(
            $isDuplicate,
            DuplicateExamQuestionException::class,
            'This question has already been added to the exam.'
        );
    }

    private function performAddition(Exam $exam, Question $question, ?float $marksOverride): ExamQuestion
    {
        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        $examQuestion = ExamQuestion::create([
            'exam_id'    => $exam->id,
            'question_id' => $question->id,
            'order'       => $maxOrder + 1,
            'marks'       => $data->marks_override ?? $question->default_marks,
        ]);

        $this->recomputeMarks->execute($exam);

        return $examQuestion;
    }
}