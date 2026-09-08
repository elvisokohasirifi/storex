(() => {
    const init = () => document.querySelectorAll('.classification-quick-create').forEach((panel) => {
        if (panel.dataset.ready) return;
        panel.dataset.ready = 'true';
        const form = panel.closest('form');
        const shop = form.querySelector('[name="shop_id"]');
        const name = panel.querySelector('[data-quick-name]');
        const description = panel.querySelector('[data-quick-description]');
        const button = panel.querySelector('[data-quick-save]');
        const message = panel.querySelector('[data-quick-message]');
        const select = form.querySelector(`[name="${panel.dataset.target}"]`);
        const report = (text, error = false) => {
            message.textContent = text;
            message.classList.toggle('text-danger', error);
            message.classList.toggle('text-success', !error);
        };
        name.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                button.click();
            }
        });
        button.addEventListener('click', async () => {
            const shopId = shop.value;
            if (!shopId) return report('Select a shop first.', true);
            if (!name.value.trim()) {
                report('Enter a name.', true);
                name.focus();
                return;
            }
            if (!name.reportValidity() || !description.reportValidity()) return;
            button.disabled = true;
            report('Saving…');
            try {
                const response = await fetch(panel.dataset.endpoint, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value},
                    body: JSON.stringify({shop_id: shopId, name: name.value.trim(), description: description.value.trim() || null})
                });
                const result = await response.json();
                if (!response.ok) {
                    const errors = Object.values(result.errors || {}).flat();
                    throw new Error(errors.join(' ') || result.message || 'Unable to save. Please try again.');
                }
                const shopLabel = Array.from(shop.options).find((option) => option.value === result.shop_id)?.textContent || '';
                const option = new Option(`${shopLabel} — ${result.name}`, result.id);
                select.add(option);
                if (shop.value === result.shop_id) {
                    select.value = result.id;
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                    report(`${result.name} added and selected.`);
                } else {
                    report(`${result.name} added to ${shopLabel}. Switch back to that shop to select it.`);
                }
                name.value = '';
                description.value = '';
            } catch (error) {
                report(error.message || 'Unable to save. Please try again.', true);
            } finally {
                button.disabled = false;
            }
        });
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();