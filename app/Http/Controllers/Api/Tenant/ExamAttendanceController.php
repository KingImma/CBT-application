<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Data\Results\BulkOperationResult;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttendance;
use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamAttendanceController extends Controller
{
    public function classStudents(string $examId, Request $request): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('viewMonitoring', $exam);

        $students = User::select('id', 'first_name', 'last_name')
            ->whereHas('studentProfile', function ($q) use ($exam) {
                $q->where('class_level_id', $exam->class_level_id);
                if ($exam->class_arm_id) {
                    $q->where('class_arm_id', $exam->class_arm_id);
                }
            })
            ->orderBy('last_name')
            ->paginate(200);

        $attendanceRecords = $exam->attendanceRecords()
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $data = $students->getCollection()->map(function ($student) use ($attendanceRecords) {
            $record = $attendanceRecords->get($student->id);

            return [
                'student_id' => $student->id,
                'student_name' => $student->first_name.' '.$student->last_name,
                'status' => $record?->status ?? null,
                'marked_at' => $record?->marked_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($students, 'Class students retrieved.', $data);
    }

    public function batchStore(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('viewMonitoring', $exam);

        $validated = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*.student_id' => ['required', 'uuid', 'exists:users,id'],
            'attendance.*.status' => ['required', 'in:present,absent,awp'],
        ]);

        $successCount = 0;
        $failures = [];

        foreach ($validated['attendance'] as $item) {
            try {
                ExamAttendance::updateOrCreate(
                    ['exam_id' => $examId, 'student_id' => $item['student_id']],
                    [
                        'status' => $item['status'],
                        'marked_by' => $request->user('tenant')->id,
                        'marked_at' => now(),
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                $failures[] = ['student_id' => $item['student_id'], 'error' => $e->getMessage()];
            }
        }

        $result = BulkOperationResult::fromLoop($successCount, count($failures), $failures);

        return ApiResponse::success(
            $result->toArray(),
            $result->message ?? 'Batch attendance completed.'
        );
    }

    public function update(Request $request, string $examId, string $studentId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('viewMonitoring', $exam);

        $validated = $request->validate([
            'status' => ['required', 'in:present,absent,awp'],
        ]);

        $record = ExamAttendance::updateOrCreate(
            ['exam_id' => $examId, 'student_id' => $studentId],
            [
                'status' => $validated['status'],
                'marked_by' => $request->user('tenant')->id,
                'marked_at' => now(),
            ]
        );

        return ApiResponse::success($record, 'Attendance updated.');
    }
}
