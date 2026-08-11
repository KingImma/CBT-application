<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\ActivateAssessment;
use App\Domains\Assessments\Actions\CloseSubmissions;
use App\Domains\Assessments\Actions\CompleteAssessment;
use App\Domains\Assessments\Actions\CreateAssessment;
use App\Domains\Assessments\Actions\DeleteAssessment;
use App\Domains\Assessments\Actions\OpenAssessment;
use App\Domains\Assessments\Actions\ReopenAssessment;
use App\Domains\Assessments\Actions\UpdateAssessment;
use App\Domains\Assessments\Data\Input\CreateAssessmentData;
use App\Domains\Assessments\Data\Input\UpdateAssessmentData;
use App\Domains\Assessments\Data\Output\AssessmentData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Assessment;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Assessment Administration
 *
 * Admin-owned assessment containers: the two-window lifecycle
 * (draft → open → submissions_closed → active → completed).
 */
class AssessmentController extends Controller
{
    public function __construct(
        private CreateAssessment $createAssessment,
        private UpdateAssessment $updateAssessment,
        private DeleteAssessment $deleteAssessment,
        private OpenAssessment $openAssessment,
        private CloseSubmissions $closeSubmissions,
        private ReopenAssessment $reopenAssessment,
        private ActivateAssessment $activateAssessment,
        private CompleteAssessment $completeAssessment,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Assessment::class);

        $assessments = QueryBuilder::for(
            Assessment::query()
                ->visibleTo($request->user('tenant'))
                ->with(['classLevel', 'classArm', 'term', 'creator:id,first_name,last_name'])
        )
            ->allowedFilters('status', 'class_level_id', 'class_arm_id', 'term_id')
            ->defaultSort('-created_at')
            ->withCount('submissions as submission_count')
            ->paginate((int) $request->get('per_page', 20));

        return ApiResponse::paginated(
            $assessments,
            'Assessments retrieved successfully.',
            AssessmentData::collect($assessments->getCollection())
        );
    }

    public function store(CreateAssessmentData $data, Request $request): JsonResponse
    {
        Gate::authorize('create', Assessment::class);

        $assessment = $this->createAssessment->execute($data, $request->user('tenant')->id);

        return ApiResponse::created(
            AssessmentData::from($assessment->load(['classLevel', 'classArm', 'term'])),
            'Assessment created.'
        );
    }

    public function show(Assessment $assessment): JsonResponse
    {
        Gate::authorize('view', $assessment);

        $assessment->load([
            'classLevel', 'classArm', 'term',
            'creator:id,first_name,last_name',
            'submissions.teacher:id,first_name,last_name',
            'submissions.subject:id,name',
        ])->loadCount('submissions as submission_count');

        return ApiResponse::success(AssessmentData::from($assessment), 'Assessment retrieved successfully.');
    }

    public function update(UpdateAssessmentData $data, Assessment $assessment): JsonResponse
    {
        Gate::authorize('update', $assessment);

        return ApiResponse::success(
            AssessmentData::from($this->updateAssessment->execute($assessment, $data)->load(['classLevel', 'classArm', 'term'])),
            'Assessment updated.'
        );
    }

    public function destroy(Assessment $assessment): JsonResponse
    {
        Gate::authorize('delete', $assessment);

        $this->deleteAssessment->execute($assessment);

        return ApiResponse::message('Assessment deleted.');
    }

    public function open(Assessment $assessment): JsonResponse
    {
        Gate::authorize('open', $assessment);

        return ApiResponse::success(
            AssessmentData::from($this->openAssessment->execute($assessment)),
            'Assessment opened for submissions.'
        );
    }

    public function closeSubmissions(Assessment $assessment): JsonResponse
    {
        Gate::authorize('closeSubmissions', $assessment);

        return ApiResponse::success(
            AssessmentData::from($this->closeSubmissions->execute($assessment)),
            'Submission window closed.'
        );
    }

    public function reopen(Request $request, Assessment $assessment): JsonResponse
    {
        Gate::authorize('reopen', $assessment);

        $validated = $request->validate([
            'submission_closes_at' => ['required', 'date'],
        ]);

        return ApiResponse::success(
            AssessmentData::from($this->reopenAssessment->execute($assessment, $validated['submission_closes_at'])),
            'Submission window reopened.'
        );
    }

    public function activate(Assessment $assessment): JsonResponse
    {
        Gate::authorize('activate', $assessment);

        return ApiResponse::success(
            AssessmentData::from($this->activateAssessment->execute($assessment)),
            'Assessment activated.'
        );
    }

    public function complete(Assessment $assessment): JsonResponse
    {
        Gate::authorize('complete', $assessment);

        return ApiResponse::success(
            AssessmentData::from($this->completeAssessment->execute($assessment)),
            'Assessment completed.'
        );
    }
}
