<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscription Cancelled — Dot.Agents</title>
<style>
  body { margin: 0; padding: 0; background: #f9f9f7; font-family: 'Inter', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(61,46,160,0.08); }
  .header { background: linear-gradient(135deg, #1e1660 0%, #3d2ea0 100%); padding: 40px; text-align: center; }
  .header .logo { font-size: 24px; font-weight: 800; color: #f5be1c; margin: 0; }
  .header .tagline { color: rgba(255,255,255,0.75); font-size: 15px; margin: 12px 0 0; }
  .body { padding: 48px 40px; }
  .body h2 { color: #111827; font-size: 22px; font-weight: 700; margin: 0 0 16px; }
  .body p { color: #374151; line-height: 1.7; margin: 0 0 16px; font-size: 15px; }
  .info-box { background: #f5f3ff; border-left: 4px solid #3d2ea0; border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 28px 0; }
  .info-box p { margin: 0; color: #3d2ea0; font-weight: 500; }
  .cta-block { text-align: center; margin: 36px 0 28px; }
  .btn-primary { display: inline-block; background: #3d2ea0; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 15px; }
  .footer { background: #f9f9f7; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
  .footer p { color: #9ca3af; font-size: 12px; margin: 4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <p class="logo">⚡ Dot.Agents</p>
    <p class="tagline">Your AI Workforce Platform</p>
  </div>
  <div class="body">
    <h2>Your subscription has been cancelled</h2>

    <p>Hi {{ $orgName }} team,</p>

    <p>
      Your Dot.Agents subscription has been cancelled. Your AI agents have been paused
      and your organization's data will be retained for 30 days.
    </p>

    <div class="info-box">
      <p>Your data is safe. You can reactivate your subscription at any time within the next 30 days and pick up right where you left off.</p>
    </div>

    <p>
      If you cancelled by mistake or have changed your mind, we'd love to have you back.
      Reactivating takes just a few seconds and your agents will be back online immediately.
    </p>

    <div class="cta-block">
      <a href="{{ $reactivateUrl }}" class="btn-primary">Reactivate Subscription →</a>
    </div>

    <p style="font-size:13px; color:#9ca3af; text-align:center;">
      We'd love to hear why you cancelled — your feedback helps us improve.
      <a href="mailto:feedback@dotagents.com" style="color:#3d2ea0;">Share feedback</a>
    </p>
  </div>
  <div class="footer">
    <p>Dot.Agents AI Workforce Platform</p>
    <p>Questions? <a href="mailto:support@dotagents.com" style="color:#6b7280;">support@dotagents.com</a></p>
  </div>
</div>
</body>
</html>
