<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Exceptions\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamComment;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class AddResultComment
{
    public function execute(Exam $exam, User $admin, string $comment)
    {
        throw _unless(
            $exam->isCompleted(),
            new ExamStateTransitionException("Result comments can only be added on completed exams.")
        );

        return DB::transaction(fn () => ExamComment::create([
            "exam_id" => $exam->id,
            "author_id" => $admin->id,
            "comment" => $comment,
        ]));
    }
}