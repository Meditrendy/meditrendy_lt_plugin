(() => {
  const settings = window.MeditrendySideCart || {};
  const cartSelector = '[data-mt-side-cart]';
  const triggerSelector = settings.cartTriggerSelector || 'header .x-anchor.xoo-wsc-cart-trigger, header .meditrendy-cart-toggle, header a[href*="/cart"]';
  const ajaxUrl = settings.ajaxUrl || '';
  const nonce = settings.nonce || '';
  let drawer = null;
  let inner = null;
  let isRequesting = false;
  let activeAddForm = null;

  const parseCount = (value) => {
    const count = parseInt(String(value || '').replace(/[^\d]/g, ''), 10);
    return Number.isFinite(count) ? count : 0;
  };

  const setLoading = (state) => {
    if (!drawer) return;
    drawer.classList.toggle('is-loading', !!state);
    drawer.setAttribute('aria-busy', state ? 'true' : 'false');
  };

  const setAddFormLoading = (form, state) => {
    if (!form) return;

    form.classList.toggle('mt-side-cart-add-loading', !!state);
    form.setAttribute('aria-busy', state ? 'true' : 'false');

    const submit = form.querySelector('[type="submit"], button[name="add-to-cart"]');

    if (submit) {
      submit.disabled = !!state;
    }
  };

  const broadcastUpdate = (data) => {
    const count = parseCount(data && data.count);

    document.dispatchEvent(new CustomEvent('meditrendy_side_cart_updated', {
      detail: { count, data },
    }));

    if (window.jQuery) {
      window.jQuery(document.body).trigger('meditrendy_side_cart_updated', [data]);
    }
  };

  const replaceContent = (data) => {
    if (!inner || !data || typeof data.html !== 'string') return;

    inner.innerHTML = data.html;
    broadcastUpdate(data);
  };

  const isSoftBundleAddError = (action, formData, message) => {
    if (action !== 'meditrendy_side_cart_add' || !(formData instanceof window.FormData)) return false;

    const hasBundleIds = String(formData.get('woosb_ids') || '').trim() !== '';
    const text = String(message || '').toLowerCase();

    return hasBundleIds && (
      text.includes('un-purchasable') ||
      text.includes('unpurchasable') ||
      text.includes('not purchasable') ||
      text.includes('cannot be purchased')
    );
  };

  const request = async (action, body = {}) => {
    if (!ajaxUrl || !nonce) return null;

    isRequesting = true;
    setLoading(true);

    const formData = body instanceof window.FormData ? body : new window.FormData();

    if (!(body instanceof window.FormData)) {
      Object.entries(body).forEach(([key, value]) => {
        formData.append(key, value);
      });
    }

    formData.set('action', action);
    formData.set('nonce', nonce);

    try {
      const response = await window.fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      });
      const payload = await response.json();

      if (payload && payload.success && payload.data) {
        replaceContent(payload.data);
        return payload.data;
      }

      if (payload && payload.data && payload.data.message) {
        if (isSoftBundleAddError(action, formData, payload.data.message)) {
          return await request('meditrendy_side_cart_get');
        }

        throw new Error(payload.data.message);
      }
    } catch (error) {
      if (window.console) {
        window.console.warn('Meditrendy side cart request failed.', error);
      }

      if (error && error.message) {
        window.alert(error.message);
      }
    } finally {
      isRequesting = false;
      setLoading(false);

      if (activeAddForm) {
        setAddFormLoading(activeAddForm, false);
        activeAddForm = null;
      }
    }

    return null;
  };

  const open = async (shouldRefresh = false) => {
    if (!drawer) return;

    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('mt-side-cart-is-open');

    if (shouldRefresh && !isRequesting) {
      await request('meditrendy_side_cart_get');
    }
  };

  const close = () => {
    if (!drawer) return;

    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('mt-side-cart-is-open');
  };

  const closestCartItem = (target) => target && target.closest ? target.closest('[data-cart-item-key]') : null;

  const readQuantity = (item) => {
    const input = item ? item.querySelector('[data-mt-side-cart-quantity] input') : null;
    return Math.max(0, parseInt(input ? input.value : '1', 10) || 1);
  };

  const setQuantity = async (item, quantity) => {
    if (!item || isRequesting) return;

    await request('meditrendy_side_cart_update', {
      cart_item_key: item.dataset.cartItemKey || '',
      quantity: Math.max(0, quantity),
    });
  };

  const handleTriggerClick = (event) => {
    const trigger = event.target.closest ? event.target.closest(triggerSelector) : null;

    if (!trigger || !document.body.contains(trigger)) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }

    open(false);
  };

  const getAddToCartForm = (target) => {
    const form = target && target.closest ? target.closest('form.cart') : null;

    if (!form || !document.body.contains(form)) {
      return null;
    }

    return form.querySelector('[name="add-to-cart"], [name="product_id"], [name="variation_id"], [name="woosb_ids"]') ? form : null;
  };

  const handleAddToCartSubmit = async (event) => {
    const form = getAddToCartForm(event.target);

    if (event.defaultPrevented || !form || isRequesting || form.classList.contains('variations_form') && form.querySelector('.wc-variation-selection-needed')) {
      return;
    }

    event.preventDefault();

    const formData = new window.FormData(form);
    const submitter = event.submitter || document.activeElement;

    if (submitter && submitter.name && !formData.has(submitter.name)) {
      formData.append(submitter.name, submitter.value || '');
    }

    const addToCart = form.querySelector('[name="add-to-cart"]');

    if (addToCart && addToCart.name && !formData.has(addToCart.name)) {
      formData.append(addToCart.name, addToCart.value || '');
    }

    activeAddForm = form;
    setAddFormLoading(form, true);

    const data = await request('meditrendy_side_cart_add', formData);

    if (data) {
      open(false);
    }
  };

  const handleDrawerClick = (event) => {
    if (!drawer || !drawer.contains(event.target)) return;

    if (event.target.closest('[data-mt-side-cart-close]')) {
      event.preventDefault();
      close();
      return;
    }

    if (isRequesting) {
      return;
    }

    const remove = event.target.closest('[data-mt-side-cart-remove]');

    if (remove) {
      event.preventDefault();
      setQuantity(closestCartItem(remove), 0);
      return;
    }

    const quantityButton = event.target.closest('[data-mt-side-cart-qty]');

    if (quantityButton) {
      event.preventDefault();

      const item = closestCartItem(quantityButton);
      const input = item ? item.querySelector('[data-mt-side-cart-quantity] input') : null;
      const delta = parseInt(quantityButton.dataset.mtSideCartQty || '0', 10);
      const current = readQuantity(item);
      const max = input && input.max ? parseInt(input.max, 10) : 0;
      let next = Math.max(1, current + delta);

      if (max > 0) {
        next = Math.min(next, max);
      }

      if (input) {
        input.value = next;
      }

      setQuantity(item, next);
    }
  };

  const handleQuantityChange = (event) => {
    const input = event.target.closest ? event.target.closest('[data-mt-side-cart-quantity] input') : null;

    if (!input || isRequesting) return;

    const item = closestCartItem(input);
    const max = input.max ? parseInt(input.max, 10) : 0;
    let next = Math.max(1, parseInt(input.value, 10) || 1);

    if (max > 0) {
      next = Math.min(next, max);
    }

    input.value = next;
    setQuantity(item, next);
  };

  const bindWooEvents = () => {
    if (!window.jQuery || window.meditrendySideCartWooEventsReady) return;

    window.meditrendySideCartWooEventsReady = true;

    window.jQuery(document.body).on('added_to_cart', async () => {
      await request('meditrendy_side_cart_get');
      open(false);
    });

    window.jQuery(document.body).on('removed_from_cart updated_cart_totals wc_fragments_refreshed wc_fragments_loaded', () => {
      request('meditrendy_side_cart_get');
    });
  };

  const init = () => {
    drawer = document.querySelector(cartSelector);
    inner = drawer ? drawer.querySelector('[data-mt-side-cart-inner]') : null;

    if (!drawer || !inner) return;

    document.addEventListener('click', handleTriggerClick, true);
    document.addEventListener('submit', handleAddToCartSubmit);
    document.addEventListener('click', handleDrawerClick);
    document.addEventListener('change', handleQuantityChange);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
        close();
      }
    });

    bindWooEvents();

    if (settings.openOnLoad) {
      open(true);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

  window.addEventListener('load', bindWooEvents, { once: true });
})();

