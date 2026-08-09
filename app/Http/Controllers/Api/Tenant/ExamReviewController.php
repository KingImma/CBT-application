<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Review\AddComment;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExamReviewController extends Controller
{
    public function __construct(
        private AddComment $addComment,
    ) {}

    public function show(Exam $exam): JsonResponse{
        Gate::authorize('review', $exam);

        $exam->load(['subject', 'classLevel', 'creator:id,first_name,last_name', 'comments.author:id,first_name,last_name']);

        return ApiResponse::success(
            $exam,
            message: 'Exam retrieved for review.'
        );
    }

    public function addComment(Request $request, Exam $exam): JsonResponse
    {
        Gate::authorize('review', $exam);

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment = $this->addComment->execute($exam, $request->user('tenant'), $validated['comment']);

        return ApiResponse::created(
            $comment->load('author:id,first_name,last_name'),
            message: 'Comment added.'
        );
    }
}
