<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Gate;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Notifications\ResetPassword;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        Scramble::configure()
        ->withDocumentTransformers(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
            $openApi->secure(
                \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
            );
        });
        
        Sanctum::getAccessTokenFromRequestUsing(function (Request $request) {
            $token = $request->header('Authorization');
            
            if ($token && str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
                
                // If it has our multi-tenant slug, strip it and return just the Sanctum part
                if (str_contains($token, '::')) {
                    return explode('::', $token, 2)[1];
                }
                
                return $token;
            }
        });

        // Register Mailtrap API transport
        Mail::extend('mailtrap+api', function () {
            return (new MailtrapTransportFactory)->create(
                Dsn::fromString(env('MAILTRAP_DSN'))
            );
        });

        // 1. Global Email Interceptor - route ALL emails to test address
        $overrideEmail = env('MAIL_ALWAYS_TO');
        if (!empty($overrideEmail)) {
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