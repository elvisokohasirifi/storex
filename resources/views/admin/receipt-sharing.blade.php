@if(in_array($order->status, ['paid', 'paid_review'], true))
<section class="border rounded p-3 mt-4" data-receipt-sharing data-pdf-url="{{ route('workspace.receipt.pdf', $order) }}" data-filename="receipt-{{ $order->reference }}.pdf" data-message="{{ 'Hello '.$order->customer_name.', here is your receipt from '.$order->shop->name.'. Reference: '.$order->reference.'. Total: '.$order->currency.' '.number_format($order->total / 100, 2).'.' }}">
    <h3>Share receipt</h3>
    <p>Download the PDF receipt or share it with the client on WhatsApp from this device.</p>
    <label class="form-label" for="receipt-whatsapp-phone">Customer WhatsApp number (with country code)</label>
    <input class="form-control mb-3" style="max-width:340px" id="receipt-whatsapp-phone" type="tel" value="{{ $order->customer_phone }}" placeholder="+233241234567" autocomplete="tel" aria-describedby="receipt-share-status">
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('workspace.receipt.pdf', $order) }}">Download receipt</a>
        <button class="btn btn-success" type="button" data-share-whatsapp>Share with client on WhatsApp</button>
    </div>
    <p class="small text-muted mt-2 mb-0" id="receipt-share-status" role="status" aria-live="polite">If your device supports file sharing, WhatsApp will receive the PDF receipt. Otherwise, download the PDF and attach it in the opened WhatsApp chat.</p>
</section>

@pushOnce('after_scripts')
<script>
(() => {
    document.querySelectorAll('[data-receipt-sharing]').forEach((section) => {
        const button = section.querySelector('[data-share-whatsapp]');
        const phoneInput = section.querySelector('#receipt-whatsapp-phone');
        const status = section.querySelector('#receipt-share-status');
        const pdfUrl = section.dataset.pdfUrl;
        const filename = section.dataset.filename || 'receipt.pdf';
        const message = section.dataset.message || 'Here is your receipt.';
        const normalizePhone = (phone) => phone.replace(/[^\d]/g, '');
        const whatsappUrl = () => {
            const phone = normalizePhone(phoneInput?.value || '');
            return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
        };

        button?.addEventListener('click', async () => {
            button.disabled = true;
            status.textContent = 'Preparing receipt for WhatsApp...';

            try {
                const response = await fetch(pdfUrl, {headers: {'Accept': 'application/pdf'}});
                if (!response.ok) throw new Error('The receipt PDF could not be downloaded.');

                const file = new File([await response.blob()], filename, {type: 'application/pdf'});
                if (navigator.canShare?.({files: [file]}) && navigator.share) {
                    await navigator.share({files: [file], text: message, title: 'Receipt'});
                    status.textContent = 'Receipt shared. Choose WhatsApp if your device asks where to send it.';
                    return;
                }

                window.open(whatsappUrl(), '_blank', 'noopener');
                status.textContent = 'WhatsApp chat opened. Attach the downloaded PDF receipt before sending.';
            } catch (error) {
                window.open(whatsappUrl(), '_blank', 'noopener');
                status.textContent = error.message || 'Could not attach the PDF automatically. Download the receipt and attach it in WhatsApp.';
            } finally {
                button.disabled = false;
            }
        });
    });
})();
</script>
@endPushOnce
@endif
