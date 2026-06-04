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
    /**
     * List students in the exam's class with their attendance status.
     *
     * @subgroup Exam Attendance
     *
     * @urlParam examId string required The exam UUID.
     */
    public function classStudents(string $examId, Request $request): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('viewMonitoring', $exam);

        $students = User::select('id', 'email', 'first_name', 'last_name')
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
                'email' => $student->email,
                'student_name' => $student->first_name.' '.$student->last_name,
                'status' => $record?->status ?? null,
                'marked_at' => $record?->marked_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($students, 'Class students retrieved.', $data);
    }

    /**
     * Mark attendance for multiple students at once.
     *
     * @subgroup Exam Attendance
     *
     * @urlParam examId string required The exam UUID.
     *
     * @bodyParam attendance array required Array of attendance records. No-example
     * @bodyParam attendance.*.student_id string required The student UUID. No-example
     * @bodyParam attendance.*.status string required Attendance status: present, absent, or awp. No-example
     */
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

    /**
     * Update attendance for a single student.
     *
     * @subgroup Exam Attendance
     *
     * @urlParam examId string required The exam UUID.
     * @urlParam studentId string required The student UUID.
     *
     * @bodyParam status string required Attendance status: present, absent, or awp. Example: "present"
     */
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
