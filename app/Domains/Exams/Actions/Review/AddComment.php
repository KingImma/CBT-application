<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Review;

use App\Domains\Exams\Events\ExamCommentAdded;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamComment;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class AddComment
{
    public function execute(Exam $exam, User $admin, string $comment): ExamComment
    {
        return DB::transaction(function () use ($exam, $admin, $comment): ExamComment
        {
            $examComment = ExamComment::create([
                'exam_id' => $exam->id,
                'author_id' => $admin->id,
                'comment' => $comment,
            ]);

            $exam->revertToDraft($comment);

            event(new ExamCommentAdded($exam, $admin, $examComment));

            return $examComment;
        });
    }
}
