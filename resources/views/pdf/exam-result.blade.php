<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>{{ $schoolName }} — Result</title></head>
<body>
    <h2>{{ $schoolName }}</h2>
    <h3>{{ $result->exam_title }}</h3>
    <p>Student: {{ $attempt->student->first_name }} {{ $attempt->student->last_name }}</p>
    <p>Score: {{ $result->total_score }} / {{ $result->total_marks }} ({{ $result->percentage_score }}%)</p>
    <p>Grade: {{ $result->grade }}</p>

    <table border="1" cellpadding="6" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr><th>#</th><th>Question</th><th>Marks Awarded</th><th>Marks Available</th></tr>
        </thead>
        <tbody>
            @foreach ($result->questions as $i => $q)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $q->content }}</td>
                    <td>{{ $q->marks_awarded }}</td>
                    <td>{{ $q->marks_available }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>