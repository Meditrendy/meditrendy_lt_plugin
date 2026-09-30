(() => {
    document.addEventListener('click', async (event) => {
        const toggle = event.target.closest('.meditrendy-variation-stock-toggle');
        if (!toggle) return;
        event.preventDefault();
        const productRow = toggle.closest('tr');
        if (!productRow) return;
        const id = toggle.getAttribute('aria-controls');
        let row = document.getElementById(id);
        if (row && !row.hidden) {
            row.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            return;
        }
        if (!row) {
            row = document.createElement('tr');
            row.id = id;
            row.className = 'meditrendy-variation-stock-row';
            const cell = row.insertCell();
            cell.colSpan = productRow.cells.length;
            cell.setAttribute('role', 'region');
            cell.setAttribute('aria-live', 'polite');
            productRow.after(row);
        }
        row.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        if (row.dataset.loading) return;
        const cell = row.cells[0];
        cell.textContent = meditrendyVariationStock.loading;
        cell.setAttribute('aria-busy', 'true');
        row.dataset.loading = 'true';
        try {
            const response = await fetch(meditrendyVariationStock.url, {
                method: 'POST',
                credentials: 'same-origin',
                body: new URLSearchParams({ action: 'meditrendy_variation_stock', nonce: meditrendyVariationStock.nonce, product_id: toggle.dataset.productId })
            });
            const result = await response.json();
            if (!response.ok || !result.success || typeof result.data.html !== 'string') throw new Error('Invalid response');
            cell.innerHTML = result.data.html;
        } catch (error) {
            cell.textContent = meditrendyVariationStock.error;
        } finally {
            delete row.dataset.loading;
            cell.removeAttribute('aria-busy');
        }
    });
})();
