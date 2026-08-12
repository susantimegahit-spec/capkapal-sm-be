<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
  <title>Reset Password — Garam Capkapal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; background-color: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
    @media only screen and (max-width: 600px) {
      .email-container { width: 100% !important; margin: auto !important; }
      .content-wrapper { padding: 24px 20px !important; }
      .header-wrapper { padding: 24px 20px !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #374151; -webkit-font-smoothing: antialiased;">

  <!-- Preheader -->
  <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; color: #f9fafb; opacity: 0;">
    Permintaan reset password untuk akun Garam Capkapal Anda. Tautan ini berlaku {{ $expireMinutes }} menit.
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; width: 100%;">
    <tr>
      <td align="center" style="padding: 40px 20px;">

        <!-- Main Container -->
        <table role="presentation" class="email-container" width="540" cellpadding="0" cellspacing="0" style="max-width: 540px; width: 100%; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">

          <!-- Header -->
          <tr>
            <td class="header-wrapper" align="center" style="padding: 32px 32px 24px; border-bottom: 1px solid #f3f4f6;">
              <img src="{{ config('app.url') }}/images/email/logo.avif" alt="Garam Capkapal" width="auto" height="40" style="height: 40px; width: auto; display: block;" />
            </td>
          </tr>

          <!-- Content -->
          <tr>
            <td class="content-wrapper" style="padding: 32px; font-size: 15px; line-height: 1.6; color: #374151;">
              <h1 style="margin: 0 0 20px; font-size: 18px; font-weight: 600; color: #111827;">Reset Password</h1>
              <p style="margin: 0 0 16px;">Halo {{ $user->name ?? 'Pengguna' }},</p>
              <p style="margin: 0 0 16px;">Kami menerima permintaan untuk melakukan reset password pada akun Garam Capkapal Anda. Klik tombol di bawah ini untuk mengatur ulang password Anda:</p>

              <!-- Button -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 32px 0;">
                <tr>
                  <td align="center">
                    <a href="{{ $url }}" style="display: inline-block; background-color: #d4a000; color: #ffffff; text-decoration: none; font-weight: 500; padding: 12px 28px; border-radius: 6px; font-size: 15px;">Reset Password</a>
                  </td>
                </tr>
              </table>

              <p style="margin: 0 0 16px;">Tautan reset password ini akan kedaluwarsa dalam <strong>{{ $expireMinutes }} menit</strong>. Jika Anda tidak pernah meminta reset password, Anda dapat mengabaikan email ini dengan aman dan akun Anda akan tetap terlindungi.</p>

              <!-- Mascot -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 32px 0 0;">
                <tr>
                  <td align="center">
                    <img src="{{ config('app.url') }}/images/email/capt-yodi-thankyou.png" alt="Captain Yodi" width="100" height="auto" style="width: 100px; height: auto; display: block; opacity: 0.9;" />
                  </td>
                </tr>
              </table>

              <!-- Fallback Link -->
              <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #f3f4f6; font-size: 13px; color: #6b7280; word-break: break-all;">
                Jika tombol reset tidak berfungsi, salin dan tempel URL berikut ke browser Anda:<br>
                <a href="{{ $url }}" style="color: #0284c7; text-decoration: none; margin-top: 4px; display: inline-block;">{{ $url }}</a>
              </div>
            </td>
          </tr>
        </table>

        <!-- Footer -->
        <table role="presentation" width="540" cellpadding="0" cellspacing="0" style="max-width: 540px; width: 100%; margin: 0 auto;">
          <tr>
            <td align="center" style="padding: 24px 20px; font-size: 12px; color: #9ca3af; line-height: 1.5;">
              &copy; {{ date('Y') }} Garam Capkapal. Hak Cipta Dilindungi.<br>
              Email ini dikirim secara otomatis. Mohon tidak membalas email ini.
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>

</body>
</html>
