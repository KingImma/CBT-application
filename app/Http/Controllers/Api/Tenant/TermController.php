<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Terms\CreateTerm;
use App\Actions\Tenants\Terms\UpdateTerm;
use App\Data\Term\TermData;
use App\Exceptions\Domain\Session\TermAlreadyCurrentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTermRequest;
use App\Http\Requests\Tenant\UpdateTermRequest;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Academic Calendar
 * * APIs for defining school years and terms.
 */
class TermController extends Controller
{
    /**
     * List all terms for an academic session.
     *
     * @subgroup Terms
     *
     * @urlParam sessionId string required The academic session UUID.
     */
    public function index(string $sessionId): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        return ApiResponse::success(
            TermData::collect($session->terms()->orderBy('name')->get()),
            'Terms retrieved successfully.'
        );
    }

    /**
     * Create a new term within an academic session.
     *
     * @subgroup Terms
     *
     * @urlParam sessionId string required The academic session UUID.
     *
     * @bodyParam name string required Term name. Example: "First Term"
     * @bodyParam start_date string required Start date (Y-m-d). No-example
     * @bodyParam end_date string required End date (Y-m-d), must be after start_date. No-example
     * @bodyParam is_current boolean Set as the current term. No-example
     */
    public function store(StoreTermRequest $request, string $sessionId): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        $validated = $request->validated();

        $validated['tenant_id'] = $session->tenant_id;

        $term = (new CreateTerm)->execute(array_merge($validated, ['academic_session_id' => $sessionId]));

        return ApiResponse::created(TermData::from($term), 'Term created.');
    }

    /**
     * Update a term.
     *
     * @subgroup Terms
     *
     * @urlParam sessionId string required The academic session UUID.
     * @urlParam id string required The term UUID.
     *
     * @bodyParam name string Term name. No-example
     * @bodyParam start_date string Start date. No-example
     * @bodyParam end_date string End date. No-example
     * @bodyParam is_current boolean Set as current. No-example
     */
    public function update(UpdateTermRequest $request, string $sessionId, string $id): JsonResponse
    {
        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);

        $validated = $request->validated();

        $term = (new UpdateTerm)->execute($term, $validated);

        return ApiResponse::success(TermData::from($term->fresh()), 'Term updated.');
    }

    /**
     * Delete a term.
     *
     * @subgroup Terms
     *
     * @urlParam sessionId string required The academic session UUID.
     * @urlParam id string required The term UUID.
     */
    public function destroy(string $sessionId, string $id): JsonResponse
    {
        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);
        $term->delete();

        return ApiResponse::message('Term deleted.');
    }

    /**
     * Set a term as current within its session.
     * The session must already be current.
     *
     * @subgroup Terms
     */
    public function setCurrent(string $sessionId, string $id): JsonResponse
    {
        $session = AcademicSession::findOrFail($sessionId);

        if (! $session->is_current) {
            return ApiResponse::error('Set this academic session as current first before setting a current term.', 422);
        }

        $term = Term::where('academic_session_id', $sessionId)->findOrFail($id);

        try {
            $term->setAsCurrent()->save();
        } catch (TermAlreadyCurrentException $e) {
            return ApiResponse::success([
                'term' => TermData::from($term),
            ], $e->getMessage());
        }

        return ApiResponse::success([
            'term' => TermData::from($term->fresh()),
        ], "'{$term->name}' is now the current term.");
    }
}
