<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf.partials._exam-result-styles')
</head>
<body>
@include('pdf.partials.exam-result-slip', ['attempt' => $attempt, 'result' => $result, 'schoolName' => $schoolName])
</body>
</html>