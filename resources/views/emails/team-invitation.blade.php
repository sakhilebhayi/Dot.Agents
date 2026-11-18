<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team Invitation — Dot.Agents</title>
<style>
  body { margin: 0; padding: 0; background: #f9f9f7; font-family: 'Inter', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(61,46,160,0.08); }
  .header { background: linear-gradient(135deg, #1e1660 0%, #3d2ea0 100%); padding: 40px 40px 36px; text-align: center; }
  .header .brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 20px; }
  .header .brand-dot { width: 32px; height: 32px; background: #f5be1c; border-radius: 8px; display: inline-block; }
  .header .brand-name { color: #fff; font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }
  .header h1 { color: #f5be1c; font-size: 22px; font-weight: 700; margin: 0; }
  .header p { color: rgba(255,255,255,0.75); font-size: 14px; margin: 8px 0 0; }
  .body { padding: 44px 40px; }
  .body p { color: #374151; line-height: 1.7; margin: 0 0 16px; font-size: 15px; }
  .team-badge { display: inline-flex; align-items: center; gap: 8px; background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 10px 16px; margin: 20px 0 28px; }
  .team-badge .dot { width: 8px; height: 8px; background: #3d2ea0; border-radius: 50%; }
  .team-badge span { color: #3d2ea0; font-weight: 600; font-size: 15px; }
  .btn-block { text-align: center; margin: 32px 0 24px; }
  .btn-primary { display: inline-block; background: #3d2ea0; color: #fff; text-decoration: none; padding: 14px 36px; border-radius: 8px; font-weight: 700; font-size: 15px; letter-spacing: 0.01em; }
  .btn-secondary { display: inline-block; background: #fff; color: #3d2ea0; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; border: 2px solid #3d2ea0; margin-top: 12px; }
  .info-box { background: #fffbeb; border-left: 4px solid #f5be1c; border-radius: 0 8px 8px 0; padding: 14px 18px; margin: 28px 0; }
  .info-box p { margin: 0; color: #92400e; font-size: 13px; line-height: 1.6; }
  .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
  .footer { background: #f9f9f7; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
  .footer p { color: #9ca3af; font-size: 12px; margin: 4px 0; }
  .footer a { color: #6b7280; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">

  {{-- Header --}}
  <div class="header">
    <div class="brand">
      <span class="brand-dot"></span>
      <span class="brand-name">Dot.Agents</span>
    </div>
    <h1>You've been invited!</h1>
    <p>Join a team on the Dot.Agents Enterprise Platform</p>
  </div>

  {{-- Body --}}
  <div class="body">
    <p>Hi there,</p>
    <p>You have been invited to join the following team on <strong>Dot.Agents</strong> — the AI Workforce Platform for governed, enterprise-grade AI operations.</p>

    <div class="team-badge">
      <span class="dot"></span>
      <span>{{ $invitation->team->name }}</span>
    </div>

    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
    <p>If you don't have a Dot.Agents account yet, create one first and then accept your invitation:</p>

    <div class="btn-block">
      <a href="{{ \App\Support\CentralSignIn::register(route('register')) }}" class="btn-primary">Create Account</a>
    </div>

    <hr class="divider">
    <p>Already have an account? Accept the invitation directly:</p>
    @else
    <p>Accept the invitation to join the team and start collaborating:</p>
    @endif

    <div class="btn-block">
      <a href="{{ $acceptUrl }}" class="btn-primary">Accept Invitation →</a>
    </div>

    <div class="info-box">
      <p>If you were not expecting this invitation or don't recognise the team, you can safely ignore this email. The invitation link will expire after 7 days.</p>
    </div>
  </div>

  {{-- Footer --}}
  <div class="footer">
    <p>Dot.Agents &mdash; Enterprise AI Workforce Platform</p>
    <p><a href="https://agents.infodot.co.za">agents.infodot.co.za</a></p>
    <p style="margin-top: 8px;">This email was sent to you because someone invited you to a team.</p>
  </div>

</div>
</body>
</html>
