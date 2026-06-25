<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Question;
use App\Policies\Tenant\ClassArmPolicy;
use App\Policies\Tenant\ExamAnswerPolicy;
use App\Policies\Tenant\ExamAttemptPolicy;
use App\Policies\Tenant\ExamPolicy;
use App\Policies\Tenant\QuestionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        ClassArm::class => ClassArmPolicy::class,
        Exam::class => ExamPolicy::class,
        ExamAnswer::class => ExamAnswerPolicy::class,
        ExamAttempt::class => ExamAttemptPolicy::class,
        Question::class => QuestionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
