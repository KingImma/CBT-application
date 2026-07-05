<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Exceptions\Domain\Exam\DuplicateExamQuestionException;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Closure;
use DomainException;

final class ExamQuestionGuards
{
    public static function isDraft(string $message): Closure
    {
        return fn (Exam $e) => throw_unless($e->isDraft(), new ExamStateTransitionException($message));
    }

    public static function notDuplicate(Question $question): Closure
    {
        return fn (Exam $e) => throw_if(
            $e->examQuestions()->where('question_id', $question->id)->exists(),
            DuplicateExamQuestionException::class
        );
    }

    public static function ownsQuestion(Question $question, string $userId): Closure
    {
        return fn (Exam $e) => throw_if(
            $question->created_by !== $userId,
            new DomainException('Question Does not belong to your question bank')
        );
    }
}
