<?php

namespace App\Providers;

use App\Services\SessionTermContext;
use Illuminate\Auth\Notifications\ResetPassword;
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
        $this->app->singleton(SessionTermContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PermissionRegistrar::class;
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Allow access to the Swagger docs in production environments
        Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        // 1. Global Email Interceptor - route ALL emails to test address
        $overrideEmail = env('MAIL_ALWAYS_TO');
        if (! empty($overrideEmail)) {
            Mail::alwaysTo($overrideEmail);
        }

        // 2. Password reset URL override for multi-tenant
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $tenantHandle = tenant('handle');

            return "{$frontendUrl}/reset-password?token={$token}&email={$notifiable->email}&tenant={$tenantHandle}";
        });
    }
}
