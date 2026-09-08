(() => {
    const init = () => {
        const panel = document.querySelector('[data-receipt-sharing]');
        if (!panel) return;
        const phone = panel.querySelector('#receipt-whatsapp-phone');
        const status = panel.querySelector('[role="status"]');
        const prepare = panel.querySelector('[data-prepare-share]');
        const share = panel.querySelector('[data-share-pdf]');
        let pdfFile;
        const report = (text) => { status.textContent = text; };
        panel.querySelector('[data-open-whatsapp]').addEventListener('click', () => {
            const number = phone.value.trim().replace(/[\s().-]/g, '').replace(/^00/, '+');
            if (!/^\+[1-9][0-9]{7,14}$/.test(number)) {
                report('Enter the customer number with its country code, for example +233241234567.');
                phone.focus();
                return;
            }
            window.open(`https://wa.me/${number.slice(1)}?text=${encodeURIComponent(panel.dataset.message)}`, '_blank', 'noopener,noreferrer');
            report('In WhatsApp, attach the downloaded PDF receipt, confirm the recipient, and tap Send.');
        });
        if (navigator.share && navigator.canShare) prepare.hidden = false;
        prepare.addEventListener('click', async () => {
            prepare.disabled = true;
            report('Preparing your PDF…');
            try {
                const response = await fetch(panel.dataset.pdfUrl, {headers: {'Accept': 'application/pdf'}});
                if (!response.ok || !response.headers.get('Content-Type')?.includes('application/pdf')) {
                    throw new Error('The PDF could not be loaded. Refresh this page and try again.');
                }
                pdfFile = new File([await response.blob()], panel.dataset.filename, {type: 'application/pdf'});
                if (!navigator.canShare({files: [pdfFile]})) {
                    report('This browser cannot share PDF files directly. Download the PDF and attach it in WhatsApp.');
                    return;
                }
                share.hidden = false;
                report('PDF ready. Tap Share PDF, choose WhatsApp, then select the customer and send.');
            } catch (error) {
                report(error.message || 'Unable to prepare the receipt. Try downloading it instead.');
            } finally {
                prepare.disabled = false;
            }
        });
        share.addEventListener('click', async () => {
            if (!pdfFile) return;
            share.disabled = true;
            try {
                await navigator.share({files: [pdfFile], title: 'Sales receipt', text: panel.dataset.message});
                report('Receipt handed to your selected app. Confirm the recipient and send it there.');
            } catch (error) {
                report(error.name === 'AbortError' ? 'Sharing cancelled. Your receipt is still available.' : 'Sharing was unavailable. Download the PDF and attach it in WhatsApp.');
            } finally {
                share.disabled = false;
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();