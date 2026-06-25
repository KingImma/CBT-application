{{-- resources/views/emails/exam-result.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam Result</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .card { background: #fff; max-width: 560px; margin: 32px auto; border-radius: 8px;
            padding: 32px; border-top: 4px solid #4F46E5; }
    h2   { color: #111; margin-top: 0; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 20px;
             font-weight: bold; font-size: 14px; }
    .passed { background: #D1FAE5; color: #065F46; }
    .failed { background: #FEE2E2; color: #991B1B; }
    table  { width: 100%; border-collapse: collapse; margin: 20px 0; }
    td     { padding: 10px 8px; border-bottom: 1px solid #E5E7EB; font-size: 15px; }
    td:first-child { color: #6B7280; }
    td:last-child  { font-weight: 600; text-align: right; }
    .btn { display: block; background: #4F46E5; color: #fff; text-align: center;
           padding: 14px; border-radius: 6px; text-decoration: none;
           font-weight: bold; margin: 24px 0; }
    .footer { font-size: 13px; color: #9CA3AF; text-align: center; margin-top: 24px; }
  </style>
</head>
<body>
<div class="card">

  <h2>{{ $data->schoolName }}</h2>
  <p>Hi {{ $data->studentName }},</p>
  <p>
    Your result for <strong>{{ $data->examTitle }}</strong> has been released.
    Here is a summary of your performance.
  </p>

  <table>
    <tr><td>Exam</td>      <td>{{ $data->examTitle }}</td></tr>
    <tr><td>Subject</td>   <td>{{ $data->subjectName }}</td></tr>
    <tr><td>Class</td>     <td>{{ $data->className }}</td></tr>
    <tr><td>Score</td>     <td>{{ $data->score }} / {{ $data->totalMarks }}</td></tr>
    <tr><td>Percentage</td><td>{{ number_format($data->percentage, 1) }}%</td></tr>
    @if ($data->grade)
    <tr><td>Grade</td>     <td>{{ $data->grade }}</td></tr>
    @endif
    <tr>
      <td>Status</td>
      <td>
        <span class="badge {{ strtolower($data->status) }}">
          {{ $data->status }}
        </span>
      </td>
    </tr>
    <tr><td>Released On</td><td>{{ $data->releasedOn }}</td></tr>
  </table>

  <a href="{{ $data->portalLink }}" class="btn">View Full Result</a>

  <p style="font-size: 14px; color: #374151;">
    If you have questions about your result, please contact your school administrator.
  </p>

  <div class="footer">
    This email was sent by EduCBT on behalf of {{ $data->schoolName }}.
  </div>
</div>
</body>
</html>
