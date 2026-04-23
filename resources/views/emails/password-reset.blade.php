<!--
  - What: password reset email for school admins and teachers
  - Does: shows role-specific context and a 60-minute reset link
  - Why role label: admin sees "school admin account", teacher sees "teacher account"
  - Expected: renders in Brevo Logs immediately after forgot-password request
  - Alternative: single template without role — slightly less clear for multi-role app
-->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset your EduCBT password</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:580px;width:100%;">

          <tr>
            <td style="background-color:#0B1F3A;padding:32px 40px;">
              <p style="margin:0;color:#D4AF37;font-size:22px;font-weight:700;">EduCBT</p>
            </td>
          </tr>

          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.7;">Hi <strong>{{ $name }}</strong>,</p>
              <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.7;">
                We received a request to reset the password for your EduCBT
                <strong>{{ $role }}</strong> account.
                Click the button below — this link is valid for <strong>60 minutes</strong>.
              </p>

              <table cellpadding="0" cellspacing="0" style="margin:8px 0 24px;">
                <tr>
                  <td style="background-color:#0B1F3A;border-radius:10px;">
                    <a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">
                      Reset my password →
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Warning -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;color:#9a3412;font-size:13px;line-height:1.6;">
                      If you did not request a password reset, you can safely ignore this email.
                      Your password will not change.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

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