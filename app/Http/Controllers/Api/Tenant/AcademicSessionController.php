<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Academic\Actions\CreateSession;
use App\Domains\Academic\Actions\UpdateSession;
use App\Domains\Academic\Data\AcademicSessionData;
use App\Domains\Academic\Exceptions\SessionAlreadyCurrentException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Academic Calendar
 * * APIs for defining school years and terms.
 */
class AcademicSessionController extends Controller
{
    /**
     * List all academic sessions with their terms.
     *
     * @subgroup Academic Sessions
     */
    public function index(): JsonResponse
    {
        $sessions = AcademicSession::with('terms')
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::success(
            AcademicSessionData::collect($sessions),
            'Academic sessions retrieved successfully.',
        );
    }

    /**
     * Create a new academic session.
     *
     * @subgroup Academic Sessions
     *
     * @bodyParam name string required Session name. Example: "2025/2026"
     * @bodyParam start_date string required Start date (Y-m-d). No-example
     * @bodyParam end_date string required End date (Y-m-d), must be after start_date. No-example
     * @bodyParam is_current boolean Set as the current session. No-example
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $session = (new CreateSession)->execute($validated);

        return ApiResponse::created(
            AcademicSessionData::from($session),
            'Academic session created.',
        );
    }

    /**
     * Get a single academic session with its terms.
     *
     * @subgroup Academic Sessions
     *
     * @urlParam id string required The session UUID.
     */
    public function show(string $id): JsonResponse
    {
        $session = AcademicSession::with('terms')->findOrFail($id);

        return ApiResponse::success(
            AcademicSessionData::from($session),
            'Academic session retrieved successfully.',
        );
    }

    /**
     * Update an academic session.
     *
     * @subgroup Academic Sessions
     *
     * @urlParam id string required The session UUID.
     *
     * @bodyParam name string Session name. No-example
     * @bodyParam start_date string Start date. No-example
     * @bodyParam end_date string End date. No-example
     * @bodyParam is_current boolean Set as current. No-example
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $session = (new UpdateSession)->execute($session, $validated);

        return ApiResponse::success(
            AcademicSessionData::from($session->load('terms')),
            'Academic session updated.',
        );
    }

    /**
     * Delete an academic session.
     *
     * @subgroup Academic Sessions
     *
     * @urlParam id string required The session UUID.
     */
    public function destroy(string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        if ($session->terms()->exists()) {
            return ApiResponse::error(
                'Cannot delete a session with existing terms. Delete the terms first.',
                422,
            );
        }

        $session->delete();

        return ApiResponse::message('Academic session deleted.');
    }

    /**
     * Set a session as the current one.
     * Only one session can be current at a time.
     *
     * @subgroup Academic Sessions
     */
    public function setCurrent(string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        try {
            $session->setAsCurrent()->save();
        } catch (SessionAlreadyCurrentException $e) {
            return ApiResponse::success(
                [
                    'session' => AcademicSessionData::from(
                        $session->load('terms'),
                    ),
                ],
                $e->getMessage(),
            );
        }

        return ApiResponse::success(
            [
                'session' => AcademicSessionData::from(
                    $session->fresh('terms'),
                ),
            ],
            "'{$session->name}' is now the current academic session.",
        );
    }
}
