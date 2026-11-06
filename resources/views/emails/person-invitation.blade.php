<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#ffffff;color:#17151e;font-family:Arial,sans-serif;font-size:16px;line-height:1.5">
    <p>{{ $invitedBy }} invited you to join <b>{{ $businessName }}</b> on Dot.Agents as {{ strtolower($roleLabel) }}.</p>
    <p><a href="{{ $link }}" style="color:#17151e">Open your invitation</a></p>
    <p>The link works for {{ $days }} days, and only with the account that uses this email address.</p>
</body>
</html>
