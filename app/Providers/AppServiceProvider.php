<?php

namespace App\Providers;

use App\Domains\Academic\Actions\ResolveCurrentSessionTerm;
use App\Domains\Exams\Events\ExamActivated;
use App\Domains\Exams\Events\ExamCompleted;
use App\Events\UserActivated;
use App\Events\UserDeactivated;
use App\Domains\Exams\Listeners\SendExamActivatedNotification;
use App\Domains\Tenancy\Listeners\SendUserStatusNotification;
use App\Domains\Exams\Listeners\SendExamCompletedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResolveCurrentSessionTerm::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PermissionRegistrar::class;
        // app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Allow access to the Swagger docs in production environments
        Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        // 1. Global Email Interceptor - route ALL emails to test address
        $overrideEmail = env('MAIL_ALWAYS_TO');
        if (! empty($overrideEmail)) {
            Mail::alwaysTo($overrideEmail);
        }

        // 2. Register event listeners
        Event::listen(
            ExamActivated::class,
            SendExamActivatedNotification::class,
        );

        Event::listen(
            UserActivated::class,
            SendUserStatusNotification::class,
        );

        Event::listen(
            UserDeactivated::class,
            SendUserStatusNotification::class,
        );

        Event::listen(
            ExamCompleted::class,
            SendExamCompletedNotification::class,
        );

        // 3. Password reset URL override for multi-tenant
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $tenantHandle = tenant('handle');

            return "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->email}&tenant={$tenantHandle}";
        });
    }
}
