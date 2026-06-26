(function () {
    const formSelector = 'form.variations_form';
    const outOfStockClass = 'mt-woosb-option-out-of-stock';
    const tooltipMarker = 'mtWoosbTooltip';
    const cssEscape = window.CSS && window.CSS.escape
        ? window.CSS.escape.bind(window.CSS)
        : function (value) {
            return String(value).replace(/["\\]/g, '\\$&');
        };

    function getStockTooltip() {
        if (window.woo_variation_swatches_options && window.woo_variation_swatches_options.out_of_stock_tooltip_text) {
            return window.woo_variation_swatches_options.out_of_stock_tooltip_text;
        }

        return 'Out of stock';
    }

    function readVariations(form) {
        const raw = form.getAttribute('data-product_variations') || '';

        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                // Some Woo scripts decode the attribute through jQuery data.
            }
        }

        if (window.jQuery) {
            const data = window.jQuery(form).data('product_variations');
            return Array.isArray(data) ? data : [];
        }

        return [];
    }

    function getAttributeName(select) {
        return select.getAttribute('data-attribute_name') || select.getAttribute('name') || '';
    }

    function getSelectedAttributes(form) {
        const selected = {};

        form.querySelectorAll('.variations select').forEach(function (select) {
            const attributeName = getAttributeName(select);

            if (attributeName) {
                selected[attributeName] = select.value || '';
            }
        });

        return selected;
    }

    function variationMatches(variationAttributes, selectedAttributes) {
        return Object.keys(variationAttributes || {}).every(function (attributeName) {
            const variationValue = variationAttributes[attributeName] || '';
            const selectedValue = selectedAttributes[attributeName] || '';

            return !variationValue || !selectedValue || variationValue === selectedValue;
        });
    }

    function variationCanBeBought(variation) {
        return variation
            && variation.is_in_stock !== false
            && variation.is_purchasable !== false
            && variation.variation_is_active !== false
            && variation.variation_is_visible !== false;
    }

    function hasAvailableVariation(variations, selectedAttributes, attributeName, optionValue) {
        const testAttributes = Object.assign({}, selectedAttributes, {
            [attributeName]: optionValue
        });

        return variations.some(function (variation) {
            return variationCanBeBought(variation) && variationMatches(variation.attributes, testAttributes);
        });
    }

    function getSwatchItems(form, attributeName, optionValue) {
        const wrapper = form.querySelector(
            '.variable-items-wrapper[data-attribute_name="' + cssEscape(attributeName) + '"]'
        );

        if (!wrapper) {
            return [];
        }

        return Array.from(wrapper.querySelectorAll(
            '.variable-item[data-value="' + cssEscape(optionValue) + '"]'
        ));
    }

    function setSwatchState(item, isOutOfStock) {
        item.classList.toggle(outOfStockClass, isOutOfStock);
        item.classList.remove('no-stock');
        item.removeAttribute('aria-disabled');

        if (isOutOfStock) {
            if (!item.getAttribute('data-wvstooltip-out-of-stock')) {
                item.setAttribute('data-wvstooltip-out-of-stock', getStockTooltip());
                item.dataset[tooltipMarker] = '1';
            }

            return;
        }

        if (item.dataset[tooltipMarker] === '1') {
            item.removeAttribute('data-wvstooltip-out-of-stock');
            delete item.dataset[tooltipMarker];
        }
    }

    function setOptionState(form, select, option, isOutOfStock) {
        option.classList.toggle(outOfStockClass, isOutOfStock);

        if (isOutOfStock) {
            option.dataset.mtWoosbOutOfStock = '1';
        } else {
            delete option.dataset.mtWoosbOutOfStock;
        }

        getSwatchItems(form, getAttributeName(select), option.value).forEach(function (item) {
            setSwatchState(item, isOutOfStock);
        });
    }

    function refreshForm(form) {
        if (form.classList.contains('woosb_variations_form')) {
            return;
        }

        const variations = readVariations(form);

        if (!variations.length) {
            return;
        }

        const selectedAttributes = getSelectedAttributes(form);

        form.querySelectorAll('.variations select').forEach(function (select) {
            const attributeName = getAttributeName(select);

            if (!attributeName) {
                return;
            }

            Array.from(select.options).forEach(function (option) {
                if (!option.value) {
                    return;
                }

                const isOutOfStock = !hasAvailableVariation(
                    variations,
                    selectedAttributes,
                    attributeName,
                    option.value
                );

                setOptionState(form, select, option, isOutOfStock);
            });
        });
    }

    function refreshAll(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const forms = scope.matches && scope.matches(formSelector)
            ? [scope]
            : Array.from(scope.querySelectorAll(formSelector));

        forms.forEach(refreshForm);
    }

    function scheduleRefresh(root) {
        const schedule = window.requestAnimationFrame
            ? window.requestAnimationFrame.bind(window)
            : window.setTimeout.bind(window);

        schedule(function () {
            refreshAll(root || document);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        scheduleRefresh(document);
        window.setTimeout(function () {
            refreshAll(document);
        }, 250);
    });

    document.addEventListener('change', function (event) {
        if (!event.target || !event.target.closest) {
            return;
        }

        const form = event.target.closest(formSelector);

        if (form) {
            scheduleRefresh(form);
        }
    }, true);

    if (window.jQuery) {
        const $document = window.jQuery(document);
        const formEvents = [
            'woocommerce_variation_has_changed',
            'found_variation',
            'reset_data',
            'hide_variation',
            'show_variation'
        ].join(' ');

        $document.on(formEvents, formSelector, function () {
            scheduleRefresh(this);
        });

        $document.on('wc_variation_form.wvs woo_variation_swatches_loaded woo_variation_swatches_init woosb_init woosb_found_variation woosb_reset_data', function () {
            scheduleRefresh(document);
        });
    }

    if (window.MutationObserver) {
        const observer = new MutationObserver(function (mutations) {
            const shouldRefresh = mutations.some(function (mutation) {
                return Array.from(mutation.addedNodes).some(function (node) {
                    return node.nodeType === 1
                        && ((node.matches && node.matches(formSelector))
                            || (node.querySelector && node.querySelector(formSelector)));
                });
            });

            if (shouldRefresh) {
                scheduleRefresh(document);
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            if (document.body) {
                observer.observe(document.body, { childList: true, subtree: true });
            }
        });
    }
}());
