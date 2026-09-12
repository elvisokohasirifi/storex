document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('[data-cart-form]');

    forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const originalLabel = button?.dataset.defaultLabel || button?.textContent || 'Add to cart';
            setButtonLoading(button, 'Adding...');

            try {
                const response = await fetch(form.action, {
                    method: form.method.toUpperCase(),
                    body: new FormData(form),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(firstError(payload) || 'Could not add this product to your cart.');
                }

                updateCart(payload);
                showMessage(payload.message || 'Product added to cart.');
                form.reset();
            } catch (error) {
                showMessage(error.message || 'Could not add this product to your cart.', true);
            } finally {
                restoreButton(button, originalLabel);
            }
        });
    });
});

function updateCart(payload) {
    document.querySelectorAll('[data-cart-count]').forEach((element) => {
        element.textContent = payload.cart_count ?? 0;
    });

    document.querySelectorAll('[data-cart-popover]').forEach((element) => {
        if (payload.cart_popover) {
            element.innerHTML = payload.cart_popover;
        }
    });
}

function showMessage(message, isError = false) {
    document.querySelectorAll('[data-cart-message]').forEach((element) => {
        element.textContent = message;
        element.hidden = false;
        element.classList.toggle('error', isError);
    });
}

function setButtonLoading(button, label) {
    if (!button) {
        return;
    }

    button.disabled = true;
    button.textContent = label;
}

function restoreButton(button, label) {
    if (!button) {
        return;
    }

    button.disabled = false;
    button.textContent = label;
}

function firstError(payload) {
    if (!payload?.errors) {
        return payload?.message;
    }

    const key = Object.keys(payload.errors)[0];
    return payload.errors[key]?.[0] || payload.message;
}
