<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Illuminate\Support\Collection;

final class SuggestExamQuestions
{
    public function execute(Exam $exam, int $count): Collection
    {
        $existingIds = $exam->examQuestions()->pluck('question_id');

        $available = Question::where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->where('is_active', true)
            ->whereNotIn('id', $existingIds);

        throw_if(
            $available->count() < $count,
            new BaseDomainException("Only {$available->count()} questions available, {$count} requested.")
        );

        return $available->inRandomOrder()->limit($count)->with('options')->get();
    }
}
