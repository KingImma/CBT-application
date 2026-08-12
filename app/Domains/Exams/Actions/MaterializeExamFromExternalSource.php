<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Domains\Exams\Contracts\MaterializesExamFromExternalSource as Contract;
use App\Domains\Exams\Data\MaterializeExamRequest;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use Illuminate\Support\Facades\DB;

final class MaterializeExamFromExternalSource implements Contract
{
    public function execute(MaterializeExamRequest $request): Exam
    {
        return DB::transaction(function () use ($request): Exam {
            $exam = Exam::create([
                'title' => $request->title,
                'subject_id' => $request->subjectId,
                'class_level_id' => $request->classLevelId,
                'class_arm_id' => $request->classArmId,
                'term_id' => $request->termId,
                'created_by' => $request->createdBy,
                'type' => ExamType::Exam->value,
                'status' => ExamStatus::Active->value,
                'duration_minutes' => $request->durationMinutes,
                'total_marks' => $request->totalMarks,
                'max_attempts' => 1,
                'scheduled_start' => $request->scheduledStart,
                'window_end' => $request->windowEnd,
                'instructions' => $request->instructions,
                'settings' => [],
            ]);

            foreach ($request->questions as $q) {
                $question = Question::create([
                    'subject_id' => $request->subjectId,
                    'class_level_id' => $request->classLevelId,
                    'created_by' => $request->createdBy,
                    'type' => $q->type,
                    'content' => $q->content,
                    'image_url' => $q->imageUrl,
                    'is_active' => true,
                ]);

                foreach ($q->options as $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $opt->label,
                        'content' => $opt->content,
                        'image_url' => $opt->imageUrl,
                        'is_correct' => $opt->isCorrect,
                        'order' => $opt->order,
                    ]);
                }

                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'question_id' => $question->id,
                    'order' => $q->order,
                    'marks' => $q->marks,
                    'is_marks_locked' => true,
                ]);
            }

            return $exam->fresh();
        });
    }
}