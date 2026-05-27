(function () {
    const config = window.MeditrendyProductWaitlist || {};
    const labels = config.labels || {};
    const product = config.product || {};
    const unavailableSetSelections = new Map();
    let selectedProductId = 0;
    let selectedSetId = 0;
    let waitlistHint;
    let waitlistLink;
    let modal;
    let emailInput;
    let notice;
    let submitButton;

    function text(key, fallback) {
        return labels[key] || fallback;
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function getVariationForm() {
        return document.querySelector('form.variations_form');
    }

    function getSetWrap(setId) {
        return setId ? document.querySelector('.woosb-wrap[data-id="' + setId + '"]') : document.querySelector('.woosb-wrap');
    }

    function getVariationSelectorTarget(form) {
        if (!form) {
            return null;
        }

        return form.querySelector('.variations') ||
            form.querySelector('.woo-variation-items-wrapper') ||
            form.querySelector('.variable-items-wrapper') ||
            form.querySelector('select[name^="attribute_"]');
    }

    function findInsertTarget() {
        if (product.isSet) {
            return getSetWrap(product.productId) || document.querySelector('form.cart');
        }

        const form = getVariationForm();

        return getVariationSelectorTarget(form) ||
            form ||
            document.querySelector('form.cart') ||
            document.querySelector('.summary') ||
            document.querySelector('.entry-summary');
    }

    function shouldShowHint() {
        if (product.isVariable) {
            return !!(product.hasUnavailableVariation || hasUnavailableVariations(getVariationForm()));
        }

        if (product.isSet) {
            const setId = product.productId;

            return !!(
                product.hasUnavailableSetItem ||
                hasSimpleUnavailableSetItem(setId) ||
                getSetForms(setId).some(hasUnavailableVariations)
            );
        }

        return false;
    }

    function ensureHint() {
        if (waitlistHint || !shouldShowHint()) {
            return waitlistHint;
        }

        waitlistHint = document.createElement('p');
        waitlistHint.className = 'mt-product-waitlist-hint';
        waitlistHint.textContent = product.isSet
            ? text('setHint', 'Pasirinkite visų rinkinio prekių variantus. Jei bent vienas jų išparduotas, galėsite užsiregistruoti į laukimo sąrašą.')
            : text('hint', 'Pasirinkite išparduotą variantą, kad galėtumėte užsiregistruoti į laukimo sąrašą.');

        const target = findInsertTarget();

        if (target && target.parentNode) {
            target.parentNode.insertBefore(waitlistHint, target.nextSibling);
        }

        return waitlistHint;
    }

    function ensureLink() {
        if (waitlistLink) {
            return waitlistLink;
        }

        waitlistLink = document.createElement('button');
        waitlistLink.type = 'button';
        waitlistLink.className = 'mt-product-waitlist-link';
        waitlistLink.textContent = text('link', 'Informuokite mane, kai bus prekyboje');
        waitlistLink.hidden = true;
        waitlistLink.addEventListener('click', openModal);

        const target = ensureHint() || findInsertTarget();

        if (target && target.parentNode) {
            target.parentNode.insertBefore(waitlistLink, target.nextSibling);
        }

        return waitlistLink;
    }

    function showLink(productId, setId) {
        selectedProductId = parseInt(productId, 10) || 0;
        selectedSetId = parseInt(setId, 10) || 0;
        ensureLink().hidden = !selectedProductId;
    }

    function hideLink() {
        selectedProductId = 0;
        selectedSetId = 0;
        ensureLink().hidden = true;
    }

    function ensureModal() {
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.className = 'mt-product-waitlist-modal';
        modal.hidden = true;
        modal.innerHTML =
            '<div class="mt-product-waitlist-backdrop" data-mt-waitlist-close></div>' +
            '<div class="mt-product-waitlist-dialog" role="dialog" aria-modal="true" aria-labelledby="mt-product-waitlist-heading">' +
                '<button type="button" class="mt-product-waitlist-close" aria-label="' + escapeHtml(text('close', 'Uždaryti')) + '" data-mt-waitlist-close></button>' +
                '<h2 id="mt-product-waitlist-heading">' + escapeHtml(text('heading', 'Pranešimas apie prekę')) + '</h2>' +
                '<p>' + escapeHtml(text('body', 'Įveskite el. pašto adresą ir informuosime, kai pasirinkta prekė vėl bus prekyboje.')) + '</p>' +
                '<form class="mt-product-waitlist-form">' +
                    '<label for="mt-product-waitlist-email">' + escapeHtml(text('email', 'El. pašto adresas')) + '</label>' +
                    '<input id="mt-product-waitlist-email" type="email" autocomplete="email" required>' +
                    '<button type="submit" class="mt-product-waitlist-submit">' + escapeHtml(text('submit', 'Informuokite mane')) + '</button>' +
                    '<div class="mt-product-waitlist-notice" aria-live="polite"></div>' +
                '</form>' +
            '</div>';

        document.body.appendChild(modal);

        emailInput = modal.querySelector('#mt-product-waitlist-email');
        notice = modal.querySelector('.mt-product-waitlist-notice');
        submitButton = modal.querySelector('.mt-product-waitlist-submit');

        modal.addEventListener('click', function (event) {
            if (event.target && event.target.hasAttribute('data-mt-waitlist-close')) {
                closeModal();
            }
        });

        modal.querySelector('form').addEventListener('submit', submitWaitlist);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && !modal.hidden) {
                closeModal();
            }
        });

        return modal;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function openModal() {
        if (!selectedProductId) {
            return;
        }

        ensureModal();
        setNotice('', '');
        modal.hidden = false;
        document.documentElement.classList.add('mt-product-waitlist-is-open');
        window.setTimeout(function () {
            emailInput.focus();
        }, 40);
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.documentElement.classList.remove('mt-product-waitlist-is-open');
    }

    function setNotice(message, type) {
        if (!notice) {
            return;
        }

        notice.textContent = message || '';
        notice.className = 'mt-product-waitlist-notice' + (type ? ' mt-product-waitlist-notice-' + type : '');
    }

    function setLoading(isLoading) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = isLoading;
        submitButton.classList.toggle('mt-product-waitlist-loading', isLoading);
    }

    function submitWaitlist(event) {
        event.preventDefault();

        const email = emailInput.value.trim();

        if (!selectedProductId || !isValidEmail(email)) {
            setNotice(text('invalidEmail', 'Įveskite teisingą el. pašto adresą.'), 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'meditrendy_stock_waitlist_subscribe');
        formData.append('nonce', config.nonce || '');
        formData.append('product_id', selectedProductId);
        formData.append('set_id', selectedSetId);
        formData.append('set_items', getSetItems(selectedSetId));
        formData.append('email', email);

        setLoading(true);
        setNotice('', '');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok || !json.success) {
                        throw json;
                    }

                    return json;
                });
            })
            .then(function (json) {
                setNotice((json.data && json.data.message) || text('success', 'Ačiū. Informuosime jus el. paštu, kai prekė vėl bus prekyboje.'), 'success');
                emailInput.value = '';
            })
            .catch(function (error) {
                setNotice((error && error.data && error.data.message) || text('error', 'Nepavyko išsaugoti. Bandykite dar kartą.'), 'error');
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function readVariationById(variationId) {
        const form = getVariationForm();
        const variations = form && window.jQuery ? window.jQuery(form).data('product_variations') : null;

        if (!variations || !variations.length) {
            return null;
        }

        const id = parseInt(variationId, 10);

        for (let i = 0; i < variations.length; i += 1) {
            if (parseInt(variations[i].variation_id, 10) === id) {
                return variations[i];
            }
        }

        return null;
    }

    function readFormVariations(form) {
        if (!form) {
            return [];
        }

        if (window.jQuery) {
            const jqueryVariations = window.jQuery(form).data('product_variations');

            if (Array.isArray(jqueryVariations)) {
                return jqueryVariations;
            }
        }

        const rawVariations = form.getAttribute('data-product_variations');

        if (!rawVariations) {
            return [];
        }

        try {
            const variations = JSON.parse(rawVariations);

            return Array.isArray(variations) ? variations : [];
        } catch (error) {
            return [];
        }
    }

    function hasUnavailableVariations(form) {
        return readFormVariations(form).some(function (variation) {
            return variation && variation.variation_id && variation.is_in_stock === false;
        });
    }

    function getAttributeName(select) {
        if (!select) {
            return '';
        }

        const name = select.getAttribute('name') || select.dataset.attribute_name || '';

        return name.indexOf('attribute_') === 0 ? name : 'attribute_' + name;
    }

    function getSelectedAttributes(form) {
        const selected = {};
        const selects = form ? Array.from(form.querySelectorAll('select[name^="attribute_"], select[data-attribute_name]')) : [];

        selects.forEach(function (select) {
            const attributeName = getAttributeName(select);

            if (attributeName) {
                selected[attributeName] = select.value || '';
            }
        });

        return selected;
    }

    function hasCompleteAttributeSelection(selectedAttributes) {
        const names = Object.keys(selectedAttributes);

        return !!names.length && names.every(function (name) {
            return !!selectedAttributes[name];
        });
    }

    function variationMatchesSelection(variation, selectedAttributes) {
        const variationAttributes = variation && variation.attributes ? variation.attributes : {};

        return Object.keys(selectedAttributes).every(function (name) {
            const selectedValue = selectedAttributes[name];
            const variationValue = variationAttributes[name] || '';

            return !!selectedValue && (!variationValue || variationValue === selectedValue);
        });
    }

    function findSelectedVariation(form) {
        const selectedAttributes = getSelectedAttributes(form);

        if (!hasCompleteAttributeSelection(selectedAttributes)) {
            return null;
        }

        return readFormVariations(form).find(function (variation) {
            return variationMatchesSelection(variation, selectedAttributes);
        }) || null;
    }

    function getSetIdFromForm(form) {
        const wrap = form && form.closest ? form.closest('.woosb-wrap') : null;

        if (wrap && wrap.dataset && wrap.dataset.id) {
            return parseInt(wrap.dataset.id, 10) || 0;
        }

        return product.isSet ? parseInt(product.productId, 10) || 0 : 0;
    }

    function readSelectedVariationId(form) {
        const input = form ? form.querySelector('input.variation_id') : null;

        if (input && input.value) {
            return parseInt(input.value, 10) || 0;
        }

        const selectedVariation = findSelectedVariation(form);

        return selectedVariation && selectedVariation.variation_id ? parseInt(selectedVariation.variation_id, 10) || 0 : 0;
    }

    function getSetForms(setId) {
        const wrap = getSetWrap(setId);

        if (!wrap) {
            return [];
        }

        return Array.from(wrap.querySelectorAll('.woosb-product form.variations_form'));
    }

    function setHasCompleteVariationSelection(setId) {
        const forms = getSetForms(setId);

        return forms.every(function (form) {
            return !!(unavailableSetSelections.get(form) || readSelectedVariationId(form));
        });
    }

    function setHasUnavailableSelection(setId) {
        return getSetForms(setId).some(function (form) {
            const selectedVariation = findSelectedVariation(form);

            return !!unavailableSetSelections.get(form) || !!(selectedVariation && selectedVariation.is_in_stock === false);
        });
    }

    function getSetItems(setId) {
        const wrap = getSetWrap(setId);
        const items = [];

        if (!wrap) {
            return '';
        }

        wrap.querySelectorAll('.woosb-product').forEach(function (setProduct) {
            const qty = parseFloat(setProduct.dataset.qty || '0');
            const key = setProduct.dataset.key || '';
            const form = setProduct.querySelector('form.variations_form');
            const unavailableSelection = form ? unavailableSetSelections.get(form) : null;
            const selectedVariation = form ? readSelectedVariationId(form) : 0;
            const id = unavailableSelection || selectedVariation || parseInt(setProduct.dataset.id || '0', 10);

            if (id > 0 && qty > 0 && key) {
                items.push(id + '/' + key + '/' + qty);
            }
        });

        return items.join(',');
    }

    function hasSimpleUnavailableSetItem(setId) {
        const wrap = getSetWrap(setId);

        if (!wrap) {
            return false;
        }

        return Array.from(wrap.querySelectorAll('.woosb-product-unpurchasable')).some(function (setProduct) {
            return !setProduct.querySelector('form.variations_form');
        });
    }

    function refreshSetLink(setId) {
        if (!setHasCompleteVariationSelection(setId)) {
            hideLink();
            return;
        }

        if (setHasUnavailableSelection(setId) || hasSimpleUnavailableSetItem(setId)) {
            showLink(setId, setId);
            return;
        }

        hideLink();
    }

    function updateForSetVariation(variation, form) {
        const setId = getSetIdFromForm(form);
        const selectedVariation = variation && variation.variation_id ? variation : findSelectedVariation(form);
        const variationId = selectedVariation && selectedVariation.variation_id ? parseInt(selectedVariation.variation_id, 10) : 0;

        if (variationId && selectedVariation.is_in_stock === false) {
            unavailableSetSelections.set(form, variationId);
        } else {
            unavailableSetSelections.delete(form);
        }

        refreshSetLink(setId);
    }

    function updateForVariation(variation) {
        const variationId = variation && variation.variation_id ? parseInt(variation.variation_id, 10) : 0;
        const isOutOfStock = !!(variationId && variation.is_in_stock === false);

        if (isOutOfStock) {
            showLink(variationId, 0);
        } else {
            hideLink();
        }
    }

    function bindVariableProduct() {
        if (!window.jQuery) {
            return;
        }

        const $body = window.jQuery(document.body);

        $body.on('found_variation', 'form.variations_form', function (event, variation) {
            if (product.isSet && this.closest('.woosb-wrap')) {
                updateForSetVariation(variation, this);
                return;
            }

            updateForVariation(variation);
        });

        $body.on('reset_data hide_variation', 'form.variations_form', function () {
            if (product.isSet && this.closest('.woosb-wrap')) {
                updateForSetVariation(null, this);
                return;
            }

            hideLink();
        });

        $body.on('woocommerce_variation_has_changed change', 'form.variations_form', function () {
            if (product.isSet && this.closest('.woosb-wrap')) {
                const form = this;

                window.setTimeout(function () {
                    updateForSetVariation(null, form);
                }, 0);
            }
        });

        const variationInput = document.querySelector('form.variations_form input.variation_id');

        if (variationInput && variationInput.value && !product.isSet) {
            updateForVariation(readVariationById(variationInput.value));
        }
    }

    function init() {
        ensureHint();
        ensureLink();

        if (product.isVariable || product.isSet) {
            bindVariableProduct();
        }

        if (product.isSet) {
            refreshSetLink(product.productId);
            return;
        }

        if (product.isVariable) {
            return;
        }

        if (product.productId && product.isInStock === false) {
            showLink(product.productId, 0);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
