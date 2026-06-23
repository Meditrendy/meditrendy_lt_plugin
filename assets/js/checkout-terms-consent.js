(function () {
  'use strict';

  const settings = window.meditrendyCheckoutTermsConsent || {};
  const fieldId = 'meditrendy_terms_accepted';
  const blockClass = 'meditrendy-checkout-terms-consent';
  let fetchPatched = false;

  function createConsentBlock() {
    const block = document.createElement('div');
    block.className = blockClass;

    const label = document.createElement('label');
    label.className = `${blockClass}__label`;

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = fieldId;
    checkbox.name = fieldId;
    checkbox.required = true;
    checkbox.value = '1';
    checkbox.setAttribute('aria-required', 'true');

    const text = document.createElement('span');
    text.className = `${blockClass}__text`;
    text.append(document.createTextNode(`${settings.prefix || 'Sutinku su'} `));
    text.append(createLink(settings.termsUrl || '/taisykles/', settings.terms || 'Taisyklėmis ir sąlygomis'));
    text.append(document.createTextNode(` ${settings.joiner || 'bei'} `));
    text.append(createLink(settings.privacyUrl || '/privacy-policy/', settings.privacy || 'Privatumo politika'));

    label.append(checkbox, text);

    const error = document.createElement('div');
    error.className = `${blockClass}__error`;
    error.hidden = true;
    error.textContent = settings.required || 'Prieš pateikdami užsakymą turite sutikti su taisyklėmis ir privatumo politika.';

    block.append(label, error);

    checkbox.addEventListener('change', function () {
      setError(block, false);
    });

    return block;
  }

  function createLink(url, label) {
    const link = document.createElement('a');
    link.href = url;
    link.textContent = label;
    link.target = '_blank';
    link.rel = 'noopener';

    return link;
  }

  function setError(block, visible) {
    const error = block ? block.querySelector(`.${blockClass}__error`) : null;
    const checkbox = block ? block.querySelector(`#${fieldId}`) : null;

    if (error) {
      error.hidden = !visible;
    }

    if (checkbox) {
      checkbox.setAttribute('aria-invalid', visible ? 'true' : 'false');
    }
  }

  function isAccepted() {
    const checkbox = document.getElementById(fieldId);

    return !!(checkbox && checkbox.checked);
  }

  function hideNativeTermsCopy(form) {
    if (!form) {
      return null;
    }

    const nativeTerms = form.querySelector('.wp-block-woocommerce-checkout-terms-block, .wc-block-checkout__terms, .woocommerce-terms-and-conditions-wrapper');

    if (nativeTerms) {
      nativeTerms.classList.add('meditrendy-checkout-terms-consent__native-copy');
    }

    return nativeTerms;
  }

  function ensureConsentBlock() {
    const form = document.querySelector('.wc-block-checkout__form, form.wc-block-checkout__form, form.checkout');
    const existing = document.querySelector(`.${blockClass}`);

    if (existing) {
      hideNativeTermsCopy(form);
      return existing;
    }

    if (!form) {
      return null;
    }

    const block = createConsentBlock();
    const nativeTerms = hideNativeTermsCopy(form);
    const actions = form.querySelector('.wc-block-checkout__actions, #payment, .place-order');
    const before = nativeTerms || actions;

    if (before && before.parentElement) {
      before.parentElement.insertBefore(block, before);
    } else {
      form.append(block);
    }

    return block;
  }

  function validateConsent() {
    const block = ensureConsentBlock();

    if (!block || isAccepted()) {
      return true;
    }

    setError(block, true);

    const checkbox = block.querySelector(`#${fieldId}`);

    if (checkbox) {
      checkbox.focus({ preventScroll: true });
      block.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    return false;
  }

  function bindSubmitValidation() {
    document.addEventListener('click', function (event) {
      const button = event.target.closest('.wc-block-components-checkout-place-order-button, #place_order, button[type="submit"]');

      if (!button || !button.closest('.wc-block-checkout__form, form.wc-block-checkout__form, form.checkout')) {
        return;
      }

      if (!validateConsent()) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);

    document.addEventListener('submit', function (event) {
      if (!event.target.matches('.wc-block-checkout__form, form.wc-block-checkout__form, form.checkout')) {
        return;
      }

      if (!validateConsent()) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);
  }

  function appendConsentToCheckoutRequest(body) {
    let data;

    try {
      data = JSON.parse(body || '{}');
    } catch (error) {
      return body;
    }

    if (!Array.isArray(data.payment_data)) {
      data.payment_data = [];
    }

    data.payment_data = data.payment_data.filter(function (entry) {
      return !entry || entry.key !== 'meditrendy_terms_accepted';
    });

    data.payment_data.push({
      key: 'meditrendy_terms_accepted',
      value: isAccepted() ? '1' : ''
    });

    return JSON.stringify(data);
  }

  function patchCheckoutFetch() {
    if (fetchPatched || typeof window.fetch !== 'function') {
      return;
    }

    const originalFetch = window.fetch;
    fetchPatched = true;

    window.fetch = function (resource, init) {
      const url = typeof resource === 'string' ? resource : resource && resource.url ? resource.url : '';
      const method = init && init.method ? String(init.method).toUpperCase() : '';

      if (url.indexOf('/wc/store/') !== -1 && url.indexOf('/checkout') !== -1 && method === 'POST' && init && typeof init.body === 'string') {
        init = Object.assign({}, init, {
          body: appendConsentToCheckoutRequest(init.body)
        });
      }

      return originalFetch.call(this, resource, init);
    };
  }

  function init() {
    ensureConsentBlock();
    bindSubmitValidation();
    patchCheckoutFetch();

    const observer = new MutationObserver(function () {
      ensureConsentBlock();
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
})();
