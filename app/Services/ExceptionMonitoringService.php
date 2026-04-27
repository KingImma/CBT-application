<?php
// app/Services/ExceptionMonitoringService.php
// - What: Deepened ExceptionMonitoringService with full context interface
// - Does: Accepts the complete structured context already computed in Handler::structuredLog(); stores or forwards it
// - Why: Shallow record(class, ['message']) loses tenant/request/trace context that's already computed — monitoring without context is noise
// - Delivers: Monitoring service that can correlate errors by tenant, request, trace ID — useful for BetterStack/Sentry
// - Alternative: Pass the Throwable directly and let the service extract context — couples service to exception internals

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ExceptionMonitoringService
{
    /**
     * Record an exception with full structured context.
     *
     * @param array<string, mixed> $context The same context array built in Handler::structuredLog()
     */
    public function record(string $exceptionClass, array $context): void
    {
        // Extend this to forward to BetterStack, Sentry, Flare, etc.
        // For now: structured log with full context so nothing is lost
        // The context already contains: trace_id, request, user/super_admin, sql bindings
        Log::channel('stack')->error("Monitored exception: {$exceptionClass}", $context);

        // Example Sentry integration (uncomment when SDK is installed):
        // if (app()->bound('sentry')) {
        //     \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($context) {
        //         $scope->setExtra('context', $context);
        //         if (isset($context['request']['tenant'])) {
        //             $scope->setTag('tenant', $context['request']['tenant']);
        //         }
        //     });
        // }
    }
}