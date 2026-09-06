<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #333; padding-bottom: 8px; }
    .header h1 { margin: 0; font-size: 18px; }
    .summary-grid { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
    .summary-grid td { border: 1px solid #ccc; padding: 6px 10px; font-size: 11px; }
    .summary-grid .label { font-weight: bold; background: #f2f2f2; width: 20%; }
    table.roster { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.roster th, table.roster td { border: 1px solid #ccc; padding: 5px 8px; font-size: 10px; text-align: left; }
    table.roster th { background: #f2f2f2; }
    .status-passed { color: #1a7a1a; }
    .status-failed { color: #b30000; }
    .status-not_attempted { color: #888; }
</style>
</head>
<body>
    <div class="header">
        <h1>{{ $schoolName }}</h1>
        <p>{{ $report->summary->exam_name }} — {{ $report->summary->class_arm_name }} — Class Report</p>
    </div>

    <table class="summary-grid">
        <tr>
            <td class="label">Students in Class</td><td>{{ $report->summary->students_in_class }}</td>
            <td class="label">Students Sat</td><td>{{ $report->summary->students_sat }}</td>
        </tr>
        <tr>
            <td class="label">Average Score</td><td>{{ $report->summary->average_score ?? 'N/A' }}%</td>
            <td class="label">Completion</td><td>{{ $report->summary->completion_rate }}% ({{ $report->summary->completion_status }})</td>
        </tr>
        <tr>
            <td class="label">Highest Score</td><td>{{ $report->summary->highest_score ?? 'N/A' }}</td>
            <td class="label">Lowest Score</td><td>{{ $report->summary->lowest_score ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Passed</td><td>{{ $report->summary->pass_count ?? 'N/A' }}</td>
            <td class="label">Failed</td><td>{{ $report->summary->fail_count ?? 'N/A' }}</td>
        </tr>
    </table>

    <table class="roster">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:30%">Student</th>
                <th style="width:12%">Score</th>
                <th style="width:12%">Percentage</th>
                <th style="width:10%">Grade</th>
                <th style="width:16%">Status</th>
                <th style="width:15%">Submitted</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->students as $i => $s)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $s->student_name }}</td>
                    <td>{{ $s->score ?? '—' }}</td>
                    <td>{{ $s->percentage ?? '—' }}</td>
                    <td>{{ $s->grade ?? '—' }}</td>
                    <td class="status-{{ $s->result_status }}">{{ ucfirst(str_replace('_', ' ', $s->result_status)) }}</td>
                    <td>{{ $s->submitted_at ? \Illuminate\Support\Carbon::parse($s->submitted_at)->format('M j, g:ia') : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>