<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Subject;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\AcademicSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessionId = $request->query("academic_session_id");

        if (!$sessionId) {
            $currentSession = AcademicSession::where(
                "is_current",
                true,
            )->first();
            $sessionId = $currentSession?->id;
        }

        $subjects = Subject::with([
            "classLevels",

            // 1. Target the assignments table to filter by session
            "teacherAssignments" => function ($query) use ($sessionId) {
                if ($sessionId) {
                    $query->where("academic_session_id", $sessionId);
                }
                
                // 2. Chain the user relationship inside the closure, selecting valid columns
                $query->with(["user:id,first_name,last_name"]);
            },
        ])
            ->where("is_active", true)
            ->orderBy("name")
            ->get();

        return response()->json($subjects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "name" => ["required", "string", "max:100"],
            "code" => ["nullable", "string", "max:20", "unique:subjects,code"],
            "class_level_ids" => ["nullable", "array"],
            "class_level_ids.*" => ["uuid", "exists:class_levels,id"],
        ]);

        $subject = Subject::create([
            "name" => $validated["name"],
            "code" => $validated["code"] ?? null,
            "is_active" => true,
        ]);

        if (!empty($validated["class_level_ids"])) {
            $subject->classLevels()->sync($validated["class_level_ids"]);
        }

        return response()->json($subject->load("classLevels"), 201);
    }

    public function show(string $id): JsonResponse
    {
        $subject = Subject::with([
            "classLevels",
            "teacherAssignments.teacher.user",
        ])->findOrFail($id);

        return response()->json($subject);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            "name" => ["sometimes", "string", "max:100"],
            "code" => [
                "sometimes",
                "nullable",
                "string",
                "max:20",
                "unique:subjects,code," . $id,
            ],
            "is_active" => ["sometimes", "boolean"],
            "class_level_ids" => ["sometimes", "array"],
            "class_level_ids.*" => ["uuid", "exists:class_levels,id"],
        ]);

        $subject->update(
            collect($validated)->except("class_level_ids")->toArray(),
        );

        if (isset($validated["class_level_ids"])) {
            $subject->classLevels()->sync($validated["class_level_ids"]);
        }

        return response()->json($subject->fresh(["classLevels"]));
    }

    public function destroy(string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);
        $subject->update(["is_active" => false]); // soft disable, not hard delete

        return response()->json(["message" => "Subject deactivated."]);
    }

    /**
     * Assign a teacher to a subject within a class level.
     */
    public function assignTeacher(Request $request, string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            "user_id" => [
                "required",
                "uuid",
                "exists:users,id",
                function ($attribute, $value, $fail) {
                    $user = \App\Models\Tenant\User::find($value);
                    if ($user || !$user->is_teacher) {
                        $fail("The selected user must be a registered teacher.");
                    }
                },
            ],
            "class_level_id" => [
                "required",
                "uuid",
                "exists:class_levels,id",
            ],
            "academic_session_id" => [
                "required",
                "uuid",
                "exists:academic_sessions,id",
            ],
        ]);

        // Prevent duplicate assignment
        $exists = \App\Models\Tenant\TeacherSubjectAssignment::where([
            "subject_id" => $subject->id,
            "user_id" => $validated["user_id"],
            "class_level_id" => $validated["class_level_id"],
            "academic_session_id" => $validated["academic_session_id"],
        ])->exists();

        if ($exists) {
            return response()->json(
                [
                    "message" =>
                        "This teacher is already assigned to this subject for this class level.",
                ],
                422,
            );
        }

        $assignment = \App\Models\Tenant\TeacherSubjectAssignment::create([
            "subject_id" => $subject->id,
            "user_id" => $validated["user_id"],
            "class_level_id" => $validated["class_level_id"],
            "academic_session_id" => $validated["academic_session_id"],
        ]);

        // Load the new direct user relationship
        return response()->json(
            $assignment->load(["user", "classLevel", "academicSession"]),
            201,
        );
    }

    /**
     * Remove a teacher from a subject assignment.
     */
    public function removeTeacher(
        string $id,
        string $assignmentId,
    ): JsonResponse {
        $assignment = \App\Models\Tenant\TeacherSubjectAssignment::where(
            "subject_id",
            $id,
        )->findOrFail($assignmentId);

        $assignment->delete();

        return response()->json(["message" => "Teacher assignment removed."]);
    }
}
