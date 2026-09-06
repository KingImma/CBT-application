<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf.partials._exam-result-styles')
</head>
<body>
@foreach ($items as $i => $item)
    <div style="{{ $i > 0 ? 'page-break-before: always;' : '' }}">
        @include('pdf.partials.exam-result-slip', [
            'attempt' => $item['attempt'],
            'result' => $item['result'],
            'schoolName' => $schoolName,
        ])
    </div>
@endforeach
</body>
</html>