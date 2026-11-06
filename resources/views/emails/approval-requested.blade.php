<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Required — Dot.Agents</title>
<style>
  body { margin: 0; padding: 0; background: #f9f9f7; font-family: 'Inter', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(61,46,160,0.08); }
  .header { background: #3d2ea0; padding: 32px 40px; }
  .header img { height: 32px; }
  .header h1 { color: #f5be1c; font-size: 20px; margin: 16px 0 0; font-weight: 700; }
  .body { padding: 40px; }
  .body p { color: #374151; line-height: 1.6; margin: 0 0 16px; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 24px; }
  .meta-block { background: #f3f4f6; border-radius: 8px; padding: 16px 20px; margin: 24px 0; }
  .meta-block p { margin: 4px 0; font-size: 14px; color: #6b7280; }
  .meta-block strong { color: #111827; }
  .actions { display: flex; gap: 12px; margin-top: 32px; }
  .btn-approve { background: #3d2ea0; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 15px; }
  .btn-reject { background: #fff; color: #dc2626; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 15px; border: 2px solid #dc2626; }
  .footer { background: #f9f9f7; padding: 24px 40px; text-align: center; }
  .footer p { color: #9ca3af; font-size: 12px; margin: 4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>⚡ Dot.Agents</h1>
    <h1 style="font-size:16px; color:#fff; margin:8px 0 0;">Action Required: AI Agent Approval</h1>
  </div>
  <div class="body">
    <span class="badge">Human Approval Required</span>
    <p>Your AI agent <strong>{{ $deploymentName }}</strong> has stopped at something that needs your approval before proceeding.</p>

    <div class="meta-block">
      <p><strong>Agent:</strong> {{ $agentName }}</p>
      <p><strong>Task:</strong> {{ $taskDescription }}</p>
      @if($expiresAt)
      <p><strong>Expires:</strong> {{ \Carbon\Carbon::parse($expiresAt)->format('M j, Y g:i A') }}</p>
      @endif
    </div>

    <p>Please review the agent's proposed action and approve or reject it. If no action is taken before the expiry time, the task will be automatically rejected.</p>

    <div class="actions">
      <a href="{{ $reviewUrl }}" class="btn-approve">Review and answer</a>
    </div>

  </div>
  <div class="footer">
    <p>Dot.Agents AI Workforce Platform</p>
    <p>You received this because you are an approver for this AI agent deployment.</p>
  </div>
</div>
</body>
</html>
