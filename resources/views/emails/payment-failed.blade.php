<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Failed — Dot.Agents</title>
<style>
  body { margin: 0; padding: 0; background: #f9f9f7; font-family: 'Inter', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(61,46,160,0.08); }
  .header { background: linear-gradient(135deg, #1e1660 0%, #3d2ea0 100%); padding: 40px; text-align: center; }
  .header .logo { font-size: 24px; font-weight: 800; color: #f5be1c; margin: 0; }
  .alert-icon { font-size: 48px; margin: 16px 0 0; }
  .body { padding: 48px 40px; }
  .body h2 { color: #111827; font-size: 22px; font-weight: 700; margin: 0 0 16px; }
  .body p { color: #374151; line-height: 1.7; margin: 0 0 16px; font-size: 15px; }
  .alert-box { background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 28px 0; }
  .alert-box p { margin: 0; color: #991b1b; font-weight: 500; }
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
    <div class="alert-icon">⚠️</div>
  </div>
  <div class="body">
    <h2>Payment could not be processed</h2>

    <p>Hi {{ $orgName }} team,</p>

    <p>
      We were unable to process your latest payment for your Dot.Agents subscription.
      To avoid any interruption to your AI workforce, please update your payment method as soon as possible.
    </p>

    <div class="alert-box">
      <p>Your AI agents may be paused if payment is not resolved within 7 days.</p>
    </div>

    <p>This can happen when a card expires, reaches its limit, or when the card issuer declines the charge. Updating your billing details takes less than a minute.</p>

    <div class="cta-block">
      <a href="{{ $billingUrl }}" class="btn-primary">Update Payment Method →</a>
    </div>

    <p style="font-size:13px; color:#9ca3af;">If you believe this is an error or need assistance, contact us at <a href="mailto:billing@dotagents.com" style="color:#3d2ea0;">billing@dotagents.com</a>.</p>
  </div>
  <div class="footer">
    <p>Dot.Agents AI Workforce Platform</p>
    <p>Questions? <a href="mailto:billing@dotagents.com" style="color:#6b7280;">billing@dotagents.com</a></p>
  </div>
</div>
</body>
</html>
