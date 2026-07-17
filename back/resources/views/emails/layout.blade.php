<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'FinTech')</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background-color: #080b14;
      font-family: Inter, 'Segoe UI', Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
      margin: 0;
      padding: 40px 16px;
    }
  </style>
</head>
<body>

  <!-- ═══ Email wrapper ═══ -->
  <table role="presentation" cellpadding="0" cellspacing="0"
    style="
      width: 100%;
      max-width: 600px;
      margin: 0 auto;
      border-radius: 24px;
      overflow: hidden;
      background-color: #0d1422;
      border: 1px solid rgba(125,152,197,0.14);
      box-shadow: 0 24px 56px rgba(4,10,20,0.40);
    ">

    <!-- ── Header ── -->
    <tr>
      <td style="
        background: linear-gradient(135deg, #0d1422 0%, #12102a 100%);
        padding: 36px 40px 32px;
        border-bottom: 1px solid rgba(125,152,197,0.12);
      ">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
          <tr>
            <td>
              <!-- Brand mark -->
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="
                    width: 44px; height: 44px;
                    background-color: #7c3aed;
                    border-radius: 14px;
                    text-align: center;
                    vertical-align: middle;
                    box-shadow: 0 0 0 1px rgba(139,92,246,0.3), 0 8px 24px rgba(91,33,182,0.40);
                  ">
                    <span style="
                      display: inline-block;
                      color: #fff;
                      font-size: 20px;
                      line-height: 44px;
                      font-weight: 800;
                    ">F</span>
                  </td>
                  <td style="padding-left: 12px; vertical-align: middle;">
                    <span style="
                      font-size: 1.15rem;
                      font-weight: 800;
                      color: #eff6ff;
                      letter-spacing: -0.03em;
                    ">FinTech</span>
                  </td>
                </tr>
              </table>
            </td>
            <td style="text-align: right; vertical-align: middle;">
              <!-- Security badge -->
              <span style="
                display: inline-block;
                background: rgba(34,211,238,0.12);
                border: 1px solid rgba(34,211,238,0.22);
                border-radius: 999px;
                padding: 4px 12px;
                font-size: 0.72rem;
                font-weight: 700;
                color: #22d3ee;
                letter-spacing: 0.08em;
                text-transform: uppercase;
              ">🔒 Seguro</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- ── Content ── -->
    @yield('content')

    <!-- ── Footer ── -->
    <tr>
      <td style="
        background: rgba(8,11,20,0.82);
        border-top: 1px solid rgba(125,152,197,0.10);
        padding: 24px 40px;
        text-align: center;
      ">
        <p style="
          font-size: 0.78rem;
          color: #5f6f8f;
          line-height: 1.6;
          margin-bottom: 12px;
        ">
          @yield('footer_text', 'Este correo fue enviado desde FinTech.')
        </p>
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
          <tr>
            <td style="padding: 0 8px;">
              <a href="#" style="font-size: 0.75rem; color: #5f6f8f; text-decoration: none;">Privacidad</a>
            </td>
            <td style="color: rgba(125,152,197,0.3); font-size: 0.75rem;">·</td>
            <td style="padding: 0 8px;">
              <a href="#" style="font-size: 0.75rem; color: #5f6f8f; text-decoration: none;">Términos</a>
            </td>
            <td style="color: rgba(125,152,197,0.3); font-size: 0.75rem;">·</td>
            <td style="padding: 0 8px;">
              <a href="#" style="font-size: 0.75rem; color: #5f6f8f; text-decoration: none;">Soporte</a>
            </td>
          </tr>
        </table>
        <p style="
          font-size: 0.72rem;
          color: rgba(125,152,197,0.35);
          margin-top: 16px;
        ">&copy; {{ date('Y') }} FinTech. Todos los derechos reservados.</p>
      </td>
    </tr>

  </table>

</body>
</html>
