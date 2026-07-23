<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Support\MarksDistributor;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

final class RedistributeQuestionMarks
{
    public function __construct(private MarksDistributor $distributor) {}

    public function execute(Exam $exam): void
    {
        DB::transaction(function () use ($exam) {

            $orderedExamQuestionIds = $exam->examQuestions()
                ->orderBy('order')
                ->pluck('id');

            if ($orderedExamQuestionIds->isEmpty()) {
                return;
            }

            $distributedMarks = $this->distributor->distribute(
                (float) $exam->total_marks,
                $orderedExamQuestionIds->count(),
            );

            foreach ($orderedExamQuestionIds as $questionPosition => $examQuestionId) {
                ExamQuestion::whereKey($examQuestionId)->update([
                    'marks' => $distributedMarks[$questionPosition],
                ]);
            }
        });
    }
}
