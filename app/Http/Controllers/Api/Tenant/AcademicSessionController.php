<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AcademicSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = AcademicSession::with('terms')
            ->orderByDesc('start_date')
            ->get();

        return response()->json($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100', 'unique:academic_sessions,name'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $session = AcademicSession::create($validated);

        return response()->json($session, 201);
    }

    public function show(string $id): JsonResponse
    {
        $session = AcademicSession::with('terms')->findOrFail($id);

        return response()->json($session);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100', 'unique:academic_sessions,name,' . $id],
            'start_date' => ['sometimes', 'date'],
            'end_date'   => ['sometimes', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $session->update($validated);

        return response()->json($session->fresh('terms'));
    }

    public function destroy(string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);
        $session->delete();

        return response()->json(['message' => 'Academic session deleted.']);
    }

    /**
     * Set a session as the current one.
     * Only one session can be current at a time.
     */
    public function setCurrent(string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        if ($session->is_current) {
            return response()->json([
                'message' => "'{$session->name}' is already the current academic session.",
                'session' => $session->load('terms'),
            ]);
        }

        DB::transaction(function () use ($session) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
            
            $session->update(['is_current' => true]);

            \App\Models\Tenant\Term::where('is_current', true)->update(['is_current' => false]);
        });

        return response()->json([
            'message' => "'{$session->name}' is now the current academic session.",
            'session' => $session->fresh('terms'),
        ]);
    }
}