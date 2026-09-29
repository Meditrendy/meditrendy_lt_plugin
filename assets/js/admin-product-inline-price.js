(() => {
    const config = window.meditrendyInlinePrice;
    if (!config) return;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.meditrendy-inline-price');
        if (!button || button.closest('.meditrendy-inline-price-editor')) return;

        const editor = document.createElement('form');
        editor.className = 'meditrendy-inline-price-editor';
        editor.noValidate = true;

        const addField = (name, label, value, required) => {
            const wrapper = document.createElement('label');
            wrapper.textContent = label;
            const input = document.createElement('input');
            input.type = 'text';
            input.inputMode = 'decimal';
            input.name = name;
            input.value = value.replace('.', config.decimalSeparator);
            input.required = required;
            input.autocomplete = 'off';
            wrapper.append(input);
            editor.append(wrapper);
            return input;
        };

        const regular = addField('regular', config.regular, button.dataset.regular, true);
        const sale = addField('sale', config.sale, button.dataset.sale, false);
        const controls = document.createElement('div');
        controls.className = 'meditrendy-inline-price-controls';
        const save = document.createElement('button');
        save.type = 'submit';
        save.className = 'button button-primary';
        save.textContent = config.save;
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'button';
        cancel.textContent = config.cancel;
        controls.append(save, cancel);
        editor.append(controls);
        const message = document.createElement('span');
        message.className = 'meditrendy-inline-price-error';
        message.setAttribute('role', 'alert');
        editor.append(message);

        const restore = () => {
            editor.replaceWith(button);
            button.focus();
        };
        cancel.addEventListener('click', restore);
        editor.addEventListener('keydown', (keyEvent) => {
            if (keyEvent.key === 'Escape') {
                keyEvent.preventDefault();
                restore();
            }
        });
        editor.addEventListener('submit', async (submitEvent) => {
            submitEvent.preventDefault();
            message.textContent = '';
            if (!regular.value.trim()) {
                regular.focus();
                return;
            }

            save.disabled = true;
            cancel.disabled = true;
            save.textContent = config.saving;
            const payload = new URLSearchParams({
                action: 'meditrendy_inline_price',
                nonce: config.nonce,
                product_id: button.dataset.productId,
                regular: regular.value.trim(),
                sale: sale.value.trim(),
            });

            try {
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: payload.toString(),
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.data?.message || config.error);
                }
                button.dataset.regular = result.data.regular;
                button.dataset.sale = result.data.sale;
                button.querySelector('.meditrendy-inline-price-display').innerHTML = result.data.html;
                restore();
            } catch (error) {
                message.textContent = error.message || config.error;
                save.disabled = false;
                cancel.disabled = false;
                save.textContent = config.save;
            }
        });

        button.replaceWith(editor);
        regular.focus();
        regular.select();
    });
})();
