<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('pdf.partials._result-sheet-styles')
</head>

<body>
    @foreach ($results as $index => $result)
        <div @if ($index > 0) style="page-break-before: always;" @endif>
            @include('pdf.partials.student-result-sheet', [
                'result' => $result,
                'school' => $school,
            ])
        </div>
    @endforeach
</body>
</html>
