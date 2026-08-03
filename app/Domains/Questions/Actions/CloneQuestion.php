<?php

declare(strict_types=1);

namespace App\Domains\Questions\Actions;

use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

class CloneQuestion
{
    public function cloneToTerm(
        string $sourceSessionId,
        string $sourceTermId,
        string $targetSessionId,
        string $targetTermId,
        string $subjectId,
        string $classLevelId,
        string $createdBy,
    ): int {
        $sourceQuestions = Question::where('academic_session_id', $sourceSessionId)
            ->where('term_id', $sourceTermId)
            ->where('subject_id', $subjectId)
            ->where('class_level_id', $classLevelId)
            ->where('is_active', true)
            ->get();

        if ($sourceQuestions->isEmpty()) {
            return 0;
        }

        $clonedCount = 0;

        DB::transaction(function () use ($sourceQuestions, $targetSessionId, $targetTermId, $createdBy, &$clonedCount) {
            foreach ($sourceQuestions as $source) {
                $clone = $source->replicate(['default_marks'])->fill([
                    'academic_session_id' => $targetSessionId,
                    'term_id' => $targetTermId,
                    'created_by' => $createdBy,
                ]);
                $clone->save();

                foreach ($source->options as $option) {
                    $option->replicate()->fill(['question_id' => $clone->id])->save();
                }

                $clonedCount++;
            }
        });

        return $clonedCount;
    }
}
