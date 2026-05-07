<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Academic Calendar
 * * APIs for defining school years and terms.
 */
class TermController extends Controller
{
    public function index(string $sessionId): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        return ApiResponse::success(
            $session->terms()->orderBy('name')->get(),
            'Terms retrieved successfully.'
        );
    }

    public function store(Request $request, string $sessionId): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $term = $session->terms()->create($validated);

        return ApiResponse::created($term, 'Term created.');
    }

    public function update(Request $request, string $sessionId, string $id): JsonResponse
    {
        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $term->update($validated);

        return ApiResponse::success($term->fresh(), 'Term updated.');
    }

    public function destroy(string $sessionId, string $id): JsonResponse
    {
        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);
        $term->delete();

        return ApiResponse::message('Term deleted.');
    }

    /**
     * Set a term as current within its session.
     * The session must already be current.
     */
    public function setCurrent(string $sessionId, string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        if (! $session->is_current) {
            return ApiResponse::error('Set this academic session as current first before setting a current term.', 422);
        }

        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);

        // 1. Prevent unnecessary database calls if it is already current
        if ($term->is_current) {
            return ApiResponse::success([
                'term' => $term,
            ], "'{$term->name}' is already the current term.");
        }

        // 2. Wrap the toggle in a transaction for data integrity
        DB::transaction(function () use ($term) {
            // Unset current on ALL terms globally.
            // This is a great safety net that guarantees no term from a previous year was accidentally left active.
            Term::where('is_current', true)->update(['is_current' => false]);

            // Set the new current term
            $term->update(['is_current' => true]);
        });

        return ApiResponse::success([
            'term' => $term->fresh(),
        ], "'{$term->name}' is now the current term.");
    }
}
