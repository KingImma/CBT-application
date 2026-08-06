<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Review;

use App\Domains\Exams\Events\ExamCommentReplied;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamComment;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class ReplyToExamComment
{
    public function execute(Exam $exam, string $parentCommentId, User $teacher, string $reply): ExamComment
    {
        $parent = ExamComment::where('exam_id', $exam->id)
            ->whereNull('parent_id') // can't reply to a reply — one level of threading only, keeps it simple
            ->findOrFail($parentCommentId);

        return DB::transaction(function () use ($exam, $parent, $teacher, $reply) {
            $replyComment = ExamComment::create([
                'exam_id' => $exam->id,
                'author_id' => $teacher->id, // see naming note above — column holds any author
                'parent_id' => $parent->id,
                'comment' => $reply,
            ]);

            $parent->update(['resolved_at' => now()]); // teacher replying = signal the note was addressed

            event(new ExamCommentReplied($exam, $parent, $replyComment));

            return $replyComment->load('author');
        });
    }
}
