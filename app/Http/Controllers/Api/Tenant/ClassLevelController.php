<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;


class ClassLevelController extends Controller
{
    public function index(): JsonResponse
    {
        $levels = ClassLevel::withCount(['classArms', 'students'])
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $levels,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100', 'unique:class_levels,name'],
            'category' => ['required', 'string', 'in:junior,senior'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);
    
        // Auto-generate the slug based on the name
        $validated['slug'] = Str::slug($validated['name']);
    
        // Auto-calculate the order if not provided
        if (empty($validated['order'])) {
            $maxOrder = ClassLevel::max('order') ?? 0;
            
            if ($maxOrder >= 6) {
                throw ValidationException::withMessages([
                    'order' => ['Maximum class hierarchy reached. The system only auto-assigns up to 6 levels.']
                ]);
            }
    
            $validated['order'] = $maxOrder + 1;
        }
    
        $level = ClassLevel::create($validated);
    
        return response()->json([
            'success' => true,
            'message' => 'Class level created.',
            'data'    => $level,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $level = ClassLevel::withCount(['classArms', 'students'])
            ->with(['classArms', 'subjects'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $level,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $level = ClassLevel::findOrFail($id);

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100', 'unique:class_levels,name,' . $id],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);
        
        if (isset($validated['name']) && $validated['name'] !== $level->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if (empty($validated['order'])) {
            unset($validated['order']); 
        }

        $level->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Class level updated.',
            'data'    => $level->fresh(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $level = ClassLevel::withCount('students')->findOrFail($id);

        // Prevent deletion if students are assigned — data integrity
        if ($level->students_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete — {$level->students_count} student(s) are assigned to this class level.",
            ], 422);
        }

        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class level deleted.',
        ]);
    }
}