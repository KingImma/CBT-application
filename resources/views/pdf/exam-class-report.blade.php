<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 9px; }
    h1 { text-align: center; color: #3b2d6e; margin-bottom: 2px; }
    .subtitle { text-align: center; font-weight: bold; margin-bottom: 10px; }
    .meta { margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 3px 4px; text-align: center; }
    th { background: #f0f0f5; }
    td.name { text-align: left; }
</style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <div class="subtitle">
        EXAM REPORT FOR {{ strtoupper($report->summary->exam_name) }}
        | CLASS: {{ strtoupper($report->summary->class_arm_name) }}
    </div>

    <div class="meta">
        <span>Students in class: {{ $report->summary->students_in_class }}</span>
        <span>Students sat: {{ $report->summary->students_sat }}</span>
        <span>Average score: {{ $report->summary->average_score ?? '-' }}</span>
        <span>Highest: {{ $report->summary->highest_score ?? '-' }}</span>
        <span>Lowest: {{ $report->summary->lowest_score ?? '-' }}</span>
        <span>Passed: {{ $report->summary->pass_count ?? '-' }}</span>
        <span>Failed: {{ $report->summary->fail_count ?? '-' }}</span>
        <span>Completion: {{ $report->summary->completion_rate !== null ? round($report->summary->completion_rate, 1).'%' : '-' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>S/N</th>
                <th>Name of Student</th>
                <th>Score</th>
                <th>Percentage (%)</th>
                <th>Grade</th>
                <th>Status</th>
                <th>Submitted</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->students as $student)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="name">{{ strtoupper($student->student_name) }}</td>
                    <td>{{ $student->score ?? '-' }}</td>
                    <td>{{ $student->percentage !== null ? round($student->percentage, 2) : '-' }}</td>
                    <td>{{ $student->grade ?? '-' }}</td>
                    <td>{{ $student->result_status ?? '-' }}</td>
                    <td>{{ $student->submitted_at ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No completed attempts recorded for this class and exam.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>