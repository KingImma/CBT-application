<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\Report\BuildExamClassReport;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherExamReportController extends Controller
{
    public function __construct(
        private BuildExamClassReport $buildExamClassReport,
    ) {}

    public function show(
        Request $request,
        string $armId,
        Exam $exam,
    ): JsonResponse {
        $classArm = ClassArm::with('classLevel')->findOrFail($armId);

        $this->authorize('viewExamReport', [$classArm, $exam]);

        // Visibility gate: exam must be at least completed
        if (
            ! in_array($exam->status->value, [
                ExamStatus::Completed->value,
                ExamStatus::Published->value,
            ])
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Exam results are not available yet.',
                ],
                422,
            );
        }

        $report = $this->buildExamClassReport->execute(
            $classArm,
            $exam,
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
