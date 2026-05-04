<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
// use Illuminate\Contracts\Pagination\array-key;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param array<int,mixed> $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return self::success(null, $message, $status);
    }
    /**
     * @param array<int,mixed> $errors
     * @param array<int,mixed> $meta
     */
    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }
    /**
     * @param LengthAwarePaginator<array-key,mixed> $paginator
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Retrieved successfully',
        mixed $data = null
    ): JsonResponse {
        return self::success(
            data: $data ?? $paginator->items(),
            message: $message,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]
        );
    }
}
