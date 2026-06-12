(() => {
  if (window.meditrendySideCartReady) {
    return;
  }

  window.meditrendySideCartReady = true;

  const settings = window.MeditrendySideCart || {};
  const cartSelector = '[data-mt-side-cart]';
  const triggerSelector = settings.cartTriggerSelector || 'header .x-anchor.xoo-wsc-cart-trigger, header .meditrendy-cart-toggle, header a[href*="/cart"]';
  const ajaxUrl = settings.ajaxUrl || '';
  let nonce = settings.nonce || '';
  let drawer = null;
  let inner = null;
  let isRequesting = false;
  let activeAddForm = null;
  let activeAddRequestKey = '';
  let lastSessionRefresh = 0;
  let isUpsellsLoading = false;
  let cartMutationVersion = 0;
  let isEmittingSyntheticAddedToCart = false;

  const debugEnabled = (() => {
    try {
      return new URLSearchParams(window.location.search || '').get('mt_side_cart_debug') === '1';
    } catch (error) {
      return false;
    }
  })();

  const debug = (message, data = {}) => {
    if (!debugEnabled) return;

    window.MeditrendySideCartDebug = window.MeditrendySideCartDebug || [];
    window.MeditrendySideCartDebug.push({
      time: new Date().toISOString(),
      message,
      data,
    });

    if (window.console) {
      window.console.log('[Meditrendy side cart]', message, data);
    }
  };

  const currentSettings = () => window.MeditrendySideCart || settings || {};

  const currentAjaxUrl = () => {
    const configured = currentSettings().ajaxUrl || ajaxUrl;

    if (configured) {
      return configured;
    }

    return `${window.location.origin}/wp-admin/admin-ajax.php`;
  };

  const currentNonce = () => {
    if (nonce) {
      return nonce;
    }

    const configured = currentSettings().nonce || '';

    if (configured) {
      nonce = configured;
    }

    return nonce;
  };

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

    const submit = form.querySelector('[data-mt-side-cart-upsell-add], [type="submit"], button[name="add-to-cart"]');

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

  const emitAddToCartTracking = (tracking, trigger = null) => {
    if (!tracking || tracking.event !== 'add_to_cart') {
      return;
    }

    const ecommerce = {
      currency: tracking.currency || '',
      value: Number(tracking.value || 0),
      items: Array.isArray(tracking.items) ? tracking.items : [],
    };

    if (typeof window.gtm4wp_push_ecommerce === 'function') {
      window.gtm4wp_push_ecommerce('add_to_cart', ecommerce.items, {
        currency: ecommerce.currency,
        value: ecommerce.value,
      });
    } else {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({ ecommerce: null });
      window.dataLayer.push({
        event: 'add_to_cart',
        ecommerce,
      });
    }

    if (typeof window.gtag === 'function') {
      window.gtag('event', 'add_to_cart', ecommerce);
    }

    if (typeof window.fbq === 'function' && tracking.meta) {
      window.fbq('track', 'AddToCart', tracking.meta);
    }

    if (window.jQuery) {
      const $trigger = trigger ? window.jQuery(trigger) : window.jQuery();

      if (tracking.product_id) {
        $trigger
          .attr('data-product_id', tracking.product_id)
          .data('product_id', tracking.product_id);
      }

      if (tracking.quantity) {
        $trigger
          .attr('data-quantity', tracking.quantity)
          .data('quantity', tracking.quantity);
      }

      isEmittingSyntheticAddedToCart = true;

      try {
        window.jQuery(document.body).trigger('adding_to_cart', [$trigger, {
          product_id: tracking.product_id || '',
          quantity: tracking.quantity || 1,
        }]);

        window.jQuery(document.body).trigger('added_to_cart', [{}, '', $trigger]);
      } finally {
        window.setTimeout(() => {
          isEmittingSyntheticAddedToCart = false;
        }, 0);
      }
    }

    document.dispatchEvent(new CustomEvent('meditrendy_add_to_cart_tracked', {
      detail: tracking,
    }));
  };

  const stripHtml = (value) => {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = String(value || '');

    return (wrapper.textContent || wrapper.innerText || '').replace(/\s+/g, ' ').trim();
  };

  const readResponsePayload = async (response) => {
    const text = await response.text();

    try {
      return JSON.parse(text);
    } catch (error) {
      const message = stripHtml(text);

      throw new Error(
        message && message.length < 220
          ? message
          : 'Nepavyko atnaujinti krep\u0161elio. Bandykite dar kart\u0105.'
      );
    }
  };

  const isNonceFailure = (error) => {
    const message = String(error && error.message ? error.message : '').trim().toLowerCase();

    return message === '-1' || message.includes('nonce') || message.includes('security check');
  };

  const refreshNonce = async () => {
    const nonceAjaxUrl = currentAjaxUrl();

    if (!nonceAjaxUrl) {
      return false;
    }

    const formData = new window.FormData();
    formData.set('action', 'meditrendy_side_cart_nonce');

    try {
      const response = await window.fetch(nonceAjaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      });
      const payload = await readResponsePayload(response);
      const nextNonce = payload && payload.success && payload.data ? payload.data.nonce : '';

      if (!nextNonce) {
        return false;
      }

      nonce = nextNonce;
      return true;
    } catch (error) {
      if (window.console) {
        window.console.warn('Meditrendy side cart nonce refresh failed.', error);
      }
    }

    return false;
  };

  const setUpsellsLoading = (state) => {
    if (!inner) return;

    isUpsellsLoading = !!state;

    let section = inner.querySelector('[data-mt-side-cart-upsells]');

    if (!section && state) {
      const content = inner.querySelector('.mt-side-cart-content');

      if (!content) return;

      section = document.createElement('section');
      section.className = 'mt-side-cart-upsells';
      section.setAttribute('data-mt-side-cart-upsells', '');
      section.innerHTML = '<div class="mt-side-cart-upsells-header"><h2>Jums taip pat gali patikti</h2></div>';
      content.appendChild(section);
    }

    if (!section) return;

    section.classList.toggle('is-loading', !!state);
    section.setAttribute('aria-busy', state ? 'true' : 'false');
  };

  const replaceContent = (data) => {
    if (!inner || !data || typeof data.html !== 'string') {
      debug('replace skipped', {
        hasInner: !!inner,
        hasData: !!data,
        htmlType: typeof (data && data.html),
      });
      return;
    }

    inner.innerHTML = data.html;

    try {
      initDynamicCartContent();
      collapseUpsellLabels(inner);
    } catch (error) {
      debug('dynamic init failed after replace', {
        message: error && error.message ? error.message : String(error),
      });

      if (window.console) {
        window.console.warn('Meditrendy side cart content initialization failed.', error);
      }
    }

    try {
      broadcastUpdate(data);
    } catch (error) {
      debug('cart update broadcast failed', {
        message: error && error.message ? error.message : String(error),
      });

      if (window.console) {
        window.console.warn('Meditrendy side cart update broadcast failed.', error);
      }
    }
  };

  const prepareSingleOptionUpsellSelects = (root) => {
    const scope = root || document;

    scope.querySelectorAll('[data-mt-side-cart-upsell] select[name^="attribute_"]').forEach((select) => {
      const options = Array.from(select.options || []).filter((option) => option.value);

      if (options.length !== 1) {
        return;
      }

      select.value = options[0].value;

      const row = select.closest('label, .variation, tr');

      if (row) {
        row.hidden = true;
      }
    });
  };

  const initDynamicCartContent = () => {
    if (!inner) return;

    prepareSingleOptionUpsellSelects(inner);

    if (!window.jQuery) return;

    const $ = window.jQuery;

    if ($.fn.wc_variation_form) {
      $(inner).find('.variations_form').each(function () {
        const form = $(this);

        if (!form.data('product_variations_ready')) {
          form.wc_variation_form();
          form.data('product_variations_ready', true);
        }
      });
    }

    $(document.body).trigger('woosb_init');
    $(document.body).trigger('woosb_update');
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

  const isAddToCartAction = (action) => action === 'meditrendy_side_cart_add';

  const hasCartCookie = () => /(?:^|;\s*)woocommerce_items_in_cart=1(?:;|$)/.test(document.cookie || '') ||
    /(?:^|;\s*)woocommerce_cart_hash=([^;]+)/.test(document.cookie || '');

  const recoverAddedCartFromSession = async (action, options = {}) => {
    if (!isAddToCartAction(action) || !hasCartCookie()) {
      return null;
    }

    return request(
      'meditrendy_side_cart_get',
      { include_upsells: options.includeUpsells === false ? 0 : 1 },
      {
        blocking: false,
        silent: true,
        upsellsLoading: true,
      }
    );
  };

  const request = async (action, body = {}, options = {}) => {
    const requestAjaxUrl = currentAjaxUrl();

    if (!requestAjaxUrl) {
      return null;
    }

    if (!currentNonce() && !options.nonceRetry && !(await refreshNonce())) {
      return null;
    }

    const blocking = options.blocking !== false;
    const upsellsLoading = !!options.upsellsLoading;
    const requestMutationVersion = cartMutationVersion;

    if (blocking) {
      cartMutationVersion += 1;
      isRequesting = true;
      setLoading(true);
    }

    if (upsellsLoading) {
      setUpsellsLoading(true);
    }

    const formData = body instanceof window.FormData ? body : new window.FormData();

    if (!(body instanceof window.FormData)) {
      Object.entries(body).forEach(([key, value]) => {
        formData.append(key, value);
      });
    }

    formData.set('action', action);
    formData.set('nonce', currentNonce());

    try {
      const response = await window.fetch(requestAjaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
      });
      const payload = await readResponsePayload(response);

      debug('request response', {
        action,
        status: response.status,
        success: !!(payload && payload.success),
        hasData: !!(payload && payload.data),
        hasHtml: !!(payload && payload.data && payload.data.html),
        count: payload && payload.data ? payload.data.count : null,
        message: payload && payload.data ? payload.data.message : '',
      });

      if (payload && payload.success && payload.data) {
        if (payload.data.nonce) {
          nonce = payload.data.nonce;
        }

        if (blocking || requestMutationVersion === cartMutationVersion) {
          replaceContent(payload.data);
        }

        if (action === 'meditrendy_side_cart_add') {
          open(false);
        }

        try {
          emitAddToCartTracking(payload.data.tracking, options.trigger || null);
        } catch (error) {
          debug('tracking failed after cart update', {
            message: error && error.message ? error.message : String(error),
          });

          if (window.console) {
            window.console.warn('Meditrendy add-to-cart tracking failed.', error);
          }
        }

        return payload.data;
      }

      if (payload && payload.success && isAddToCartAction(action)) {
        const recoveredData = await recoverAddedCartFromSession(action, options);

        if (recoveredData) {
          return recoveredData;
        }
      }

      if (payload && payload.data && payload.data.message) {
        if (isSoftBundleAddError(action, formData, payload.data.message)) {
          return await request('meditrendy_side_cart_get', { include_upsells: 1 });
        }

        throw new Error(payload.data.message);
      }
    } catch (error) {
      debug('request failed', {
        action,
        message: error && error.message ? error.message : String(error),
      });

      if (window.console) {
        window.console.warn('Meditrendy side cart request failed.', error);
      }

      if (!options.nonceRetry && isNonceFailure(error) && await refreshNonce()) {
        return request(action, formData, {
          ...options,
          nonceRetry: true,
        });
      }

      const recoveredData = await recoverAddedCartFromSession(action, options);

      if (recoveredData) {
        return recoveredData;
      }

      if (!options.silent && error && error.message) {
        window.alert(error.message);
      }
    } finally {
      if (blocking) {
        isRequesting = false;
        setLoading(false);
      }

      if (upsellsLoading) {
        setUpsellsLoading(false);
      }

      if (blocking && activeAddForm) {
        setAddFormLoading(activeAddForm, false);
        activeAddForm = null;
      }
    }

    return null;
  };

  const open = async (shouldRefresh = false) => {
    if (!drawer) {
      debug('open skipped: missing drawer');
      return;
    }

    debug('open', { shouldRefresh });

    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('mt-side-cart-is-open');

    if (shouldRefresh && !isRequesting) {
      if (!isUpsellsLoading) {
        request('meditrendy_side_cart_get', { include_upsells: 1 }, {
          blocking: false,
          silent: true,
          upsellsLoading: true,
        });
      }
    }
  };

  const close = () => {
    if (!drawer) return;

    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('mt-side-cart-is-open');
  };

  const closestCartItem = (target) => target && target.closest ? target.closest('[data-cart-item-key]') : null;
  const closestElement = (target, selector) => target && target.closest ? target.closest(selector) : null;

  const readQuantity = (item) => {
    const input = item ? item.querySelector('[data-mt-side-cart-quantity] input') : null;
    return Math.max(0, parseInt(input ? input.value : '1', 10) || 1);
  };

  const waitForIdle = (timeout = 2500) => {
    if (!isRequesting) {
      return Promise.resolve(true);
    }

    const started = Date.now();

    return new Promise((resolve) => {
      const check = () => {
        if (!isRequesting) {
          resolve(true);
          return;
        }

        if (Date.now() - started >= timeout) {
          resolve(false);
          return;
        }

        window.setTimeout(check, 50);
      };

      check();
    });
  };

  const refreshFromSession = async (options = {}) => {
    if (!hasCartCookie() || isRequesting) {
      return null;
    }

    const now = Date.now();
    const minInterval = options.force ? 0 : 5000;

    if (now - lastSessionRefresh < minInterval) {
      return null;
    }

    lastSessionRefresh = now;

    return request(
      'meditrendy_side_cart_get',
      { include_upsells: drawer && drawer.classList.contains('is-open') ? 1 : 0 },
      { silent: true }
    );
  };

  const readCartItemQuantity = (item) => {
    if (!item) return 0;

    const input = item.querySelector('[data-mt-side-cart-quantity] input');
    const single = item.querySelector('.mt-side-cart-single-qty');
    const value = input ? input.value : single ? single.textContent : '0';

    return Math.max(0, parseInt(value, 10) || 0);
  };

  const getKnownCartQuantity = (formData) => {
    const productId = String(formData.get('product_id') || formData.get('add-to-cart') || '');
    const variationId = String(formData.get('variation_id') || '0');
    let quantity = 0;

    document.querySelectorAll('[data-mt-side-cart] [data-product-id]').forEach((item) => {
      const itemProductId = String(item.dataset.productId || '');
      const itemVariationId = String(item.dataset.variationId || '0');

      if (variationId !== '0' && itemVariationId === variationId) {
        quantity += readCartItemQuantity(item);
        return;
      }

      if (variationId === '0' && itemProductId === productId && itemVariationId === '0') {
        quantity += readCartItemQuantity(item);
      }
    });

    return quantity;
  };

  const setQuantity = async (item, quantity, options = {}) => {
    if (!item) return;

    const cartItemKey = item.dataset.cartItemKey || '';

    if (!cartItemKey) return;

    if (isRequesting) {
      if (!options.defer || !(await waitForIdle())) {
        return;
      }
    }

    await request('meditrendy_side_cart_update', {
      cart_item_key: cartItemKey,
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

    open(true);
  };

  const getAddToCartForm = (target) => {
    const form = target && target.closest ? target.closest('form.cart') : null;

    if (!form || !document.body.contains(form)) {
      return null;
    }

    return form.querySelector('[name="add-to-cart"], [name="product_id"], [name="variation_id"], [name="woosb_ids"]') ? form : null;
  };

  const canHandleAddToCartForm = (form) => {
    if (!form || isRequesting) {
      return false;
    }

    if (!form.classList.contains('variations_form')) {
      return true;
    }

    const variationInput = form.querySelector('input[name="variation_id"]');

    if (variationInput && parseInt(variationInput.value || '0', 10) > 0) {
      return true;
    }

    const requiredSelects = Array.from(form.querySelectorAll('select[name^="attribute_"]'));

    if (requiredSelects.length && requiredSelects.every((select) => !!select.value)) {
      return true;
    }

    return !form.querySelector('.single_add_to_cart_button.wc-variation-selection-needed, .single_add_to_cart_button.disabled');
  };

  const shouldHandleUpsellForm = (form) => {
    return !!(form && form.closest && form.closest('[data-mt-side-cart-upsell]'));
  };

  const getUpsellVariationBox = (form) => form ? form.querySelector('.mt-side-cart-upsell-variations') : null;

  const isUpsellChoiceForm = (form) => !!(form && form.classList.contains('mt-side-cart-upsell-form') && getUpsellVariationBox(form));

  const readUpsellVariationData = (box) => {
    if (!box) return [];

    try {
      return JSON.parse(box.getAttribute('data-product_variations') || '[]');
    } catch (error) {
      return [];
    }
  };

  const readUpsellSelectedAttributes = (box) => {
    const selected = {};
    let complete = true;

    box.querySelectorAll('select[name^="attribute_"]').forEach((select) => {
      const options = Array.from(select.options || []).filter((option) => option.value);

      if (!select.value && options.length === 1) {
        select.value = options[0].value;
      }

      selected[select.name] = select.value || '';

      if (!select.value) {
        complete = false;
      }
    });

    return { selected, complete };
  };

  const normalizeAttributeKey = (key) => String(key || '').replace(/^attribute_/, '').replace(/[_\s]+/g, '-').toLowerCase();

  const selectedAttributeValue = (selected, key) => {
    if (Object.prototype.hasOwnProperty.call(selected, key)) {
      return selected[key];
    }

    const wanted = normalizeAttributeKey(key);
    const match = Object.entries(selected).find(([selectedKey]) => normalizeAttributeKey(selectedKey) === wanted);

    return match ? match[1] : '';
  };

  const updateUpsellVariation = (form) => {
    const box = getUpsellVariationBox(form);

    if (!box) return true;

    const { selected, complete } = readUpsellSelectedAttributes(box);
    const variationInput = form.querySelector('input.variation_id');

    if (!complete) {
      if (variationInput) variationInput.value = '0';
      return false;
    }

    const purchasableVariations = readUpsellVariationData(box).filter((variation) => {
      if (!variation || !variation.is_purchasable || !variation.is_in_stock) return false;

      return true;
    });

    const match = purchasableVariations.find((variation) => {
      const attributes = variation.attributes || {};

      if (!Object.keys(attributes).length) {
        return true;
      }

      return Object.entries(attributes).every(([key, value]) => {
        return !value || selectedAttributeValue(selected, key) === value;
      });
    }) || (purchasableVariations.length === 1 ? purchasableVariations[0] : null);

    if (variationInput) {
      variationInput.value = match && match.variation_id ? String(match.variation_id) : '0';
    }

    return !!match;
  };

  const showUpsellTooltip = (button, message = 'Pasirinkite dydį') => {
    if (!button) return;

    const existing = button.parentElement ? button.parentElement.querySelector('.mt-side-cart-upsell-tooltip') : null;

    if (existing) {
      existing.remove();
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'mt-side-cart-upsell-tooltip';
    tooltip.textContent = message;
    button.insertAdjacentElement('afterend', tooltip);

    window.setTimeout(() => {
      tooltip.remove();
    }, 2200);
  };

  const isInteractiveUpsellTarget = (target) => {
    return target && target.closest && target.closest('select, input, button, a, label');
  };

  const expandUpsellTile = (tile) => {
    if (!tile) return;

    tile.classList.add('is-expanded');
    tile.querySelectorAll('[data-mt-side-cart-upsell-add]').forEach((button) => {
      if (button.dataset.mtAddLabel) {
        button.textContent = button.dataset.mtAddLabel;
      }
    });
    tile.querySelectorAll('select[name^="attribute_"]').forEach((select) => {
      select.style.display = 'block';
      select.removeAttribute('aria-hidden');
      select.removeAttribute('tabindex');
    });

    const firstSelect = tile.querySelector('select[name^="attribute_"]');

    if (firstSelect) {
      firstSelect.focus({ preventScroll: true });
    }
  };

  const allBundleSelectionsComplete = (container) => {
    const selects = container ? Array.from(container.querySelectorAll('.woosb_variations_form select[name^="attribute_"]')) : [];

    return selects.length > 0 && selects.every((select) => !!select.value);
  };

  const collapseUpsellLabels = (root) => {
    const scope = root || document;

    scope.querySelectorAll('[data-mt-side-cart-upsell]:not(.is-expanded) [data-mt-side-cart-upsell-add]').forEach((button) => {
      const form = button.closest('form');

      if (form && getUpsellVariationBox(form) && button.dataset.mtChooseLabel) {
        button.textContent = button.dataset.mtChooseLabel;
      }
    });
  };

  const submitAddToCartForm = async (form, submitter = null) => {
    const upsellTile = form.closest ? form.closest('[data-mt-side-cart-upsell]') : null;
    debug('submit add form', {
      formClass: form.className,
      buttonClass: submitter ? submitter.className : '',
      isUpsell: !!upsellTile,
    });

    if (isUpsellChoiceForm(form) && upsellTile && !upsellTile.classList.contains('is-expanded')) {
      expandUpsellTile(upsellTile);
      return;
    }

    if (isUpsellChoiceForm(form) && !updateUpsellVariation(form)) {
      expandUpsellTile(upsellTile);
      showUpsellTooltip(submitter || form.querySelector('[type="submit"]'));
      return;
    }

    if (upsellTile && upsellTile.querySelector('.woosb-wrap') && !allBundleSelectionsComplete(upsellTile)) {
      expandUpsellTile(upsellTile);
      showUpsellTooltip(submitter || form.querySelector('[type="submit"]'));
      return;
    }

    const formData = new window.FormData(form);

    if (submitter && submitter.name && !formData.has(submitter.name)) {
      formData.append(submitter.name, submitter.value || '');
    }

    const addToCart = form.querySelector('[name="add-to-cart"]');

    if (addToCart && addToCart.name && !formData.has(addToCart.name)) {
      formData.append(addToCart.name, addToCart.value || '');
    }

    formData.set('mt_side_cart_existing_quantity', getKnownCartQuantity(formData));

    const requestKey = [
      formData.get('add-to-cart') || formData.get('product_id') || '',
      formData.get('variation_id') || '',
      formData.get('quantity') || '1',
      formData.get('woosb_ids') || '',
    ].join('|');

    if (activeAddRequestKey === requestKey) {
      return;
    }

    activeAddRequestKey = requestKey;
    activeAddForm = form;
    setAddFormLoading(form, true);

    const data = await request('meditrendy_side_cart_add', formData, { trigger: submitter || form.querySelector('[type="submit"], button[name="add-to-cart"]') });

    if (data) {
      open(false);
    } else {
      debug('submit add form finished without data', {
        hasCartCookie: hasCartCookie(),
      });
    }

    activeAddRequestKey = '';
  };

  const takeOverAddToCartEvent = (event) => {
    event.preventDefault();
    event.stopPropagation();

    if (typeof event.stopImmediatePropagation === 'function') {
      event.stopImmediatePropagation();
    }
  };

  const handleAddToCartClick = (event) => {
    const button = event.target && event.target.closest
      ? event.target.closest('[data-mt-side-cart-upsell-add], .single_add_to_cart_button, button[name="add-to-cart"]')
      : null;
    const form = button ? getAddToCartForm(button) : null;

    if (button) {
      debug('add button click', {
        defaultPrevented: event.defaultPrevented,
        hasForm: !!form,
        canHandle: !!form && canHandleAddToCartForm(form),
        formClass: form ? form.className : '',
        buttonClass: button.className,
      });
    }

    if (button && form && shouldHandleUpsellForm(form)) {
      takeOverAddToCartEvent(event);
      submitAddToCartForm(form, button);
      return;
    }

    if (event.defaultPrevented || !button || !form || !canHandleAddToCartForm(form)) {
      return;
    }

    takeOverAddToCartEvent(event);
    submitAddToCartForm(form, button);
  };

  const handleAddToCartSubmit = async (event) => {
    const form = getAddToCartForm(event.target);

    if (form && shouldHandleUpsellForm(form)) {
      takeOverAddToCartEvent(event);
      await submitAddToCartForm(form, event.submitter || form.querySelector('[data-mt-side-cart-upsell-add], [type="submit"], button[name="add-to-cart"]'));
      return;
    }

    if (event.defaultPrevented || !canHandleAddToCartForm(form)) {
      return;
    }

    takeOverAddToCartEvent(event);
    await submitAddToCartForm(form, event.submitter || document.activeElement);
  };

  const handleDrawerClick = (event) => {
    if (!drawer || !drawer.contains(event.target)) return;

    if (closestElement(event.target, '[data-mt-side-cart-close]')) {
      event.preventDefault();
      close();
      return;
    }

    const remove = closestElement(event.target, '[data-mt-side-cart-remove]');

    if (remove) {
      event.preventDefault();
      setQuantity(closestCartItem(remove), 0, { defer: true });
      return;
    }

    if (isRequesting) {
      return;
    }

    const upsellAdd = closestElement(event.target, '[data-mt-side-cart-upsell-add]');

    if (upsellAdd) {
      const form = getAddToCartForm(upsellAdd);

      if (form) {
        event.preventDefault();
        submitAddToCartForm(form, upsellAdd);
      }

      return;
    }

    const quantityButton = closestElement(event.target, '[data-mt-side-cart-qty]');

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
      return;
    }

    const prev = closestElement(event.target, '[data-mt-upsell-prev]');
    const next = closestElement(event.target, '[data-mt-upsell-next]');

    if (prev || next) {
      event.preventDefault();

      const section = closestElement(event.target, '[data-mt-side-cart-upsells]');
      const track = section ? section.querySelector('[data-mt-upsell-track]') : null;

      if (!track) return;

      const tile = track.querySelector('.mt-side-cart-upsell');
      const distance = tile ? tile.getBoundingClientRect().width + 12 : 220;

      track.scrollBy({
        left: prev ? -distance : distance,
        behavior: 'smooth',
      });
      return;
    }

    const upsellTile = closestElement(event.target, '[data-mt-side-cart-upsell]');

    if (upsellTile && !isInteractiveUpsellTarget(event.target)) {
      event.preventDefault();
      expandUpsellTile(upsellTile);
    }
  };

  const handleQuantityChange = (event) => {
    const upsellSelect = event.target.closest ? event.target.closest('.mt-side-cart-upsell-form select[name^="attribute_"]') : null;

    if (upsellSelect) {
      updateUpsellVariation(upsellSelect.closest('form'));
      return;
    }

    const bundleSelect = event.target.closest ? event.target.closest('[data-mt-side-cart-upsell] .woosb_variations_form select[name^="attribute_"]') : null;

    if (bundleSelect) {
      return;
    }

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
      if (isEmittingSyntheticAddedToCart) {
        debug('skip synthetic added_to_cart refresh');
        return;
      }

      await request('meditrendy_side_cart_get', { include_upsells: 1 }, {
        blocking: false,
        silent: true,
        upsellsLoading: true,
      });
      open(false);
    });

    window.jQuery(document.body).on('removed_from_cart updated_cart_totals wc_fragments_refreshed wc_fragments_loaded', () => {
      request('meditrendy_side_cart_get', { include_upsells: drawer && drawer.classList.contains('is-open') ? 1 : 0 }, {
        blocking: false,
        silent: true,
        upsellsLoading: !!(drawer && drawer.classList.contains('is-open')),
      });
    });
  };

  const init = () => {
    drawer = document.querySelector(cartSelector);
    inner = drawer ? drawer.querySelector('[data-mt-side-cart-inner]') : null;

    debug('init', {
      hasDrawer: !!drawer,
      hasInner: !!inner,
      hasSettings: !!window.MeditrendySideCart,
      hasAjaxUrl: !!currentAjaxUrl(),
      hasNonce: !!currentNonce(),
    });

    if (!drawer || !inner) return;

    initDynamicCartContent();
    collapseUpsellLabels(inner);

    window.addEventListener('click', handleAddToCartClick, true);
    window.addEventListener('submit', handleAddToCartSubmit, true);
    document.addEventListener('click', handleTriggerClick, true);
    document.addEventListener('click', handleDrawerClick);
    document.addEventListener('change', handleQuantityChange);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
        close();
      }
    });

    bindWooEvents();

    refreshFromSession();

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
  window.addEventListener('pageshow', () => {
    refreshFromSession({ force: true });
  });
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

        if (event.target && event.target.closest && event.target.closest('[data-mt-side-cart-upsells]')) {
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
