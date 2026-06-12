(function () {
  'use strict';

  const settings = window.meditrendyCheckoutCodFee || {};

  let lastPaymentMethod = null;
  let saveTimer = null;

  function getCheckedPaymentInput() {
    return (
      document.querySelector('input[name="payment_method"]:checked') ||
      document.querySelector('.wc-block-checkout__payment-method input[type="radio"]:checked') ||
      Array.from(document.querySelectorAll('input[type="radio"]:checked')).find(function (input) {
        const text = [
          input.name || '',
          input.id || '',
          input.value || '',
          input.closest('label') ? input.closest('label').textContent : '',
          input.closest('.wc-block-components-radio-control__option')
            ? input.closest('.wc-block-components-radio-control__option').textContent
            : ''
        ].join(' ').toLowerCase();

        return text.indexOf('payment') !== -1 ||
          text.indexOf('mokėj') !== -1 ||
          text.indexOf('mokej') !== -1 ||
          text.indexOf('cash on delivery') !== -1 ||
          text.indexOf('pristatymo metu') !== -1 ||
          text.indexOf('cod') !== -1;
      }) ||
      null
    );
  }

  function isCodInput(input) {
    if (!input) {
      return false;
    }

    const text = [
      input.name || '',
      input.id || '',
      input.value || '',
      input.closest('label') ? input.closest('label').textContent : '',
      input.closest('.wc-block-components-radio-control__option')
        ? input.closest('.wc-block-components-radio-control__option').textContent
        : ''
    ].join(' ').toLowerCase();

    return text.indexOf('cod') !== -1 ||
      text.indexOf('cash on delivery') !== -1 ||
      text.indexOf('apmokėjimas pristatymo metu') !== -1 ||
      text.indexOf('apmokejimas pristatymo metu') !== -1 ||
      text.indexOf('pristatymo metu') !== -1;
  }

  function getSelectedPaymentMethod() {
    const input = getCheckedPaymentInput();

    return isCodInput(input) ? 'cod' : '';
  }

  function refreshClassicCheckout() {
    if (window.jQuery) {
      window.jQuery(document.body).trigger('update_checkout');
    }
  }

  function refreshBlocksCheckout() {
    if (!window.wp || !window.wp.data) {
      return;
    }

    const stores = [];

    if (window.wc && window.wc.wcBlocksData) {
      if (window.wc.wcBlocksData.cartStore) {
        stores.push(window.wc.wcBlocksData.cartStore);
      }

      if (window.wc.wcBlocksData.checkoutStore) {
        stores.push(window.wc.wcBlocksData.checkoutStore);
      }
    }

    stores.push('wc/store/cart');
    stores.push('wc/store/checkout');

    stores.forEach(function (store) {
      try {
        const dispatch = window.wp.data.dispatch(store);

        if (!dispatch) {
          return;
        }

        if (typeof dispatch.invalidateResolutionForStore === 'function') {
          dispatch.invalidateResolutionForStore();
        }

        if (typeof dispatch.invalidateResolution === 'function') {
          ['getCartData', 'getCartTotals', 'getCartItems'].forEach(function (selector) {
            dispatch.invalidateResolution(selector, []);
          });
        }
      } catch (error) {
        // Silent fallback. Classic refresh may still handle it.
      }
    });
  }

  function refreshCheckout() {
    refreshClassicCheckout();
    refreshBlocksCheckout();
  }

  function savePaymentMethodViaBlocks(paymentMethod) {
    if (
      !window.wc ||
      !window.wc.blocksCheckout ||
      typeof window.wc.blocksCheckout.extensionCartUpdate !== 'function'
    ) {
      return null;
    }

    return window.wc.blocksCheckout.extensionCartUpdate({
      namespace: 'meditrendy-cod-fee',
      data: {
        payment_method: paymentMethod
      }
    });
  }

  function savePaymentMethodViaAjax(paymentMethod) {
    if (!settings.ajaxUrl || !settings.nonce) {
      return Promise.resolve();
    }

    const payload = new URLSearchParams();
    payload.set('action', 'meditrendy_save_cod_payment_method');
    payload.set('nonce', settings.nonce);
    payload.set('payment_method', paymentMethod);

    return window.fetch(settings.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: payload.toString()
    }).then(function () {
      refreshCheckout();
    });
  }

  function savePaymentMethod(immediate) {
    window.clearTimeout(saveTimer);

    const run = function () {
      const paymentMethod = getSelectedPaymentMethod();

      if (paymentMethod === lastPaymentMethod) {
        return;
      }

      lastPaymentMethod = paymentMethod;

      const blocksUpdate = savePaymentMethodViaBlocks(paymentMethod);

      if (blocksUpdate && typeof blocksUpdate.catch === 'function') {
        blocksUpdate.catch(function () {
          return savePaymentMethodViaAjax(paymentMethod);
        });
        return;
      }

      savePaymentMethodViaAjax(paymentMethod);
    };

    if (immediate) {
      run();
      return;
    }

    saveTimer = window.setTimeout(run, 150);
  }

  function init() {
    savePaymentMethod(true);

    document.addEventListener('change', function (event) {
      if (!event.target || !event.target.matches('input, select')) {
        return;
      }

      savePaymentMethod(false);
    }, true);

    const observer = new MutationObserver(function () {
      savePaymentMethod(false);
    });

    if (document.body) {
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
