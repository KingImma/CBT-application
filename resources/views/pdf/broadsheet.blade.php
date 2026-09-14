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
        Class: {{ $broadsheet->meta->class_level_id }}{{ $broadsheet->meta->class_arm_id ? ' / '.$broadsheet->meta->class_arm_id : '' }}
        | Term: {{ $broadsheet->meta->term_id }}
        | Session: {{ $broadsheet->meta->academic_session_id }}
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">#</th>
                <th rowspan="2" style="text-align:left;">Student</th>
                @foreach ($broadsheet->subjects as $subject)
                    <th colspan="3">{{ $subject->name }}</th>
                @endforeach
                <th class="total" rowspan="2">Total</th>
                <th class="total" rowspan="2">Avg</th>
                <th rowspan="2">Pos</th>
            </tr>
            <tr>
                @foreach ($broadsheet->subjects as $subject)
                    <th>CA</th><th>Exam</th><th>Tot</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($broadsheet->students as $index => $student)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="name">{{ $student->full_name }}</td>
                    {{-- zipped by index: subjects array is sorted by subject name on both sides --}}
                    @foreach ($student->subjects as $score)
                        <td>{{ $score->ca }}</td>
                        <td>{{ $score->exam }}</td>
                        <td>{{ $score->total }}</td>
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
                @foreach ($broadsheet->subjects as $subject)
                    <td colspan="3">{{ $subject->class_average }}</td>
                @endforeach
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>