<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 9px; }
    h1 { text-align: center; color: #3b2d6e; margin-bottom: 2px; }
    .subtitle { text-align: center; font-weight: bold; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 3px 4px; text-align: center; }
    th { background: #f0f0f5; }
    td.name { text-align: left; }
</style>
</head>
<body>
    <h1>{{ $schoolName }}</h1>
    <div class="subtitle">
        BROADSHEET REPORT FOR {{ $broadsheet->meta->academic_session_name }} SESSION
        | CLASS: {{ $broadsheet->meta->class_level_name }}{{ $broadsheet->meta->class_arm_name ? ' '.$broadsheet->meta->class_arm_name : '' }}
        | TERM: {{ $broadsheet->meta->term_name }}
    </div>

    <table>
        <thead>
            <tr>
                <th>S/N</th>
                <th>Admission No.</th>
                <th>Name of Students</th>
                @foreach ($broadsheet->subjects as $subject)
                    <th>{{ $subject->name }}</th>
                @endforeach
                <th>Total No. of Subjects</th>
                <th>Total Marks Obtainable</th>
                <th>Total</th>
                <th>Average</th>
                <th>Position</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($broadsheet->students as $student)
                @php($scoresBySubject = $student->subjects->toCollection()->keyBy('subject_id'))
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $student->admission_number }}</td>
                    <td class="name">{{ strtoupper($student->full_name) }}</td>
                    @foreach ($broadsheet->subjects as $subject)
                        <td>{{ $scoresBySubject->get($subject->id)?->total ?? '-' }}</td>
                    @endforeach
                    <td>{{ $student->total_subjects }}</td>
                    <td>{{ $student->total_marks_obtainable }}</td>
                    <td>{{ $student->total_score }}</td>
                    <td>{{ $student->average_score }}</td>
                    <td>{{ $student->position }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>