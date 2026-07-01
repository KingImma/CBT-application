<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ActivateExam;
use App\Actions\Tenants\Exam\CreateExam;
use App\Actions\Tenants\Exam\DeleteExam;
use App\Actions\Tenants\Exam\SubmitExamForReview;
use App\Actions\Tenants\Exam\UpdateExam;
use App\Data\Exam\Input\CreateExamData;
use App\Data\Exam\Input\UpdateExamData;
use App\Data\Exam\Output\ExamData;
use App\Exceptions\Domain\Exam\ExamCannotBeCompletedException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamController extends Controller
{
    /**
     * List all exams with optional filters.
     *
     * @subgroup Exam Management
     *
     * @queryParam status string Filter by status (draft, submitted, active, completed). No-example
     * @queryParam subject_id string Filter by subject UUID. No-example
     * @queryParam class_level_id string Filter by class level UUID. No-example
     * @queryParam class_arm_id string Filter by class arm UUID. No-example
     * @queryParam per_page int Results per page (default: 20). No-example
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $user = $request->user('tenant');

        $exams = QueryBuilder::for(
            Exam::query()
                ->visibleTo($user)
                ->with(['subject', 'classLevel', 'classArm', 'term', 'creator:id,first_name,last_name'])
        )
            ->allowedFilters('status', 'subject_id', 'class_level_id', 'class_arm_id')
            ->defaultSort('-created_at')
            ->withCount('examQuestions as question_count')
            ->paginate($perPage);

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
    public function store(CreateExamData $data, Request $request, CreateExam $action): JsonResponse
    {
        $this->authorize('create', Exam::class);

        $exam = $action->execute($data, $request->user('tenant')->id);

        return ApiResponse::created(
            $exam->load(['subject', 'classLevel']),
            'Exam created.',
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
            'subject',
            'classLevel',
            'classArm',
            'term',
            'creator:id,first_name,last_name',
            'examQuestions.question.options',
        ])->loadCount('examQuestions as question_count');

        return ApiResponse::success(
            ExamData::from($exam),
            'Exam retrieved successfully.',
        );
    }

    /**
     * Update an existing exam.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     */
    public function update(UpdateExamData $data, Exam $exam, UpdateExam $action): JsonResponse
    {
        $this->authorize('update', $exam);

        $exam = $action->execute($exam, $data);

        return ApiResponse::success(
            $exam->load(['subject', 'classLevel']),
            'Exam updated.',
        );
    }

    /**
     * Delete an exam.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     */
    public function destroy(Exam $exam, DeleteExam $action): JsonResponse
    {
        $this->authorize('delete', $exam);

        $action->execute($exam);

        return ApiResponse::message('Exam deleted.');
    }

    /**
     * Submit an exam for review by an administrator.
     *
     * @subgroup Exam Workflow
     */
    public function submitForReview(Exam $exam, SubmitExamForReview $action): JsonResponse
    {
        $this->authorize('submitForReview', $exam);

        $exam = $action->execute($exam);

        return ApiResponse::success($exam, 'Exam submitted for review.');
    }

    /**
     * Activate an exam, making it visible to students.
     * Students can start the exam when scheduled_start is reached.
     *
     * @subgroup Exam Workflow
     */
    public function activate(Exam $exam, Request $request, ActivateExam $action): JsonResponse
    {
        $this->authorize('activate', $exam);
        $exam = $action->execute($exam, $request->user('tenant')->id);

        $students = $exam->attempts()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $notification = new InAppNotification(
            title: 'Exam Activated',
            message: "The exam {$exam->title} is now active.",
            type: 'success',
            action: [
                'url' => "/student/exams/{$exam->id}",
                'label' => 'View Exam',
            ]
        );

        Notification::send($students, $notification);

        return ApiResponse::success($exam, 'Exam activated.');
    }

    /**
     * Publish an exam to make it available to students.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function publish(Exam $exam): JsonResponse
    {
        $this->authorize('publish', $exam);

        try {
            $exam->publish();
            $exam->save();
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam published.');
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

        $exam->publish();
        $exam->save();

        return ApiResponse::success($exam, 'Results published.');
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

        $exam->unpublish();
        $exam->save();

        return ApiResponse::success($exam, 'Results unpublished.');
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

        try {
            $exam->complete();
            $exam->save();
        } catch (ExamCannotBeCompletedException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            ExamData::from($exam->load(['subject', 'classLevel'])),
            'Exam ended successfully.'
        );
    }
}
