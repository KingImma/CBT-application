<!--
  - What: Password changed confirmation email
  - Does: notifies the user that their password was successfully changed
  - Why: security best practice — users should be alerted to account changes
  - Expected: renders in Gmail, Outlook, and Mailtrap
-->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password changed</title>
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
              <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.7;">Hi <strong>{{ $firstName }}</strong>,</p>
              <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.7;">
                Your password for <strong>{{ $schoolName }}</strong> was successfully changed.
              </p>

              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin:24px 0;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0;color:#16a34a;font-size:13px;line-height:1.6;">
                      If you made this change, no further action is needed.
                    </p>
                  </td>
                </tr>
              </table>

              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;color:#9a3412;font-size:13px;line-height:1.6;">
                      If you did <strong>not</strong> change your password, please contact your school administrator
                      or <a href="mailto:support@educbt.com" style="color:#9a3412;">support@educbt.com</a> immediately.
                    </p>
                  </td>
                </tr>
              </table>
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
