<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\SubmissionStatus;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Turn every approved submission on an activated assessment into a live,
 * student-facing Exam — one Exam per submission (a submission is subject-
 * specific, exactly like an Exam). Real Question + QuestionOption rows and
 * exam_questions pivots are created so the entire existing attempt / grading /
 * results path runs against a materialised paper with no changes.
 */
final class MaterializeAssessmentExams
{
    public function __construct() {}

    /**
     * @return array<int,Exam> the exams created this run
     */
    public function execute(Assessment $assessment): array
    {
        return DB::transaction(function () use ($assessment): array {
            $assessment->loadMissing('term');

            // Idempotent: only approved submissions not already backed by an exam.
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
        $exam = Exam::create([
            'title' => $submission->title,
            'subject_id' => $submission->subject_id,
            'class_level_id' => $assessment->class_level_id,
            'class_arm_id' => $assessment->class_arm_id,
            'term_id' => $assessment->term_id,
            'created_by' => $submission->teacher_id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => $this->durationMinutes($assessment),
            'total_marks' => $submission->total_marks,
            'max_attempts' => 1,
            'scheduled_start' => $assessment->student_starts_at,
            'window_end' => $assessment->student_ends_at,
            'instructions' => $assessment->instructions,
            'settings' => [],
        ]);

        $assessmentSessionId = $assessment->term?->academic_session_id;

        foreach ($submission->submissionQuestions as $sq) {
            $question = $this->materializeQuestion($assessment, $submission, $sq, $assessmentSessionId);

            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'order' => $sq->order,
                'marks' => $sq->marks,
                'is_marks_locked' => true,
            ]);
        }

        $submission->update(['exam_id' => $exam->id]);

        return $exam->fresh();
    }

    private function materializeQuestion(
        Assessment $assessment,
        Submission $submission,
        SubmissionQuestion $sq,
        ?string $academicSessionId,
    ): Question {
        $question = Question::create([
            'subject_id' => $submission->subject_id,
            'class_level_id' => $assessment->class_level_id,
            'academic_session_id' => $academicSessionId,
            'term_id' => $assessment->term_id,
            'created_by' => $submission->teacher_id,
            'type' => $sq->type->toQuestionType()->value,
            'content' => $sq->content,
            'image_url' => $sq->image_url,
            'is_active' => true,
        ]);

        foreach ($sq->options as $option) {
            QuestionOption::create([
                'question_id' => $question->id,
                'label' => $option->label,
                'content' => $option->content,
                'image_url' => $option->image_url,
                'is_correct' => $option->is_correct,
                'order' => $option->order,
            ]);
        }

        return $question;
    }

    /**
     * Exams require a duration; assessments may leave it null (the student
     * window alone bounds the attempt). Fall back to the full window length.
     */
    private function durationMinutes(Assessment $assessment): int
    {
        if ($assessment->duration_minutes) {
            return $assessment->duration_minutes;
        }

        if ($assessment->student_starts_at && $assessment->student_ends_at) {
            return max(1, (int) $assessment->student_starts_at->diffInMinutes($assessment->student_ends_at));
        }

        return 60;
    }
}
