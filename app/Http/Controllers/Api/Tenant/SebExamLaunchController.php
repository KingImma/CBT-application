<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Seb\ExchangeSebToken;
use App\Domains\Exams\Actions\Seb\GetCurrentSebExam;
use App\Domains\Exams\Actions\Seb\StartSebSession;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class SebExamLaunchController extends Controller
{
    public function start(Request $request, StartSebSession $action): JsonResponse
    {
        $validated = $request->validate([
            'examId' => ['required', 'uuid', 'exists:exams,id'],
        ]);

        $exam = Exam::findOrFail($validated['examId']);
        $student = $request->user('tenant');

        $result = $action->execute($exam, $student);

        return ApiResponse::success([
            'examId' => $result['examId'],
            'launchToken' => $result['launchToken'],
        ], 'SEB session started.');
    }

    public function currentExam(Request $request, GetCurrentSebExam $action): JsonResponse
    {
        // Identity comes ONLY from the authenticated guard — never from
        // query/body input. This is the whole point of the exchange step.
        $studentId = $request->user('tenant')->id;

        $examId = $action->execute($studentId);

        return ApiResponse::success(['examId' => $examId]);
    }

    public function exchange(Request $request, ExchangeSebToken $action): RedirectResponse
    {
      $validated = $request->validate([
          't' => ['required', 'string'],
      ]);

      $result = $action->execute($validated['t']);

      $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

      return redirect()->away(
          "{$frontendUrl}/seb-entry?tenant={$result['tenantHandle']}&bearer=".urlencode($result['bearerToken'])
      );
    }
}
