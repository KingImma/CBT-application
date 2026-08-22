<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\CreateAssessment;
use App\Domains\Assessments\Actions\DeleteAssessment;
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
 * Stable assessment definitions (class + subject cap). Occurrences live on
 * assessment schedules — see AssessmentScheduleController.
 */
class AssessmentController extends Controller
{
    public function __construct(
        private CreateAssessment $createAssessment,
        private UpdateAssessment $updateAssessment,
        private DeleteAssessment $deleteAssessment,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Assessment::class);

        $assessments = QueryBuilder::for(
            Assessment::query()
                ->visibleTo($request->user('tenant'))
                ->with(['classLevel', 'classArm', 'creator:id,first_name,last_name'])
        )
            ->allowedFilters('title', 'class_level_id', 'class_arm_id')
            ->defaultSort('-created_at')
            ->withCount('schedules as schedule_count')
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
            AssessmentData::from($assessment->load(['classLevel', 'classArm'])),
            'Assessment created.'
        );
    }

    public function show(Assessment $assessment): JsonResponse
    {
        Gate::authorize('view', $assessment);

        $assessment->load([
            'classLevel',
            'classArm',
            'creator:id,first_name,last_name',
            'schedules.term',
            'schedules.academicSession',
        ])->loadCount('schedules as schedule_count');

        return ApiResponse::success(AssessmentData::from($assessment), 'Assessment retrieved successfully.');
    }

    public function update(UpdateAssessmentData $data, Assessment $assessment): JsonResponse
    {
        Gate::authorize('update', $assessment);

        return ApiResponse::success(
            AssessmentData::from($this->updateAssessment->execute($assessment, $data)->load(['classLevel', 'classArm'])),
            'Assessment updated.'
        );
    }

    public function destroy(Assessment $assessment): JsonResponse
    {
        Gate::authorize('delete', $assessment);

        $this->deleteAssessment->execute($assessment);

        return ApiResponse::message('Assessment deleted.');
    }
}
