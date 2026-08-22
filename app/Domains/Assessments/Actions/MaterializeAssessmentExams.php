<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Exams\Contracts\MaterializesExamFromExternalSource;
use App\Domains\Exams\Data\MaterializeExamOptionRequest;
use App\Domains\Exams\Data\MaterializeExamQuestionRequest;
use App\Domains\Exams\Data\MaterializeExamRequest;
use App\Enums\SubmissionStatus;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Submission;
use Illuminate\Support\Facades\DB;

final class MaterializeAssessmentExams
{
    public function __construct(
        private MaterializesExamFromExternalSource $materializer,
    ) {}

    /** @return array<int,Exam> */
    public function execute(Assessment $assessment): array
    {
        return DB::transaction(function () use ($assessment): array {
            $assessment->loadMissing('term');

            $submissions = $assessment->submissions()
                ->where('status', SubmissionStatus::Approved->value)
                ->whereNull('exam_id')
                ->with('submissionQuestions.options')
                ->get();

            return $submissions
                ->map(fn (Submission $submission): Exam => $this->materialize($assessment, $submission))
                ->all();
        });
    }

    private function materialize(Assessment $assessment, Submission $submission): Exam
    {
        $scheduleSubject = $assessment->scheduleSubjects()
            ->where('subject_id', $submission->subject_id)
            ->first();

        throw_if($scheduleSubject === null, new \RuntimeException(
            "No schedule window set for subject on submission {$submission->id}. Contact the admin to schedule this subject."
        ));

        $exam = $this->materializer->execute(new MaterializeExamRequest(
            title: $submission->title,
            subjectId: $submission->subject_id,
            classLevelId: $assessment->class_level_id,
            classArmId: $assessment->class_arm_id,
            termId: $assessment->term_id,
            createdBy: $submission->teacher_id,
            durationMinutes: $scheduleSubject->optionalDurationMinutes(),
            totalMarks: (float) $submission->total_marks,
            scheduledStart: $scheduleSubject->starts_at?->toIso8601String(),
            windowEnd: $scheduleSubject->ends_at?->toIso8601String(),
            instructions: $assessment->instructions,
            questions: $submission->submissionQuestions
                ->map(fn ($sq) => new MaterializeExamQuestionRequest(
                    type: $sq->type->value,
                    order: $sq->order,
                    content: $sq->content,
                    imageUrl: $sq->image_url,
                    marks: (float) $sq->marks,
                    options: $sq->options
                        ->map(fn ($opt) => new MaterializeExamOptionRequest(
                            label: $opt->label,
                            content: $opt->content,
                            imageUrl: $opt->image_url,
                            isCorrect: (bool) $opt->is_correct,
                            order: $opt->order,
                        ))
                        ->all(),
                ))
                ->all(),
        ));

        $submission->update(['exam_id' => $exam->id]);

        return $exam;
    }
}