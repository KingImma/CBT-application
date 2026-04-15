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
    }
}