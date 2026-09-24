<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@include('pdf.partials._result-sheet-styles')
</head>
<body>
@include('pdf.partials.student-result-sheet', ['result' => $result, 'school' => $school])
</body>
</html>