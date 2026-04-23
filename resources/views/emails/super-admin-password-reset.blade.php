<!--
  - What: password reset email for the EduCBT super admin
  - Does: distinct dark indigo header differentiates it visually from school-level emails
  - Why separate template: super admin reset URL goes to /admin/reset-password, not a school URL
  - Expected: super admin receives this on forgot-password from the central login page
  - Alternative: reuse password-reset.blade.php with a URL param — loses visual distinction
-->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset your Admin password</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:580px;width:100%;">

          <tr>
            <td style="background-color:#1e1b4b;padding:32px 40px;">
              <p style="margin:0;color:#a5b4fc;font-size:22px;font-weight:700;">EduCBT Admin</p>
              <p style="margin:6px 0 0;color:#6d7da8;font-size:13px;">Platform Administration</p>
            </td>
          </tr>

          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.7;">Hi <strong>{{ $name }}</strong>,</p>
              <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.7;">
                A password reset was requested for your EduCBT Super Admin account.
                This link expires in <strong>60 minutes</strong>.
              </p>

              <table cellpadding="0" cellspacing="0" style="margin:8px 0 24px;">
                <tr>
                  <td style="background-color:#1e1b4b;border-radius:10px;">
                    <a href="{{ $resetUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">
                      Reset Admin Password →
                    </a>
                  </td>
                </tr>
              </table>

              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;color:#9a3412;font-size:13px;line-height:1.6;">
                      If you didn't request this, contact the platform owner immediately.
                      This link expires in 60 minutes.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:20px 40px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
              <p style="margin:0;color:#94a3b8;font-size:12px;">EduCBT Platform · Internal Use Only</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>