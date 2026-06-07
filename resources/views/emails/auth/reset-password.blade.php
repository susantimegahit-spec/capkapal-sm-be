<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
  <title>Reset Password — {{ $appName }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="color-scheme" content="light" />
  <meta name="supported-color-schemes" content="light" />
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <![endif]-->
  <style>
    /* ── Reset ─────────────────────────────────────────── */
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }

    /* ── Base ──────────────────────────────────────────── */
    body {
      background-color: #FFFDF5;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      color: #1C1D1F;
      line-height: 1.6;
    }

    /* ── Responsive ───────────────────────────────────── */
    @media only screen and (max-width: 620px) {
      .email-container { width: 100% !important; margin: auto !important; }
      .email-body { padding: 28px 20px !important; }
      .hero-section { padding: 32px 20px 20px !important; }
      .btn-reset { padding: 14px 32px !important; font-size: 15px !important; }
      .footer-cell { padding: 24px 20px !important; }
      .info-box { padding: 16px !important; }
      .mascot-img { width: 100px !important; height: auto !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #FFFDF5;">
  <!-- Preheader (hidden preview text) -->
  <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
    Permintaan reset password untuk akun {{ $appName }} Anda. Link ini berlaku {{ $expireMinutes }} menit. 🔒
  </div>

  <!-- Outer wrapper -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #FFFDF5;">
    <tr>
      <td align="center" style="padding: 40px 16px;">

        <!-- Email container -->
        <table role="presentation" class="email-container" width="560" cellpadding="0" cellspacing="0" style="max-width: 560px; width: 100%; margin: 0 auto;">

          {{-- ═══════════════════════════════════════════
               HEADER — Golden brand strip with mascot
          ═══════════════════════════════════════════ --}}
          <tr>
            <td class="hero-section" align="center" style="
              background: linear-gradient(135deg, #FFD738 0%, #D4A000 100%);
              padding: 36px 32px 16px;
              border-radius: 20px 20px 0 0;
            ">
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                {{-- Logo --}}
                <tr>
                  <td align="center" style="padding-bottom: 12px;">
                    <img
                      src="{{ config('app.url') }}/images/email/logo.png"
                      alt="{{ $appName }}"
                      width="72"
                      style="width: 72px; height: auto; display: block;"
                    />
                  </td>
                </tr>
                {{-- Title --}}
                <tr>
                  <td align="center">
                    <h1 style="margin: 0; color: #6E5904; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; text-transform: uppercase;">
                      Reset Password
                    </h1>
                    <p style="margin: 6px 0 0; color: #8B7209; font-size: 13px; font-weight: 500;">
                      Kami siap membantu kamu masuk kembali!
                    </p>
                  </td>
                </tr>
                {{-- Captain Yodi mascot --}}
                <tr>
                  <td align="center" style="padding-top: 8px;">
                    <img
                      class="mascot-img"
                      src="{{ config('app.url') }}/images/email/capt-yodi-thankyou.png"
                      alt="Captain Yodi"
                      width="140"
                      style="width: 140px; height: auto; display: block;"
                    />
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- ═══════════════════════════════════════════
               BODY
          ═══════════════════════════════════════════ --}}
          <tr>
            <td class="email-body" style="
              background-color: #ffffff;
              padding: 36px 32px;
              border-left: 1px solid #F5E6A3;
              border-right: 1px solid #F5E6A3;
            ">
              {{-- Greeting --}}
              <p style="margin: 0 0 20px; font-size: 16px; color: #1C1D1F; font-weight: 700;">
                Halo {{ $user->name }}! 👋
              </p>

              <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.7;">
                Kami menerima permintaan untuk mereset password akun Anda. Klik tombol di bawah untuk membuat password baru:
              </p>

              {{-- CTA Button --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 28px 0;">
                <tr>
                  <td align="center">
                    <table role="presentation" cellpadding="0" cellspacing="0">
                      <tr>
                        <td align="center" style="border-radius: 14px; background: linear-gradient(135deg, #FFD738 0%, #D4A000 100%); box-shadow: 0 4px 14px rgba(212, 160, 0, 0.35);">
                          <a href="{{ $url }}" target="_blank" rel="noopener" class="btn-reset" style="
                            display: inline-block;
                            padding: 16px 44px;
                            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                            font-size: 16px;
                            font-weight: 800;
                            color: #6E5904;
                            text-decoration: none;
                            border-radius: 14px;
                            letter-spacing: 0.5px;
                            text-transform: uppercase;
                          ">🔐 Reset Password</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              {{-- Expiry info box --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td class="info-box" style="
                    background-color: #FFFBEB;
                    border-left: 4px solid #FFD738;
                    border-radius: 0 12px 12px 0;
                    padding: 16px 20px;
                  ">
                    <p style="margin: 0; font-size: 13px; color: #92400E; line-height: 1.6;">
                      ⏳ <strong>Link ini berlaku {{ $expireMinutes }} menit.</strong> Setelah kedaluwarsa, kamu harus meminta link reset baru.
                    </p>
                  </td>
                </tr>
              </table>

              {{-- Safety notice --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 16px;">
                <tr>
                  <td class="info-box" style="
                    background-color: #f0fdf4;
                    border-left: 4px solid #22c55e;
                    border-radius: 0 12px 12px 0;
                    padding: 16px 20px;
                  ">
                    <p style="margin: 0; font-size: 13px; color: #166534; line-height: 1.6;">
                      🛡️ Jika kamu <strong>tidak merasa meminta</strong> reset password, abaikan email ini. Akunmu tetap aman.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- ═══════════════════════════════════════════
               FALLBACK URL
          ═══════════════════════════════════════════ --}}
          <tr>
            <td style="
              background-color: #FFFDF5;
              padding: 20px 32px;
              border-top: 1px solid #F5E6A3;
              border-left: 1px solid #F5E6A3;
              border-right: 1px solid #F5E6A3;
            ">
              <p style="margin: 0 0 8px; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                Tombol tidak berfungsi? Salin dan tempel URL ini di browser:
              </p>
              <p style="margin: 0; font-size: 12px; word-break: break-all;">
                <a href="{{ $url }}" style="color: #D4A000; text-decoration: underline;">{{ $url }}</a>
              </p>
            </td>
          </tr>

          {{-- ═══════════════════════════════════════════
               FOOTER — Dark footer with golden accent
          ═══════════════════════════════════════════ --}}
          <tr>
            <td class="footer-cell" align="center" style="
              background-color: #1C1D1F;
              padding: 28px 32px;
              border-radius: 0 0 20px 20px;
            ">
              {{-- Golden accent line --}}
              <table role="presentation" width="60" cellpadding="0" cellspacing="0" style="margin-bottom: 16px;">
                <tr>
                  <td style="height: 3px; background: linear-gradient(90deg, #FFD738, #D4A000); border-radius: 2px;"></td>
                </tr>
              </table>

              <p style="margin: 0 0 4px; font-size: 14px; font-weight: 700; color: #FFD738;">
                {{ $appName }}
              </p>
              <p style="margin: 0; font-size: 12px; color: #94a3b8; line-height: 1.6;">
                Email ini dikirim secara otomatis. Mohon tidak membalas email ini.
              </p>
              <p style="margin: 12px 0 0; font-size: 11px; color: #64748b;">
                © {{ date('Y') }} {{ $appName }}. Hak cipta dilindungi.
              </p>
            </td>
          </tr>

        </table>
        <!-- /email-container -->

      </td>
    </tr>
  </table>
</body>
</html>
