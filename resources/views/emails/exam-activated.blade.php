<!--
  - What: Exam activated notification for students
  - Does: notifies students that a new exam is available for them to take
  - Why: students need to know when exams are published/activated
  - Expected: renders in Gmail, Outlook, and Mailtrap
-->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Exam now available</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:580px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="background-color:#0B1F3A;padding:32px 40px;">
              <p style="margin:0;color:#D4AF37;font-size:22px;font-weight:700;">EduCBT</p>
              <p style="margin:6px 0 0;color:#94a3b8;font-size:13px;">{{ $schoolName }}</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.7;">Hi <strong>{{ $studentName }}</strong>,</p>
              <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.7;">
                A new exam is now available for you:
              </p>

              <!-- Exam Details Box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;margin:24px 0;">
                <tr>
                  <td style="padding:20px;">
                    <p style="margin:0 0 12px;color:#0B1F3A;font-size:18px;font-weight:700;">{{ $exam->title }}</p>
                    <table cellpadding="0" cellspacing="0" width="100%">
                      <tr>
                        <td style="padding:4px 0;color:#64748b;font-size:14px;width:50%;">Subject</td>
                        <td style="padding:4px 0;color:#334155;font-size:14px;font-weight:600;">{{ $exam->subject?->name ?? '-' }}</td>
                      </tr>
                      <tr>
                        <td style="padding:4px 0;color:#64748b;font-size:14px;width:50%;">Duration</td>
                        <td style="padding:4px 0;color:#334155;font-size:14px;font-weight:600;">{{ $exam->duration_minutes }} minutes</td>
                      </tr>
                      <tr>
                        <td style="padding:4px 0;color:#64748b;font-size:14px;width:50%;">Total Marks</td>
                        <td style="padding:4px 0;color:#334155;font-size:14px;font-weight:600;">{{ $exam->total_marks }}</td>
                      </tr>
                      @if ($exam->scheduled_start)
                        <tr>
                          <td style="padding:4px 0;color:#64748b;font-size:14px;width:50%;">Available from</td>
                          <td style="padding:4px 0;color:#334155;font-size:14px;font-weight:600;">{{ $exam->scheduled_start->format('d M Y, g:i A') }}</td>
                        </tr>
                      @endif
                    </table>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.7;">
                Log in to your account to start the exam. Make sure you have a stable internet connection before you begin.
              </p>

              @if ($exam->max_attempts > 1)
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;">
                  <tr>
                    <td style="padding:14px 18px;">
                      <p style="margin:0;color:#1e40af;font-size:13px;line-height:1.6;">
                        You have <strong>{{ $exam->max_attempts }} attempts</strong> for this exam.
                        Your highest score will be recorded.
                      </p>
                    </td>
                  </tr>
                </table>
              @endif
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
              <p style="margin:0;color:#94a3b8;font-size:12px;">EduCBT · Nigeria · support@educbt.com</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
