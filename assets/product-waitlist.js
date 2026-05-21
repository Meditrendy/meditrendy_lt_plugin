(function () {
    var config = window.MeditrendyProductWaitlist || {};
    var labels = config.labels || {};
    var product = config.product || {};
    var selectedProductId = 0;
    var waitlistLink;
    var modal;
    var emailInput;
    var notice;
    var submitButton;

    function text(key, fallback) {
        return labels[key] || fallback;
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function getVariationForm() {
        return document.querySelector('form.variations_form');
    }

    function findInsertTarget() {
        return getVariationForm() ||
            document.querySelector('form.cart') ||
            document.querySelector('.summary') ||
            document.querySelector('.entry-summary');
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

        var target = findInsertTarget();
        if (target && target.parentNode) {
            target.parentNode.insertBefore(waitlistLink, target.nextSibling);
        }

        return waitlistLink;
    }

    function showLink(productId) {
        selectedProductId = parseInt(productId, 10) || 0;
        ensureLink().hidden = !selectedProductId;
    }

    function hideLink() {
        selectedProductId = 0;
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

        var email = emailInput.value.trim();

        if (!selectedProductId || !isValidEmail(email)) {
            setNotice(text('invalidEmail', 'Įveskite teisingą el. pašto adresą.'), 'error');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'meditrendy_stock_waitlist_subscribe');
        formData.append('nonce', config.nonce || '');
        formData.append('product_id', selectedProductId);
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
        var form = getVariationForm();
        var variations = form && window.jQuery ? window.jQuery(form).data('product_variations') : null;

        if (!variations || !variations.length) {
            return null;
        }

        variationId = parseInt(variationId, 10);

        for (var i = 0; i < variations.length; i += 1) {
            if (parseInt(variations[i].variation_id, 10) === variationId) {
                return variations[i];
            }
        }

        return null;
    }

    function updateForVariation(variation) {
        var variationId = variation && variation.variation_id ? parseInt(variation.variation_id, 10) : 0;
        var isOutOfStock = !!(variationId && variation.is_in_stock === false);

        if (isOutOfStock) {
            showLink(variationId);
        } else {
            hideLink();
        }
    }

    function bindVariableProduct() {
        if (!window.jQuery) {
            return;
        }

        var $body = window.jQuery(document.body);

        $body.on('found_variation', 'form.variations_form', function (event, variation) {
            updateForVariation(variation);
        });

        $body.on('reset_data hide_variation', 'form.variations_form', function () {
            hideLink();
        });

        var variationInput = document.querySelector('form.variations_form input.variation_id');
        if (variationInput && variationInput.value) {
            updateForVariation(readVariationById(variationInput.value));
        }
    }

    function init() {
        ensureLink();

        if (product.isVariable) {
            bindVariableProduct();
            return;
        }

        if (product.productId && product.isInStock === false) {
            showLink(product.productId);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
