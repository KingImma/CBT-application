<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 10px; }
    h1 { text-align: center; margin-bottom: 2px; }
    .subtitle { text-align: center; font-weight: bold; margin-bottom: 10px; }
    .meta span { display: inline-block; width: 24%; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 4px; text-align: center; }
    th { background: #f0f0f5; }
    td.subject { text-align: left; }
</style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <div class="subtitle">RESULT SHEET — {{ $result->academic_session_name }}</div>

    <div class="meta">
        <span>Name: {{ $result->student_name }}</span>
        <span>Admission No: {{ $result->admission_number }}</span>
        <span>Class: {{ $result->class_level_name }}{{ $result->class_arm_name ? ' '.$result->class_arm_name : '' }}</span>
        <span>Term: {{ $result->terms[0]['name'] ?? '' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result->subjects as $row)
                @php($cell = $row->terms[$result->terms[0]['id']] ?? null)
                <tr>
                    <td class="subject">{{ $row->subject_name }}</td>
                    <td>{{ $cell['total'] ?? '-' }}</td>
                    <td>{{ $cell['grade'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>