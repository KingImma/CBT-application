
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: center; }
        th { background: #f0f0f0; }
        td.name { text-align: left; }
        td.total, th.total { background: #f7f7f7; font-weight: bold; }
        tfoot td { font-weight: bold; background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>{{ $schoolName }} — Broadsheet</h1>
    <div class="meta">
        Class: {{ $broadsheet->meta->class_level_name }}{{ $broadsheet->meta->class_arm_name ? ' / '.$broadsheet->meta->class_arm_name : '' }}
        | Term: {{ $broadsheet->meta->term_name }}
        | Session: {{ $broadsheet->meta->academic_session_name }}
    </div>

    @php
        $classSubjects = $broadsheet->subjects;
    @endphp

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                @foreach ($classSubjects as $subject)
                    <th colspan="3">{{ $subject->name }}</th>
                @endforeach
                <th class="total">Total</th>
                <th class="total">Avg</th>
                <th>Pos</th>
            </tr>
            <tr>
                <th></th>
                <th></th>
                @foreach ($classSubjects as $subject)
                    <th>CA</th><th>Exam</th><th>Tot</th>
                @endforeach
                <th class="total"></th>
                <th class="total"></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($broadsheet->students as $index => $student)
                @php
                    // Key each student's scores by subject_id, NOT by position.
                    //
                    // Do NOT use collect($student->subjects) here: a Spatie
                    // DataCollection implements Arrayable, so collect() calls
                    // toArray() and turns every score into a plain array. Then
                    // $score->ca / ->exam / ->total silently read null and print
                    // 0, even when the student has real scores.
                    //
                    // Iterating the DataCollection directly goes through its
                    // IteratorAggregate, which yields the StudentSubjectScoreData
                    // objects untouched.
                    $scoresBySubject = [];
                    foreach ($student->subjects as $score) {
                        $scoresBySubject[(string) $score->subject_id] = $score;
                    }
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="name">{{ $student->full_name }}</td>
                    @foreach ($classSubjects as $subject)
                        @php $score = $scoresBySubject[(string) $subject->id] ?? null; @endphp
                        <td>{{ $score?->ca ?? 0 }}</td>
                        <td>{{ $score?->exam ?? 0 }}</td>
                        <td>{{ $score?->total ?? 0 }}</td>
                    @endforeach
                    <td class="total">{{ $student->total_score }}</td>
                    <td class="total">{{ $student->average_score }}</td>
                    <td>{{ $student->position }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Class Average</td>
                @foreach ($classSubjects as $subject)
                    <td colspan="3">{{ $subject->class_average }}</td>
                @endforeach
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
