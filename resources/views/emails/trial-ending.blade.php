<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your trial ends soon — Dot.Agents</title>
<style>
  body { margin: 0; padding: 0; background: #f9f9f7; font-family: 'Inter', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(61,46,160,0.08); }
  .header { background: linear-gradient(135deg, #1e1660 0%, #3d2ea0 100%); padding: 48px 40px; text-align: center; }
  .header .logo { font-size: 24px; font-weight: 800; color: #f5be1c; margin: 0; }
  .header .tagline { color: rgba(255,255,255,0.75); font-size: 15px; margin: 12px 0 0; }
  .body { padding: 48px 40px; }
  .body h2 { color: #111827; font-size: 22px; font-weight: 700; margin: 0 0 16px; }
  .body p { color: #374151; line-height: 1.7; margin: 0 0 16px; font-size: 15px; }
  .highlight-box { background: #fffbeb; border-left: 4px solid #f5be1c; border-radius: 0 8px 8px 0; padding: 16px 20px; margin: 28px 0; }
  .highlight-box p { margin: 0; color: #92400e; font-weight: 500; }
  .features { margin: 32px 0; }
  .feature { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
  .feature-icon { width: 36px; height: 36px; background: #f5be1c; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; line-height: 36px; text-align: center; }
  .feature-text h4 { margin: 0 0 4px; color: #111827; font-size: 14px; font-weight: 600; }
  .feature-text p { margin: 0; color: #6b7280; font-size: 13px; line-height: 1.5; }
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
    <h2>Your free trial is ending soon ⏳</h2>

    <p>Hi {{ $orgName }} team,</p>

    <p>
      We hope you've been enjoying your AI workforce during your free trial.
      Your access is about to expire — upgrade now to keep your agents working without interruption.
    </p>

    @if($trialEndsAt)
    <div class="highlight-box">
      <p>Your trial ends on <strong>{{ $trialEndsAt }}</strong>. Upgrade before then to avoid any disruption.</p>
    </div>
    @endif

    <div class="features">
      <div class="feature">
        <div class="feature-icon">🤖</div>
        <div class="feature-text">
          <h4>Keep your AI agents running</h4>
          <p>All deployed agents continue working 24/7 after you upgrade.</p>
        </div>
      </div>
      <div class="feature">
        <div class="feature-icon">📊</div>
        <div class="feature-text">
          <h4>Retain all your data</h4>
          <p>Task history, decision logs, and scorecards are preserved.</p>
        </div>
      </div>
      <div class="feature">
        <div class="feature-icon">🛡️</div>
        <div class="feature-text">
          <h4>Governance stays active</h4>
          <p>Approval workflows and audit logs continue uninterrupted.</p>
        </div>
      </div>
    </div>

    <div class="cta-block">
      <a href="{{ $upgradeUrl }}" class="btn-primary">Upgrade Now →</a>
    </div>

    <p style="font-size:13px; color:#9ca3af; text-align:center;">
      Questions about pricing? <a href="mailto:sales@dotagents.com" style="color:#3d2ea0;">Talk to our team</a>
    </p>
  </div>
  <div class="footer">
    <p>Dot.Agents AI Workforce Platform</p>
    <p>Questions? <a href="mailto:support@dotagents.com" style="color:#6b7280;">support@dotagents.com</a></p>
  </div>
</div>
</body>
</html>
