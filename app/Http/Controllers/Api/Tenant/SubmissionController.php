<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\Submissions\AddSubmissionQuestion;
use App\Domains\Assessments\Actions\Submissions\ApproveSubmission;
use App\Domains\Assessments\Actions\Submissions\CreateSubmission;
use App\Domains\Assessments\Actions\Submissions\RemoveSubmissionQuestion;
use App\Domains\Assessments\Actions\Submissions\RequestSubmissionChanges;
use App\Domains\Assessments\Actions\Submissions\SubmitSubmissionForReview;
use App\Domains\Assessments\Actions\Submissions\UpdateSubmission;
use App\Domains\Assessments\Data\Input\CreateSubmissionData;
use App\Domains\Assessments\Data\Input\SubmissionQuestionData;
use App\Domains\Assessments\Data\Input\UpdateSubmissionData;
use App\Domains\Assessments\Data\Output\SubmissionData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Assessment Submissions
 *
 * A teacher's paper inside an assessment: authoring (draft → submitted),
 * the admin review loop (request changes → resubmit), and approval.
 */
class SubmissionController extends Controller
{
    public function __construct(
        private CreateSubmission $createSubmission,
        private UpdateSubmission $updateSubmission,
        private AddSubmissionQuestion $addQuestion,
        private RemoveSubmissionQuestion $removeQuestion,
        private SubmitSubmissionForReview $submitForReview,
        private RequestSubmissionChanges $requestChanges,
        private ApproveSubmission $approveSubmission,
    ) {}

    public function index(Assessment $assessment, Request $request): JsonResponse
    {
        Gate::authorize('view', $assessment);

        $submissions = $assessment->submissions()
            ->with(['subject:id,name', 'teacher:id,first_name,last_name'])
            ->withCount('submissionQuestions as question_count')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(
            SubmissionData::collect($submissions),
            'Submissions retrieved successfully.'
        );
    }

    public function store(Assessment $assessment, CreateSubmissionData $data, Request $request): JsonResponse
    {
        // Authz (teacher|school_admin) is the route's role middleware. Whether
        // *this* teacher may author here is subject-eligibility — a domain rule
        // surfaced as a 409 by CreateSubmission, not a 403 (decision #1).
        $submission = $this->createSubmission->execute(
            $assessment,
            $data,
            $request->user('tenant')->id,
        );

        return ApiResponse::created(
            SubmissionData::from(
                $submission
                    ->load(['subject', 'teacher:id,first_name,last_name'])
                    ->loadCount('submissionQuestions as question_count')
            ),
            'Submission created.'
        );
    }

    public function show(Submission $submission): JsonResponse
    {
        Gate::authorize('view', $submission);

        $submission->load([
            'subject:id,name',
            'teacher:id,first_name,last_name',
            'assessment',
            'submissionQuestions.options',
        ])->loadCount('submissionQuestions as question_count');

        return ApiResponse::success(SubmissionData::from($submission), 'Submission retrieved successfully.');
    }

    public function update(UpdateSubmissionData $data, Submission $submission): JsonResponse
    {
        Gate::authorize('update', $submission);

        return ApiResponse::success(
            SubmissionData::from($this->updateSubmission->execute($submission, $data)->load(['subject'])),
            'Submission updated.'
        );
    }

    public function addQuestion(SubmissionQuestionData $data, Submission $submission): JsonResponse
    {
        Gate::authorize('manageQuestions', $submission);

        $question = $this->addQuestion->execute($submission, $data);

        return ApiResponse::created([
                "question" => $question,
                "submission" => [
                    'id' => $submission->id,
                    'total_marks' => (float) $submission->fresh()->total_marks,
                    'assessment_cap' => (float) $submission->assessment->total_marks,
                    'question_count' => $submission->submissionQuestions()->count()
                ]
            ], 
            'Question added.'
        );
    }

    public function removeQuestion(Submission $submission, SubmissionQuestion $question): JsonResponse
    {
        Gate::authorize('manageQuestions', $submission);

        $this->removeQuestion->execute($submission, $question);

        return ApiResponse::message([
            "submission" => [
                'id' => $submission->id,
                'total_marks' => (float) $submission->fresh()->total_marks,
                'question_count' => $submission->submissionQuestions()->count()
            ]
        ],
        'Question removed.');
    }

    public function submitForReview(Submission $submission): JsonResponse
    {
        Gate::authorize('submitForReview', $submission);

        return ApiResponse::success(
            SubmissionData::from($this->submitForReview->execute($submission)),
            'Submission sent for review.'
        );
    }

    public function requestChanges(Request $request, Submission $submission): JsonResponse
    {
        Gate::authorize('requestChanges', $submission);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        return ApiResponse::success(
            SubmissionData::from($this->requestChanges->execute(
                $submission,
                $request->user('tenant'),
                $validated['comment'],
            )),
            'Changes requested.'
        );
    }

    public function approve(Request $request, Submission $submission): JsonResponse
    {
        Gate::authorize('approve', $submission);

        return ApiResponse::success(
            SubmissionData::from($this->approveSubmission->execute($submission, $request->user('tenant'))),
            'Submission approved.'
        );
    }
}
