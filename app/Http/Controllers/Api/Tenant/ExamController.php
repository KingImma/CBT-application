<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ActivateExam;
use App\Actions\Tenants\Exam\CreateExam;
use App\Actions\Tenants\Exam\DeleteExam;
use App\Actions\Tenants\Exam\ForceCompleteExam;
use App\Actions\Tenants\Exam\PublishExamResults;
use App\Actions\Tenants\Exam\SubmitExamForReview;
use App\Actions\Tenants\Exam\UnpublishExamResults;
use App\Actions\Tenants\Exam\UpdateExam;
use App\Data\Exam\Input\CreateExamData;
use App\Data\Exam\Input\UpdateExamData;
use App\Data\Exam\Output\ExamData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use App\Notifications\InAppNotification;
use Illuminate\Support\Facades\Notification;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamController extends Controller
{
    public function __construct(
        private CreateExam           $createExam,
        private UpdateExam           $updateExam,
        private DeleteExam           $deleteExam,
        private SubmitExamForReview  $submitForReview,
        private ActivateExam         $activateExam,
        private ForceCompleteExam    $forceComplete,
        private PublishExamResults   $publishResults,
        private UnpublishExamResults $unpublishResults,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $exams = QueryBuilder::for(
            Exam::query()
                ->visibleTo($request->user('tenant'))
                ->with(['subject', 'classLevel', 'classArm', 'term', 'creator:id,first_name,last_name'])
        )
            ->allowedFilters('status', 'subject_id', 'class_level_id', 'class_arm_id')
            ->defaultSort('-created_at')
            ->withCount('examQuestions as question_count')
            ->paginate((int) $request->get('per_page', 20));

        return ApiResponse::paginated(
            $exams,
            'Exams retrieved successfully.',
            ExamData::collect($exams->getCollection())
        );
    }

    /**
     * Create a new exam.
     *
     * @subgroup Exam Management
     */
    public function store(CreateExamData $data, Request $request): JsonResponse
    {
        $this->authorize('create', Exam::class);

        $exam = $this->createExam->execute($data, $request->user('tenant')->id);

        return ApiResponse::created(
            $exam->load(['subject', 'classLevel']),
            'Exam created.'
        );
    }

    /**
     * Get a single exam with its questions.
     *
     * @subgroup Exam Management
     *
     * @urlParam exam string required The exam UUID.
     */
    public function show(Exam $exam): JsonResponse
    {
        $this->authorize('view', $exam);

        $exam->load([
            'subject', 'classLevel', 'classArm', 'term',
            'creator:id,first_name,last_name',
            'examQuestions.question.options',
        ])->loadCount('examQuestions as question_count');

        return ApiResponse::success(ExamData::from($exam), 'Exam retrieved successfully.');
    }

    /**
     * Update an existing exam.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     */
    public function update(UpdateExamData $data, Exam $exam): JsonResponse
    {
        $this->authorize('update', $exam);

        return ApiResponse::success(
            $this->updateExam->execute($exam, $data)->load(['subject', 'classLevel']),
            'Exam updated.'
        );
    }

    /**
     * Delete an exam.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     */
    public function destroy(Exam $exam): JsonResponse
    {
        $this->authorize('delete', $exam);

        $this->deleteExam->execute($exam);

        return ApiResponse::message('Exam deleted.');
    }

    /**
     * Submit an exam for review by an administrator.
     *
     * @subgroup Exam Workflow
     */
    public function submitForReview(Exam $exam): JsonResponse
    {
        $this->authorize('submitForReview', $exam);

        return ApiResponse::success(
            $this->submitForReview->execute($exam),
            'Exam submitted for review.'
        );
    }

    /**
     * Activate an exam, making it visible to students.
     * Students can start the exam when scheduled_start is reached.
     *
     * @subgroup Exam Workflow
     */
    public function activate(Exam $exam, Request $request): JsonResponse
    {
        $this->authorize('activate', $exam);
        $exam = $this->activateExam->execute($exam, $request->user('tenant')->id);

        $students = $exam
            ->attempts()
            ->with("student")
            ->get()
            ->pluck("student")
            ->filter();

        $notification = new InAppNotification(
            title: "Exam Activated",
            message: "The exam {$exam->title} is now active.",
            type: "success",
            action: [
                "url" => "/student/exams/{$exam->id}",
                "label" => "View Exam",
            ],
        );

        Notification::send($students, $notification);

        return ApiResponse::success(
            $exam,
            'Exam activated.'
        );
    }

    /**
     * Publish results for an exam, making them visible to students.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function publishResults(Exam $exam): JsonResponse
    {
        $this->authorize('publishResults', $exam);

        return ApiResponse::success(
            $this->publishResults->execute($exam),
            'Results published.'
        );
    }

    /**
     * Unpublish results for an exam, rolling the status back to Completed
     * and hiding results from students.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function unpublishResults(Exam $exam): JsonResponse
    {
        $this->authorize('unpublishResults', $exam);

        return ApiResponse::success(
            $this->unpublishResults->execute($exam),
            'Results unpublished.'
        );
    }

    /**
     * Force-complete an active exam immediately, bypassing window
     * and completed attempt checks.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function forceComplete(Exam $exam): JsonResponse
    {
        $this->authorize('forceComplete', $exam);

        return ApiResponse::success(
            ExamData::from($this->forceComplete->execute($exam)->load(['subject', 'classLevel'])),
            'Exam ended successfully.'
        );
    }
}
