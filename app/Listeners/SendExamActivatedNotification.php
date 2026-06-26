<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ExamActivated;
use App\Mail\ExamActivatedMail;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Facades\Mail;

class SendExamActivatedNotification
{
    public function handle(ExamActivated $event): void
    {
        $exam = $event->exam;
        $schoolName = tenant('name') ?? 'EduCBT';

        StudentProfile::query()
            ->where('class_level_id', $exam->class_level_id)
            ->when($exam->class_arm_id, fn ($q) => $q->where('class_arm_id', $exam->class_arm_id))
            ->with('user')
            ->chunk(100, function ($students) use ($exam, $schoolName) {
                foreach ($students as $student) {
                    if ($student->user && $student->user->email) {
                        $recipient = config('mail.override_address') ?: $student->user->email;
                        Mail::to($recipient)->send(new ExamActivatedMail(
                            exam: $exam,
                            studentName: $student->user->first_name,
                            schoolName: $schoolName,
                        ));
                    }
                }
            });
    }
}
