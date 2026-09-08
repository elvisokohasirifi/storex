@if(in_array($order->status, ['paid', 'paid_review'], true))
<section class="border rounded p-3 mt-4" data-receipt-sharing data-pdf-url="{{ route('workspace.receipt.pdf', $order) }}" data-filename="receipt-{{ $order->reference }}.pdf" data-message="{{ 'Hello '.$order->customer_name.', here is your receipt from '.$order->shop->name.'. Reference: '.$order->reference.'. Total: '.$order->currency.' '.number_format($order->total / 100, 2).'.' }}">
    <h3>Send receipt via WhatsApp</h3>
    <p>Download the PDF, open the customer’s WhatsApp chat, then attach the downloaded receipt and tap Send.</p>
    <label class="form-label" for="receipt-whatsapp-phone">Customer WhatsApp number (with country code)</label>
    <input class="form-control mb-3" style="max-width:340px" id="receipt-whatsapp-phone" type="tel" value="{{ $order->customer_phone }}" placeholder="+233241234567" autocomplete="tel" aria-describedby="receipt-share-status">
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('workspace.receipt.pdf', $order) }}">Download PDF receipt</a>
        <button class="btn btn-success" type="button" data-open-whatsapp>Open WhatsApp chat</button>
        <button class="btn btn-outline-primary" type="button" data-prepare-share hidden>Prepare PDF for sharing</button>
        <button class="btn btn-outline-primary" type="button" data-share-pdf hidden>Share PDF</button>
    </div>
    <p class="small text-muted mt-2 mb-0" id="receipt-share-status" role="status" aria-live="polite">The PDF stays private until you choose to share it. No message is sent automatically.</p>
</section>
@endif