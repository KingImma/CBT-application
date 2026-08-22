<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Assessments\Policies\AssessmentPolicy;
use App\Domains\Assessments\Policies\AssessmentSchedulePolicy;
use App\Domains\Assessments\Policies\SubmissionPolicy;
use App\Domains\Exams\Policies\ExamAnswerPolicy;
use App\Domains\Exams\Policies\ExamAttemptPolicy;
use App\Domains\Exams\Policies\ExamPolicy;
use App\Domains\Questions\Policies\QuestionPolicy;
use App\Domains\Tenancy\Policies\ClassArmPolicy;
use App\Domains\Tenancy\Policies\UserPolicy;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Question;
use App\Models\Tenant\Submission;
use App\Models\Tenant\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Assessment::class => AssessmentPolicy::class,
        AssessmentSchedule::class => AssessmentSchedulePolicy::class,
        ClassArm::class => ClassArmPolicy::class,
        Exam::class => ExamPolicy::class,
        ExamAnswer::class => ExamAnswerPolicy::class,
        ExamAttempt::class => ExamAttemptPolicy::class,
        Question::class => QuestionPolicy::class,
        Submission::class => SubmissionPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
