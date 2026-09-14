<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Results\BuildBroadsheet;
use App\Domains\Exams\Actions\Results\GenerateBroadsheetPdf;
use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadsheetController extends Controller
{
    public function __construct(
        private BuildBroadsheet $buildBroadsheet,
        private GenerateBroadsheetPdf $generatePdf,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
            'term_id' => ['required', 'uuid', 'exists:terms,id'],
            'academic_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
        ]);

        $broadsheet = $this->buildBroadsheet->execute(
            $validated['class_level_id'],
            $validated['class_arm_id'] ?? null,
            $validated['term_id'],
            $validated['academic_session_id'],
        );

        return ApiResponse::success($broadsheet, 'Broadsheet retrieved successfully.');
    }

    public function pdf(Request $request)
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
            'term_id' => ['required', 'uuid', 'exists:terms,id'],
            'academic_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
        ]);

        $broadsheet = $this->buildBroadsheet->execute(
            $validated['class_level_id'],
            $validated['class_arm_id'] ?? null,
            $validated['term_id'],
            $validated['academic_session_id'],
        );

        $pdf = $this->generatePdf->execute($broadsheet);

        return $pdf->download($this->generatePdf->filename($broadsheet));
    }
}
