<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

class GetExamQuestionsAction
{
    public function execute(ExamAttempt $attempt): array
    {
        $exam = $attempt->exam;
        $questions = ExamQuestion::where('exam_id', $exam->id)
            ->with('question.options', 'question.fillBlankAnswers', 'question.topic')
            ->orderBy('order')
            ->get();

        $questionIds = $questions->pluck('question.id')->toArray();

        if (! empty($exam->settings['randomize_questions'])) {
            $shuffled = $questionIds;
            shuffle($shuffled);

            // Store randomized order in attempt settings
            $settings = $attempt->settings ?? [];
            $settings['question_order'] = $shuffled;
            $attempt->settings = $settings;
            $attempt->save();

            $questionIds = $shuffled;
        }

        return [
            'questions' => $questions,
            'order' => $questionIds,
        ];
    }
}
