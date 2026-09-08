<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt {{ $order->reference }}</title>
<style>
@page { margin: 36pt 40pt 42pt; }
body { font-family: DejaVu Sans, sans-serif; color: #20372f; font-size: 10pt; line-height: 1.5; }
h1 { font-size: 23pt; margin: 4pt 0 10pt; line-height: 1.25; }
p { margin: 3pt 0; }
.eyebrow { color: #557163; font-size: 9pt; letter-spacing: 2pt; text-transform: uppercase; }
.header { border-bottom: 3pt solid #20372f; padding-bottom: 18pt; margin-bottom: 20pt; }
.meta { width: 100%; margin-bottom: 24pt; table-layout: fixed; }
.meta td { width: 50%; vertical-align: top; padding: 0 14pt 0 0; overflow-wrap: break-word; }
.label { font-size: 8pt; text-transform: uppercase; color: #65776c; margin-top: 9pt; }
.reference { font-size: 8pt; }
.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
.items thead { display: table-header-group; }
.items th { background: #edf2eb; text-align: left; font-size: 9pt; padding: 10pt 6pt; }
.items td { border-bottom: 1pt solid #dce4d9; padding: 10pt 6pt; vertical-align: top; overflow-wrap: break-word; }
.items tr { page-break-inside: avoid; }
.items .number { text-align: right; }
.total { text-align: right; font-size: 17pt; margin-top: 18pt; page-break-inside: avoid; }
.footer { border-top: 1pt solid #dce4d9; margin-top: 28pt; padding-top: 12pt; color: #65776c; font-size: 9pt; page-break-inside: avoid; }
.notice { padding: 10pt; background: #fff1d3; margin: 15pt 0; }
</style>
</head>
<body>
<div class="header"><p class="eyebrow">Sales receipt</p><h1>{{ $order->shop->name }}</h1><p>{{ $order->shop->location }}</p><p>{{ $order->shop->contacts }}</p>@if($order->shop->email)<p>{{ $order->shop->email }}</p>@endif</div>
<table class="meta"><tr><td><p class="label">Customer</p><p><strong>{{ $order->customer_name }}</strong></p>@if($order->customer_phone)<p>{{ $order->customer_phone }}</p>@endif @if($order->customer_email)<p>{{ $order->customer_email }}</p>@endif</td><td><p class="label">Receipt reference</p><p class="reference">{{ $order->reference }}</p><p class="label">Payment date ({{ config('app.timezone') }})</p><p>{{ ($order->paid_at ?? $order->created_at)->format('d M Y, H:i') }}</p><p class="label">Payment method / status</p><p>{{ $order->channel === 'cash' ? 'Cash' : 'Paystack' }} / {{ $order->status === 'paid' ? 'Paid' : 'Payment received - review required' }}</p></td></tr></table>
@if($order->status === 'paid_review')<p class="notice">Payment received. Please contact the shop to confirm fulfilment.</p>@endif
<table class="items"><thead><tr><th style="width:44%">Item</th><th style="width:10%" class="number">Qty</th><th style="width:21%" class="number">Unit price</th><th style="width:25%" class="number">Amount</th></tr></thead><tbody>
@foreach($order->items as $item)<tr><td>{{ $item->name }}</td><td class="number">{{ $item->quantity }}</td><td class="number">{{ number_format($item->unit_price / 100, 2) }}</td><td class="number">{{ number_format($item->quantity * $item->unit_price / 100, 2) }}</td></tr>@endforeach
</tbody></table>
@if($order->discount_total > 0)<p class="total" style="font-size: 11pt;"><strong>Subtotal: {{ $order->currency }} {{ number_format($order->subtotal / 100, 2) }}</strong></p><p class="total" style="font-size: 11pt;"><strong>Discount{{ $order->discount_name ? ' · '.$order->discount_name : '' }}: -{{ $order->currency }} {{ number_format($order->discount_total / 100, 2) }}</strong></p>@endif
<p class="total"><strong>Total: {{ $order->currency }} {{ number_format($order->total / 100, 2) }}</strong></p>
<div class="footer"><p>All amounts are in {{ $order->currency }}.</p><p>Thank you for shopping with {{ $order->shop->name }}.</p><p>Keep this receipt for your records. For questions, contact the shop above.</p></div>
</body>
</html>
