(() => {
    let sequence = 0;
    const panel = document.querySelector('#woocommerce-order-items');
    if (!panel) return;
    const refresh = () => {
        const add = panel.querySelector('.med-add-custom-item');
        const product = panel.querySelector('.add-order-item');
        if (add && product && product.nextElementSibling !== add) product.after(add);
        panel.querySelectorAll('.med-custom-item').forEach((row) => {
            const data = row.querySelector('.med-custom-details');
            if (!data) return;
            [['.item_cost > .view', data.dataset.unitGross], ['.line_cost > .view', data.dataset.totalGross]].forEach(([selector, value]) => {
                const view = row.querySelector(selector);
                if (view && view.dataset.medGross !== value) {
                    // Keep native discount/refund annotations intact.
                    const amount = view.querySelector('.woocommerce-Price-amount');
                    if (amount) amount.outerHTML = value;
                    else view.insertAdjacentHTML('afterbegin', value);
                    view.dataset.medGross = value;
                }
            });
        });
    };
    refresh();
    new MutationObserver(refresh).observe(panel, {childList: true, subtree: true});
    document.addEventListener('click', (event) => {
        const panel = event.target.closest('#woocommerce-order-items');
        if (!panel) return;
        if (event.target.closest('.med-add-custom-item')) {
            const template = panel.querySelector('.med-custom-template');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = template.innerHTML.replaceAll('__KEY__', `new_${Date.now()}_${++sequence}`);
            const row = wrapper.firstElementChild;
            row.firstElementChild.colSpan = panel.querySelector('table.woocommerce_order_items thead tr').children.length;
            panel.querySelector('tbody#order_line_items').appendChild(row);
            row.querySelector('input').focus();
        }
        if (event.target.closest('.med-remove-custom-draft')) event.target.closest('tr').remove();
        // WooCommerce handles Save with delegated events. Validate first.
        if (event.target.closest('.save-action, .calculate-action, .add-order-item, .add-order-fee, .add-order-shipping')) {
            for (const input of panel.querySelectorAll('.med-custom-fields input')) {
                if (!input.checkValidity()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    const edit = input.closest('.edit');
                    if (edit) edit.style.display = '';
                    input.reportValidity();
                    break;
                }
            }
        }
    }, true);
})();