/************************
 *  Swipe gesture
 ************************/

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const sideCart = document.querySelector('.mt-side-cart');

    if (!sideCart) {
        return;
    }

    const panel = sideCart.querySelector('.mt-side-cart-panel');

    if (!panel) {
        return;
    }

    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let currentY = 0;
    let isDragging = false;
    let isHorizontalSwipe = false;

    const minSwipeDistance = 80;
    const maxVerticalMovement = 90;

    function isCartOpen() {
        return sideCart.classList.contains('is-open');
    }

    function closeSideCart() {
        const closeButton = sideCart.querySelector('.mt-side-cart-close');

        if (closeButton) {
            closeButton.click();
            return;
        }

        sideCart.classList.remove('is-open');
        document.body.classList.remove('mt-side-cart-open');
    }

    function resetPanelPosition() {
        panel.style.transition = '';
        panel.style.transform = '';
    }

    function startDrag(event) {
        if (!isCartOpen()) {
            return;
        }

        if (!event.touches || event.touches.length !== 1) {
            return;
        }

        startX = event.touches[0].clientX;
        startY = event.touches[0].clientY;
        currentX = startX;
        currentY = startY;

        isDragging = true;
        isHorizontalSwipe = false;
    }

    function moveDrag(event) {
        if (!isDragging || !event.touches || event.touches.length !== 1) {
            return;
        }

        currentX = event.touches[0].clientX;
        currentY = event.touches[0].clientY;

        const diffX = currentX - startX;
        const diffY = currentY - startY;

        if (!isHorizontalSwipe) {
            isHorizontalSwipe = Math.abs(diffX) > 12 && Math.abs(diffX) > Math.abs(diffY);
        }

        if (!isHorizontalSwipe) {
            return;
        }

        if (diffX <= 0) {
            return;
        }

        event.preventDefault();

        panel.style.transition = 'none';
        panel.style.transform = 'translateX(' + diffX + 'px)';
    }

    function endDrag() {
        if (!isDragging) {
            return;
        }

        const diffX = currentX - startX;
        const diffY = currentY - startY;

        isDragging = false;

        if (
            isHorizontalSwipe &&
            diffX > minSwipeDistance &&
            Math.abs(diffY) < maxVerticalMovement
        ) {
            panel.style.transition = 'transform 180ms ease';
            panel.style.transform = 'translateX(100%)';

            setTimeout(function () {
                closeSideCart();
                resetPanelPosition();
            }, 170);

            return;
        }

        panel.style.transition = 'transform 180ms ease';
        panel.style.transform = 'translateX(0)';

        setTimeout(resetPanelPosition, 180);
    }

    panel.addEventListener('touchstart', startDrag, { passive: true });
    panel.addEventListener('touchmove', moveDrag, { passive: false });
    panel.addEventListener('touchend', endDrag);
    panel.addEventListener('touchcancel', endDrag);
});