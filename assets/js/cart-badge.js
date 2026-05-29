(function () {
    const settings = window.MeditrendyCartBadge || {};
    const cartTriggerSelector = settings.cartTriggerSelector || '.x-anchor.xoo-wsc-cart-trigger';
    const sourceCountSelector = settings.sourceCountSelector || '.xoo-wsc-items-count,.xoo-wsch-items-count,.xoo-wscb-count,.xoo-wsc-sc-count,[data-csdc-wc="cart-items"]';

    let cartCount = parseCount(settings.count) || 0;
    let queuedCount = null;
    let renderQueued = false;

    function parseCount(value) {
        const count = parseInt(String(value || '').replace(/[^\d]/g, ''), 10);

        return Number.isFinite(count) ? count : null;
    }

    function readPageCount(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const countElements = scope.querySelectorAll(sourceCountSelector);

        for (const element of countElements) {
            const count = parseCount(element.textContent);

            if (count !== null) {
                return count;
            }
        }

        return null;
    }

    function readFragmentCount(fragments) {
        if (!fragments || typeof fragments !== 'object') {
            return null;
        }

        const holder = document.createElement('div');

        for (const fragment of Object.values(fragments)) {
            if (typeof fragment !== 'string') {
                continue;
            }

            holder.innerHTML = fragment;

            const count = readPageCount(holder);

            if (count !== null) {
                return count;
            }
        }

        return null;
    }

    function render(nextCount) {
        const parsedCount = nextCount !== undefined ? parseCount(nextCount) : readPageCount(document);
        cartCount = parsedCount !== null ? parsedCount : cartCount;

        const cartButtons = document.querySelectorAll(cartTriggerSelector);

        if (!cartButtons.length) {
            return;
        }

        document.querySelectorAll('.meditrendy-cart-count').forEach((badge) => badge.remove());
        document.querySelectorAll('.meditrendy-cart-toggle').forEach((toggle) => {
            toggle.classList.remove('meditrendy-cart-toggle');
        });

        cartButtons.forEach((cartButton) => {
            const badgeTarget = cartButton.querySelector('.x-graphic') || cartButton;

            cartButton.classList.add('meditrendy-cart-trigger');
            badgeTarget.classList.add('meditrendy-cart-toggle');
            badgeTarget.querySelectorAll('.xoo-wsc-items-count, .xoo-wsch-items-count, .xoo-wscb-count, .xoo-wsc-sc-count')
                .forEach((legacyBadge) => legacyBadge.remove());

            if (cartCount <= 0) {
                return;
            }

            const badge = document.createElement('span');
            badge.className = 'meditrendy-cart-count';
            badge.textContent = cartCount;
            badgeTarget.appendChild(badge);
        });
    }

    function queueRender(nextCount) {
        const parsedCount = nextCount !== undefined ? parseCount(nextCount) : null;

        if (parsedCount !== null) {
            cartCount = parsedCount;
            queuedCount = parsedCount;
        }

        if (renderQueued) {
            return;
        }

        renderQueued = true;

        const schedule = window.requestAnimationFrame || window.setTimeout;

        schedule(() => {
            renderQueued = false;
            render(queuedCount !== null ? queuedCount : undefined);
            queuedCount = null;
        });
    }

    function bindCartEvents() {
        if (!window.jQuery || window.meditrendyCartBadgeEventsReady) {
            return;
        }

        window.meditrendyCartBadgeEventsReady = true;

        window.jQuery(document.body).on(
            'added_to_cart removed_from_cart updated_cart_totals wc_fragments_loaded wc_fragments_refreshed xoo_wsc_quantity_updated',
            (event, fragments) => queueRender(readFragmentCount(fragments))
        );

        window.jQuery(document.body).on('xoo_wsc_cart_updated', (event, response) => {
            queueRender(readFragmentCount(response && response.fragments));
        });

        window.jQuery(document.body).on('meditrendy_side_cart_updated', (event, data) => {
            queueRender(data && data.count);
        });
    }

    function watchLateCartMarkup() {
        if (!window.MutationObserver || !document.body) {
            return;
        }

        const observer = new MutationObserver((mutations) => {
            const shouldRender = mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) => {
                if (node.nodeType !== 1) {
                    return false;
                }

                return (
                    node.matches(cartTriggerSelector) ||
                    node.matches(sourceCountSelector) ||
                    node.querySelector(cartTriggerSelector) ||
                    node.querySelector(sourceCountSelector)
                );
            }));

            if (shouldRender) {
                queueRender();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    function init() {
        render(cartCount);
        document.addEventListener('meditrendy_side_cart_updated', (event) => {
            queueRender(event.detail && event.detail.count);
        });
        bindCartEvents();
        watchLateCartMarkup();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    window.addEventListener('load', () => {
        bindCartEvents();
        queueRender();
    }, { once: true });
}());
