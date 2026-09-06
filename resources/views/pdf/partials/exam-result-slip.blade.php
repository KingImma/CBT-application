<div class="header">
    <h1>{{ $schoolName }}</h1>
    <p>Exam Result Slip</p>
</div>

<table class="meta-table">
    <tr>
        <td><strong>Student:</strong> {{ $attempt->student->first_name }} {{ $attempt->student->last_name }}</td>
        <td><strong>Exam:</strong> {{ $result->exam_title }}</td>
    </tr>
    <tr>
        <td><strong>Attempt #:</strong> {{ $result->attempt_number }}</td>
        <td><strong>Submitted:</strong> {{ $result->submitted_at ?? 'N/A' }}</td>
    </tr>
</table>

<div class="score-box">
    <div class="big">{{ $result->total_score }} / {{ $result->total_marks }}</div>
    <div>{{ $result->percentage_score }}% &mdash; Grade: {{ $result->grade ?? 'N/A' }}</div>
</div>

<table class="questions">
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:45%">Question</th>
            <th style="width:15%">Result</th>
            <th style="width:15%">Marks Awarded</th>
            <th style="width:15%">Marks Available</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($result->questions as $i => $q)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ strip_tags($q->content) }}</td>
                <td class="{{ $q->is_correct ? 'correct' : 'incorrect' }}">
                    {{ $q->is_correct ? 'Correct' : 'Incorrect' }}
                </td>
                <td>{{ $q->marks_awarded }}</td>
                <td>{{ $q->marks_available }}</td>
            </tr>
        @endforeach
    </tbody>
</table>