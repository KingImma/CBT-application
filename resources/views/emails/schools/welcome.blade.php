<!-- 
  - What: HTML welcome email for new school admins
  - Does: shows school URL, login button, and support contact
  - Why inline styles: email clients strip external CSS — inline is the only reliable option
  - Expected: renders correctly in Gmail, Outlook, and Brevo's preview tool
  - Alternative: MJML templates — better maintainability but requires a build step
-->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to EduCBT</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:580px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="background-color:#0B1F3A;padding:32px 40px;">
              <p style="margin:0;color:#D4AF37;font-size:22px;font-weight:700;letter-spacing:-0.3px;">EduCBT</p>
              <p style="margin:6px 0 0;color:#94a3b8;font-size:13px;">Computer-Based Testing for Nigerian Schools</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.7;">Hi <strong>{{ $adminName }}</strong>,</p>
              <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.7;">
                Your school <strong>{{ $schoolName }}</strong> has been successfully set up on EduCBT.
                You can now log in and start adding teachers, students, and setting up exams.
              </p>

              <!-- School URL box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin:24px 0;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0 0 4px;color:#16a34a;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;">Your school's URL</p>
                    <a href="{{ $loginUrl }}" style="color:#15803d;font-size:15px;font-weight:700;text-decoration:none;">
                      {{ $handle }}.{{ config('app.central_domain') }}
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.7;">
                Log in with <strong>{{ $adminEmail }}</strong> and the password you created during registration.
              </p>

              <!-- CTA button -->
              <table cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background-color:#0B1F3A;border-radius:10px;">
                    <a href="{{ $loginUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">
                      Log in to your school →
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:28px 0 0;color:#94a3b8;font-size:13px;line-height:1.6;">
                If you did not create this account, please contact
                <a href="mailto:support@educbt.com" style="color:#64748b;">support@educbt.com</a> immediately.
              </p>
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