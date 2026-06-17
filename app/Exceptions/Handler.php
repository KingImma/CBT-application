<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\Exceptions\MonitorException;
use App\Exceptions\Auth\AccountDeactivatedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Business\BulkOperationException;
use App\Exceptions\Business\PlanLimitExceededException;
use App\Exceptions\Tenant\TenantSlugAlreadyTakenException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Never report these — they are expected operational failures,
     * not bugs. Logging them pollutes your log pipeline.
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        InvalidCredentialsException::class,
        AccountDeactivatedException::class,
        TenantSlugAlreadyTakenException::class,
        HttpResponseException::class,
    ];

    public function register(): void
    {
        // reportable — structured logging with context for bugs worth tracking
        $this->reportable(function (Throwable $e) {
            // Build context once — structuredLog writes it, monitoring service receives it
            $context = $this->buildStructuredContext($e);

            Log::error('Exception occurred', $context);

            app(MonitorException::class)->execute(
                get_class($e),
                $context
            );
        });

        // renderable — typed responses for each known exception
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->renderApiException($e, $request);
            }
        });
    }

    private function renderApiException(Throwable $e, Request $request): JsonResponse
    {
        // ── Validation ─────────────────────────────────────────────────────
        if ($e instanceof ValidationException) {
            return ApiResponse::error('The given data was invalid.', 422, $e->errors());
        }

        // ── Authentication ──────────────────────────────────────────────────
        if ($e instanceof AuthenticationException) {
            return ApiResponse::error('You must be logged in to access this resource.', 401);
        }

        // ── Invalid credentials / deactivated account ───────────────────────
        if ($e instanceof InvalidCredentialsException ||
            $e instanceof AccountDeactivatedException) {
            return ApiResponse::error($e->getMessage(), 401);
        }

        // ── Model not found ─────────────────────────────────────────────────
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());

            return ApiResponse::error("{$model} not found.", 404);
        }

        // ── Route not found ─────────────────────────────────────────────────
        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::error("The path '{$request->path()}' does not exist.", 404);
        }

        // ── Method not allowed ──────────────────────────────────────────────
        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(
                "{$request->method()} is not supported on this route.",
                405,
                meta: ['allowed' => $e->getHeaders()['Allow'] ?? null]
            );
        }

        // ── Rate limiting ───────────────────────────────────────────────────
        if ($e instanceof ThrottleRequestsException) {
            return ApiResponse::error(
                'Slow down — you are sending requests too quickly.',
                429,
                meta: ['retry_after' => $e->getHeaders()['Retry-After'] ?? 60]
            );
        }

        // ── Tenant slug conflict ────────────────────────────────────────────
        if ($e instanceof TenantSlugAlreadyTakenException) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        // ── Plan limit exceeded ─────────────────────────────────────────────
        if ($e instanceof PlanLimitExceededException) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        // ── Bulk operation partial failure ──────────────────────────────────
        if ($e instanceof BulkOperationException) {
            return ApiResponse::error(
                $e->getMessage(),
                422,
                meta: ['results' => ['succeeded' => $e->succeeded, 'failed' => $e->failed, 'failures' => $e->failures]]
            );
        }

        // ── Generic HTTP exceptions ─────────────────────────────────────────
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();

            return ApiResponse::error($e->getMessage() ?: $this->statusLabel($status), $status);
        }

        // ── Database errors ─────────────────────────────────────────────────
        if ($e instanceof QueryException) {
            return $this->buildServerError(
                $e,
                'A database error occurred. Please try again.'
            );
        }

        // ── Everything else ─────────────────────────────────────────────────
        return $this->buildServerError($e, 'An unexpected error occurred.');
    }

    /**
     * Dev gets full stack trace. Production gets a clean message.
     * Never expose internals to clients in production.
     */
    private function buildServerError(Throwable $e, string $productionMessage): JsonResponse
    {
        $meta = [];

        if (! app()->isProduction()) {
            $meta['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect(explode("\n", $e->getTraceAsString()))
                    ->take(15)
                    ->values()
                    ->toArray(),
            ];
        }

        return ApiResponse::error($productionMessage, 500, meta: $meta);
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Unauthenticated.',
            403 => 'Forbidden.',
            404 => 'Not found.',
            405 => 'Method not allowed.',
            409 => 'Conflict.',
            422 => 'Unprocessable entity.',
            429 => 'Too many requests.',
            500 => 'Internal server error.',
            503 => 'Service unavailable.',
            default => 'HTTP error.',
        };
    }

    private function buildStructuredContext(Throwable $e): array
    {
        $context = [
            'trace_id' => Str::uuid()->toString(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if (request()) {
            $context['request'] = [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'tenant' => request()->header('X-Tenant') ?? tenant('id'),
            ];
        }

        if (auth()->check()) {
            $context['user'] = ['id' => auth()->id()];
        } elseif (auth('super_admin')->check()) {
            $context['super_admin'] = ['id' => auth('super_admin')->id()];
        }

        if ($e instanceof QueryException) {
            $context['sql'] = $e->getSql();
            $context['bindings'] = $e->getBindings();
        }

        return $context;
    }
}
