<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Review\ReplyToExamComment;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherExamReviewController extends Controller
{
    public function __construct(
        private ReplyToExamComment $replyToExamComment,
    ) {}

    // Teacher sees every admin comment
    public function show(Exam $exam): JsonResponse
    {
        Gate::authorize('view', $exam);

        $exam->load([
          'comments' => fn ($q) => $q->whereNull('parent_id')->with('author:id,first_name,last_name', 'replies.author:id,first_name,last_name')->latest(), // was admin:... and replies.admin
        ]);

        return ApiResponse::success($exam, message: 'Exam retrieved for review.');
    }

    // Teacher replies to an admin comment
    public function replyToComment(Request $request, Exam $exam, string $commentId): JsonResponse
    {
        Gate::authorize('view', $exam);

        $validated = $request->validate([
            'reply' => 'required|string|max:2000',
        ]);

        $reply = $this->replyToExamComment->execute($exam, $commentId, $request->user('tenant'), $validated['reply']);

        return ApiResponse::created(['reply' => $reply], 'Reply added.');
    }
}
