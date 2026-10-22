<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\ScheduleSubjects\RemoveScheduleSubject;
use App\Domains\Assessments\Actions\ScheduleSubjects\UpsertSubmissionScheduleSlot;
use App\Domains\Assessments\Data\Input\SubmissionScheduleSlotData;
use App\Domains\Assessments\Data\Output\ScheduleSubjectData as ScheduleSubjectOutput;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Submission;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Teacher Submissions
 *
 * The teacher who owns a submission places its subject's exam window inside
 * the schedule's master window. Subject slots are teacher-owned; admins may
 * read them but cannot write them.
 */
class SubmissionScheduleSlotController extends Controller
{
    public function __construct(
        private UpsertSubmissionScheduleSlot $upsert,
        private RemoveScheduleSubject $remove,
    ) {
    }

    /** Create or move the slot for this submission's subject. */
    public function update(SubmissionScheduleSlotData $data, Submission $submission): JsonResponse
    {
        Gate::authorize('manageScheduleSlot', $submission);

        $slot = $this->upsert->execute($submission, $data);

        return ApiResponse::success(
            ScheduleSubjectOutput::from($slot->load('subject:id,name,code')),
            'Subject slot saved.',
        );
    }

    /** Remove the slot for this submission's subject. */
    public function destroy(Submission $submission): JsonResponse
    {
        Gate::authorize('manageScheduleSlot', $submission);

        $slot = $submission->schedule->scheduleSubjects()
            ->where('subject_id', $submission->subject_id)
            ->firstOrFail();

        $this->remove->execute($slot);

        return ApiResponse::message('Subject slot removed.');
    }
}