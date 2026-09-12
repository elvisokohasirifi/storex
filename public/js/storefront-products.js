document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-product-browser]').forEach((browser) => {
        const searchInput = browser.querySelector('[data-product-search-input]');
        const cards = Array.from(browser.querySelectorAll('[data-product-card]'));
        const count = browser.querySelector('[data-visible-product-count]');
        const emptyState = browser.querySelector('[data-product-filter-empty]');
        const filters = Array.from(browser.querySelectorAll('[data-filter-type]'));
        const clearButton = browser.querySelector('[data-clear-product-filters]');
        const state = { category: '', brand: '', search: '' };

        const applyFilters = () => {
            let visible = 0;
            cards.forEach((card) => {
                const matchesSearch = !state.search || (card.dataset.productSearch || '').includes(state.search);
                const matchesCategory = !state.category || card.dataset.productCategory === state.category;
                const matchesBrand = !state.brand || card.dataset.productBrand === state.brand;
                const shouldShow = matchesSearch && matchesCategory && matchesBrand;

                card.hidden = !shouldShow;
                if (shouldShow) {
                    visible += 1;
                }
            });

            if (count) {
                count.textContent = visible;
            }

            if (emptyState) {
                emptyState.hidden = visible !== 0;
            }
        };

        const activateFilter = (type, value) => {
            state[type] = value;
            filters.filter((filter) => filter.dataset.filterType === type).forEach((filter) => {
                const isActive = filter.dataset.filterValue === value;
                filter.classList.toggle('active', isActive);
                filter.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            applyFilters();
        };

        searchInput?.addEventListener('input', (event) => {
            state.search = event.target.value.trim().toLowerCase();
            applyFilters();
        });

        filters.forEach((filter) => {
            filter.addEventListener('click', () => {
                activateFilter(filter.dataset.filterType, filter.dataset.filterValue || '');
            });
        });

        clearButton?.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = '';
            }
            state.search = '';
            activateFilter('category', '');
            activateFilter('brand', '');
            applyFilters();
        });
    });
});
