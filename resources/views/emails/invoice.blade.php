<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice #{{ $invoiceNumber }} — Dot.Agents</title>
<style>
  body { margin: 0; padding: 0; background: #f9f9f7; font-family: 'Inter', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(61,46,160,0.08); }
  .header { background: #1e1660; padding: 32px 40px; display: flex; justify-content: space-between; align-items: center; }
  .header h1 { color: #f5be1c; font-size: 20px; margin: 0; font-weight: 700; }
  .header .invoice-num { color: rgba(255,255,255,0.7); font-size: 14px; text-align: right; }
  .body { padding: 40px; }
  .body p { color: #374151; line-height: 1.6; margin: 0 0 16px; }
  .table { width: 100%; border-collapse: collapse; margin: 24px 0; }
  .table th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 13px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
  .table td { padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #374151; }
  .table tr:last-child td { border-bottom: none; }
  .totals { background: #f9fafb; border-radius: 8px; padding: 16px 20px; margin: 24px 0; }
  .totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; color: #6b7280; }
  .totals-row.total { font-size: 16px; font-weight: 700; color: #111827; padding-top: 12px; border-top: 2px solid #e5e7eb; margin-top: 8px; }
  .badge-paid { display: inline-block; background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 24px; }
  .btn { display: inline-block; background: #3d2ea0; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 15px; margin-top: 8px; }
  .footer { background: #f9f9f7; padding: 24px 40px; text-align: center; }
  .footer p { color: #9ca3af; font-size: 12px; margin: 4px 0; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>⚡ Dot.Agents</h1>
    <div class="invoice-num">
      <div style="color:#fff; font-weight:600;">Invoice</div>
      <div>#{{ $invoiceNumber }}</div>
    </div>
  </div>
  <div class="body">
    <span class="badge-paid">✓ Paid</span>

    <p>Hi {{ $orgName }}, thank you for your payment. Here is your invoice summary.</p>

    @if(!empty($lineItems))
    <table class="table">
      <thead>
        <tr>
          <th>Description</th>
          <th style="text-align:right;">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($lineItems as $item)
        <tr>
          <td>{{ $item['description'] ?? 'Service' }}</td>
          <td style="text-align:right;">{{ strtoupper($currency) }} {{ number_format(($item['amount'] ?? 0) / 100, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    <div class="totals">
      @if($billingPeriod)
      <div class="totals-row">
        <span>Billing Period</span>
        <span>{{ $billingPeriod }}</span>
      </div>
      @endif
      @if($dueDate)
      <div class="totals-row">
        <span>Due Date</span>
        <span>{{ \Carbon\Carbon::parse($dueDate)->format('M j, Y') }}</span>
      </div>
      @endif
      <div class="totals-row total">
        <span>Total</span>
        <span>{{ $currency }} {{ $amount }}</span>
      </div>
    </div>

    <a href="{{ $invoiceUrl }}" class="btn">View & Download Invoice →</a>
  </div>
  <div class="footer">
    <p>Dot.Agents AI Workforce Platform</p>
    <p>Questions? Contact billing@dotagents.com</p>
  </div>
</div>
</body>
</html>
