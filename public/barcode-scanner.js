(() => {
    const scanner = {
        detector: null,
        stream: null,
        frame: null,
        video: null,
        status: null,
        resolve: null,
        active: false,
    };

    const ensureOverlay = () => {
        if (scanner.frame) return;

        const frame = document.createElement('div');
        frame.className = 'barcode-scanner-overlay d-none';
        frame.innerHTML = `
            <div class="barcode-scanner-panel">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h3 class="h5 mb-0">Scan barcode</h3>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-barcode-close>Close</button>
                </div>
                <video class="barcode-scanner-video" autoplay muted playsinline></video>
                <p class="small text-muted mt-3 mb-0" data-barcode-overlay-status>Point the camera at a barcode.</p>
            </div>`;
        document.body.appendChild(frame);

        const style = document.createElement('style');
        style.textContent = `
            .barcode-scanner-overlay{position:fixed;inset:0;z-index:1060;background:rgba(15,23,42,.74);display:flex;align-items:center;justify-content:center;padding:1rem}
            .barcode-scanner-overlay.d-none{display:none}
            .barcode-scanner-panel{width:min(560px,100%);background:#fff;border-radius:.5rem;padding:1rem;box-shadow:0 1.5rem 3rem rgba(15,23,42,.28)}
            .barcode-scanner-video{width:100%;aspect-ratio:4/3;background:#111827;border-radius:.375rem;object-fit:cover}`;
        document.head.appendChild(style);

        scanner.frame = frame;
        scanner.video = frame.querySelector('video');
        scanner.status = frame.querySelector('[data-barcode-overlay-status]');
        frame.querySelector('[data-barcode-close]').addEventListener('click', () => stop());
    };

    const message = (control, text, error = false) => {
        const status = control?.querySelector('[data-barcode-status]');
        if (!status) return;
        status.textContent = text;
        status.classList.toggle('text-danger', error);
        status.classList.toggle('text-success', !error && text.length > 0);
    };

    const stop = () => {
        scanner.active = false;
        if (scanner.stream) {
            scanner.stream.getTracks().forEach((track) => track.stop());
            scanner.stream = null;
        }
        if (scanner.video) {
            scanner.video.srcObject = null;
        }
        if (scanner.frame) {
            scanner.frame.classList.add('d-none');
        }
        if (scanner.resolve) {
            scanner.resolve(null);
            scanner.resolve = null;
        }
    };

    const scan = async () => {
        ensureOverlay();
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            throw new Error('Barcode scanning is not supported on this device or browser.');
        }

        scanner.detector = scanner.detector || new BarcodeDetector({
            formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'itf', 'codabar', 'qr_code'],
        });
        scanner.stream = await navigator.mediaDevices.getUserMedia({
            video: {facingMode: {ideal: 'environment'}},
            audio: false,
        });
        scanner.video.srcObject = scanner.stream;
        scanner.frame.classList.remove('d-none');
        scanner.status.textContent = 'Point the camera at a barcode.';
        scanner.active = true;

        await scanner.video.play();

        return await new Promise((resolve) => {
            scanner.resolve = resolve;
            const detect = async () => {
                if (!scanner.active) return;
                try {
                    const codes = await scanner.detector.detect(scanner.video);
                    if (codes.length > 0) {
                        const value = codes[0].rawValue;
                        scanner.resolve = null;
                        stop();
                        resolve(value);
                        return;
                    }
                } catch (error) {
                    scanner.status.textContent = 'Unable to read the camera feed. Try typing the barcode instead.';
                }
                requestAnimationFrame(detect);
            };
            requestAnimationFrame(detect);
        });
    };

    const fillTarget = (target, value) => {
        target.value = value;
        target.dispatchEvent(new Event('input', {bubbles: true}));
        target.dispatchEvent(new Event('change', {bubbles: true}));
        target.focus();
    };

    const escapeSelectorValue = (value) => {
        if (window.CSS?.escape) return CSS.escape(value);

        return value.replace(/["\\]/g, '\\$&');
    };

    const incrementVisibleProduct = (form, value) => {
        const row = document.querySelector(`[data-product-barcode="${escapeSelectorValue(value)}"]`);
        const input = row?.querySelector('[data-sale-quantity]');
        if (!input) return false;
        const current = parseInt(input.value || '0', 10) || 0;
        input.value = String(current + 1);
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        input.focus();
        return true;
    };

    const init = () => document.querySelectorAll('[data-barcode-scan]').forEach((button) => {
        if (button.dataset.ready) return;
        button.dataset.ready = 'true';

        button.addEventListener('click', async () => {
            const control = button.closest('[data-barcode-target], [data-barcode-pos]');
            const form = button.closest('form');
            button.disabled = true;
            message(control, 'Opening camera...');

            try {
                const value = await scan();
                if (!value) {
                    message(control, 'Scan cancelled.');
                    return;
                }

                if (control?.dataset.barcodePos !== undefined && incrementVisibleProduct(form, value)) {
                    message(control, `Added product with barcode ${value}.`);
                    return;
                }

                const selector = control?.dataset.barcodeTarget || 'barcode';
                const target = form?.querySelector(`[name="${selector}"]`) || document.querySelector(`[name="${selector}"]`);
                if (!target) {
                    throw new Error('Barcode field was not found on this page.');
                }

                fillTarget(target, value);
                if (control?.dataset.submitOnScan !== undefined && form) {
                    form.submit();
                    return;
                }
                message(control, `Scanned ${value}.`);
            } catch (error) {
                message(control, error.message || 'Unable to scan barcode. Please type it manually.', true);
            } finally {
                button.disabled = false;
            }
        });
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
