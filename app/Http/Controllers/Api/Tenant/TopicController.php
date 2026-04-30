<?php

// - CRUD for topics nested under subject + class level
// - Handles parent_id for sub-topics; returns children inline on show
// - Chosen: dual-scoped (subject + class level) matches schema unique constraint
// - Deliverable: topic tree management per subject per class level
// - Alternative: flat topics with client-side grouping — loses server-enforced hierarchy

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TopicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id'     => ['required', 'uuid', 'exists:subjects,id'],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
        ]);

        // Return only root topics (parent_id = null); children are nested inside
        $topics = Topic::where('subject_id', $request->subject_id)
            ->where('class_level_id', $request->class_level_id)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('questions')])
            ->withCount('questions')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $topics]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id'     => ['required', 'uuid', 'exists:subjects,id'],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'parent_id'      => ['nullable', 'uuid', 'exists:topics,id'],
            'name'           => [
                'required', 'string', 'max:255',
                Rule::unique('topics')->where(fn ($q) =>
                    $q->where('subject_id', $request->subject_id)
                      ->where('class_level_id', $request->class_level_id)
                      ->where('parent_id', $request->parent_id)
                ),
            ],
            'order' => ['nullable', 'integer'],
        ]);

        $topic = Topic::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Topic created.',
            'data'    => $topic->load('parent'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $topic = Topic::with([
            'subject',
            'classLevel',
            'parent',
            'children.children', // two levels deep
        ])->withCount('questions')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $topic]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $topic = Topic::findOrFail($id);

        $validated = $request->validate([
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:topics,id'],
            'name'      => [
                'sometimes', 'string', 'max:255',
                Rule::unique('topics')
                    ->where(fn ($q) =>
                        $q->where('subject_id', $topic->subject_id)
                          ->where('class_level_id', $topic->class_level_id)
                          ->where('parent_id', $request->parent_id ?? $topic->parent_id)
                    )
                    ->ignore($id),
            ],
            'order' => ['sometimes', 'nullable', 'integer'],
        ]);

        // Prevent self-referencing
        if (($validated['parent_id'] ?? null) === $id) {
            return response()->json([
                'success' => false,
                'message' => 'A topic cannot be its own parent.',
            ], 422);
        }

        $topic->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Topic updated.',
            'data'    => $topic->fresh('parent'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $topic = Topic::withCount(['questions', 'children'])->findOrFail($id);

        if ($topic->questions_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete — {$topic->questions_count} question(s) belong to this topic.",
            ], 422);
        }

        if ($topic->children_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete — {$topic->children_count} sub-topic(s) exist under this topic.",
            ], 422);
        }

        $topic->delete();

        return response()->json(['success' => true, 'message' => 'Topic deleted.']);
    }
}