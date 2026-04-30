<!--
  - What: OTP password reset email for all user types
  - Does: displays 6-digit code with school branding
  - Why OTP not link: password reset uses time-limited codes, not magic links
  - Expected: user receives code valid for 15 minutes
  - Alternative: email with reset link — requires different flow
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
              <p style="margin:6px 0 0;color:#94a3b8;font-size:13px;">{{ $schoolName }}</p>
            </td>
          </tr>
          
          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.7;">Hi there,</p>
              <p style="margin:0 0 24px;color:#334155;font-size:15px;line-height:1.7;">
                We received a request to reset your password. Use the code below — it's valid for <strong>15 minutes</strong>.
              </p>
              
              <!-- OTP Code Box -->
              <table cellpadding="0" cellspacing="0" style="margin:8px 0 24px;width:100%;">
                <tr>
                  <td align="center" style="background-color:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:24px;">
                    <p style="margin:0 0 8px;color:#64748b;font-size:13px;letter-spacing:1px;">YOUR RESET CODE</p>
                    <p style="margin:0;color:#0B1F3A;font-size:36px;font-weight:700;letter-spacing:8px;font-family:'Courier New',monospace;">{{ $otp }}</p>
                  </td>
                </tr>
              </table>
              
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;color:#9a3412;font-size:13px;line-height:1.6;">
                      If you didn't request a password reset, you can safely ignore this email. 
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
