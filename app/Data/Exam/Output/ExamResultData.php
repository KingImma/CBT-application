<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Models\Tenant\ExamAttempt;
use Spatie\LaravelData\Resource;

class ExamResultData extends Resource
{
    public function __construct(
        public readonly string $attempt_id,
        public readonly string $exam_id,
        public readonly string $exam_title,
        public readonly string $status,
        public readonly int $attempt_number,
        public readonly ?float $total_score,
        public readonly ?float $total_marks,
        public readonly ?float $percentage_score,
        public readonly ?string $grade,
        public readonly ?string $submitted_at,
        public readonly ?int $time_spent_seconds,

        /** @var array<ResultQuestionData> */
        public readonly array $questions,
    ) {}

    public static function fromAttempt(ExamAttempt $attempt): self
    {
        // Ensure relations are loaded to avoid N+1 queries
        $attempt->load([
            'exam',
            'answers.question',
            'exam.examQuestions.question',
        ]);

        return new self(
            attempt_id: $attempt->id,
            exam_id: $attempt->exam_id,
            exam_title: $attempt->exam->title,
            status: $attempt->status,
            attempt_number: $attempt->attempt_number,
            total_score: (float) $attempt->total_score,
            total_marks: (float) $attempt->exam->total_marks,
            percentage_score: $attempt->percentage_score
                ? (float) $attempt->percentage_score
                : null,
            grade: $attempt->grade,
            submitted_at: $attempt->submitted_at?->toIso8601String(),
            time_spent_seconds: $attempt->time_spent_seconds,
            questions: $attempt->answers
                ->map(function ($answer) use ($attempt) {
                    // Find the exam question configuration for this answer
                    $examQuestion = $attempt->exam->examQuestions
                        ->where('question_id', $answer->question_id)
                        ->first();

                    return ResultQuestionData::fromAnswer(
                        $answer,
                        $examQuestion,
                        $answer->question,
                    );
                })
                ->toArray(),
        );
    }
}
