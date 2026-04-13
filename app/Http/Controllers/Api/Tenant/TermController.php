<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Term;
use App\Models\Tenant\AcademicSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function index(string $sessionId): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        return response()->json(
            $session->terms()->orderBy('name')->get()
        );
    }

    public function store(Request $request, string $sessionId): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $term = $session->terms()->create($validated);

        return response()->json($term, 201);
    }

    public function update(Request $request, string $sessionId, string $id): JsonResponse
    {
        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100'],
            'start_date' => ['sometimes', 'date'],
            'end_date'   => ['sometimes', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $term->update($validated);

        return response()->json($term->fresh());
    }

    public function destroy(string $sessionId, string $id): JsonResponse
    {
        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);
        $term->delete();

        return response()->json(['message' => 'Term deleted.']);
    }

    /**
     * Set a term as current within its session.
     * The session must already be current.
     */
    public function setCurrent(string $sessionId, string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        if (! $session->is_current) {
            return response()->json([
                'message' => 'Set this academic session as current first before setting a current term.',
            ], 422);
        }

        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);

        // 1. Prevent unnecessary database calls if it is already current
        if ($term->is_current) {
            return response()->json([
                'message' => "'{$term->name}' is already the current term.",
                'term'    => $term,
            ]);
        }

        // 2. Wrap the toggle in a transaction for data integrity
        \Illuminate\Support\Facades\DB::transaction(function () use ($term) {
            // Unset current on ALL terms globally. 
            // This is a great safety net that guarantees no term from a previous year was accidentally left active.
            Term::where('is_current', true)->update(['is_current' => false]);
            
            // Set the new current term
            $term->update(['is_current' => true]);
        });

        return response()->json([
            'message' => "'{$term->name}' is now the current term.",
            'term'    => $term->fresh(),
        ]);
    }
}