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

class AcademicSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = AcademicSession::with('terms')
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::success($sessions, 'Academic sessions retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:academic_sessions,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $session = AcademicSession::create($validated);

        return ApiResponse::created($session, 'Academic session created.');
    }

    public function show(string $id): JsonResponse
    {
        $session = AcademicSession::with('terms')->findOrFail($id);

        return ApiResponse::success($session, 'Academic session retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:academic_sessions,name,'.$id],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $session->update($validated);

        return ApiResponse::success($session->fresh('terms'), 'Academic session updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);
        $session->delete();

        return ApiResponse::message('Academic session deleted.');
    }

    /**
     * Set a session as the current one.
     * Only one session can be current at a time.
     */
    public function setCurrent(string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($id);

        if ($session->is_current) {
            return ApiResponse::success([
                'session' => $session->load('terms'),
            ], "'{$session->name}' is already the current academic session.");
        }

        DB::transaction(function () use ($session) {
            AcademicSession::where('is_current', true)->update(['is_current' => false]);

            $session->update(['is_current' => true]);

            Term::where('is_current', true)->update(['is_current' => false]);
        });

        return ApiResponse::success([
            'session' => $session->fresh('terms'),
        ], "'{$session->name}' is now the current academic session.");
    }
}
