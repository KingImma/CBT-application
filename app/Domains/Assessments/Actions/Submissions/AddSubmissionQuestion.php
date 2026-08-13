<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Data\Input\SubmissionQuestionData;
use App\Domains\Assessments\Exceptions\SubmissionMarksExceedCapException;
use App\Domains\Assessments\Exceptions\SubmissionStateTransitionException;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final class AddSubmissionQuestion
{
    public function __construct(
        private RecomputeSubmissionMarks $recompute,
    ) {}

    /**
     * Append a question (authored inline) with its normalised options to a
     * submission that is still editable by the teacher, then recompute the
     * submission's running total_marks.
     */
    public function execute(Submission $submission, SubmissionQuestionData $dto): SubmissionQuestion
    {
        return DB::transaction(function () use ($submission, $dto): SubmissionQuestion {
            throw_unless(
                $submission->isEditableByTeacher(),
                new SubmissionStateTransitionException(
                    'Questions can only be changed while the submission is a draft or returned for changes.'
                )
            );

            $this->assertWithinAssessmentCap($submission, $dto->marks);

            $nextOrder = (int) $submission->submissionQuestions()->max('order') + 1;

            $question = $submission->submissionQuestions()->create([
                'type' => $dto->type->value,
                'order' => $nextOrder,
                'content' => $dto->content,
                'explanation' => $dto->explanation,
                'marks' => $dto->marks,
                'image_url' => $dto->image_url,
            ]);

            if (! $dto->options instanceof Optional) {
                foreach ($dto->options as $index => $option) {
                    $question->options()->create([
                        'label' => $option->label,
                        'content' => $option->content,
                        'image_url' => $option->image_url,
                        'is_correct' => $option->is_correct,
                        'order' => $index + 1,
                    ]);
                }
            }

            $this->recompute->execute($submission);

            return $question->load('options');
        });
    }

    private function assertWithinAssessmentCap(Submission $submission, float $incomingMarks): void
    {
        $assessment = $submission->assessment()->first();

        $currentTotal = (float) $submission->submissionQuestions()->sum('marks');
        $prospectiveTotal = $currentTotal + $incomingMarks;

        throw_if(
            $prospectiveTotal > (float) $assessment->total_marks,
            new SubmissionMarksExceedCapException(
                "Adding this question ({$incomingMarks} marks) would bring the total to {$prospectiveTotal}, exceeding the assessment cap of {$assessment->total_marks}."
            )
        );
    }
}
