<?php

declare(strict_types=1);

namespace App\Domains\Exams\Listeners;

use App\Domains\Exams\Events\ExamActivated;
use App\Domains\Exams\Mail\ExamActivatedMail;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\InAppNotification;
use App\Enums\NotificationLabel;

class SendExamActivatedNotification
{
    public function handle(ExamActivated $event): void
    {
        $exam = $event->exam;
        $schoolName = tenant('name') ?? 'EduCBT';

        $studentIds = $exam->attempts()->pluck('student_id')->unique();
        $recipients = User::whereIn('id', $studentIds)
            ->orWhere('id', $exam->created_by)
            ->get();

        $reportUrl = $exam->class_arm_id !== null
            ? route('exams.report', ['classArm' => $exam->class_arm_id, 'exam' => $exam->id])
            : null;

        Notification::send($recipients, new InAppNotification(
            title: 'Exam Activated',
            message: 'Your exam has been activated.',
            type: 'info',
            label: NotificationLabel::Exam->value,
            action: [
                'url' => $reportUrl,
                'label' => 'View Report',
            ],
        ));

        StudentProfile::query()
            ->where('class_level_id', $exam->class_level_id)
            ->when(
                $exam->class_arm_id,
                fn ($q) => $q->where('class_arm_id', $exam->class_arm_id),
            )
            ->with('user')
            ->chunk(100, function ($students) use ($exam, $schoolName) {
                foreach ($students as $student) {
                    if ($student->user && $student->user->email) {
                        $recipient =
                            config('mail.override_address') ?:
                            $student->user->email;
                        Mail::to($recipient)->queue(
                            new ExamActivatedMail(
                                exam: $exam,
                                studentName: $student->user->first_name,
                                schoolName: $schoolName,
                            ),
                        );
                    }
                }
            });
    }
}
