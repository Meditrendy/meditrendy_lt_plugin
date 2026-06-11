(function () {
  'use strict';

  const settings = window.meditrendyCheckoutInvoice || {};
  const blockClass = 'meditrendy-checkout-invoice-fields';
  const phoneClass = 'meditrendy-checkout-contact-phone';
  const billingAddressLabels = ['Billing address', 'Pirkėjo adresas'];
  const pickupLabels = ['atsiėmimas', 'atsiimimas', 'pickup', 'collection'];
  const billingToggleLabels = ['naudoti tą patį adresą', 'use same address', 'same address'];
  const labels = Object.assign({
    contactPhone: 'Telefonas',
    invoiceRequired: 'Reikia sąskaitos faktūros įmonei',
    companyName: 'Įmonės pavadinimas',
    companyCode: 'PVM mokėtojo kodas',
    invoiceAddress: 'Adresas sąskaitai',
    invoiceStreet: 'Gatvė, namo numeris',
    invoiceCity: 'Miestas',
    invoicePostcode: 'Pašto kodas'
  }, settings.labels || {});

  let saveTimer = null;
  let addressSyncTimer = null;
  let lastPayload = '';
  let saving = null;
  let lastBillingSignature = '';
  let lastShippingSignature = '';
  let lastComputedBillingAddress = null;

  function createTextInput(id, label, value, autocomplete, inputMode) {
    const wrap = document.createElement('div');
    wrap.className = 'meditrendy-checkout-invoice-fields__field';

    const input = document.createElement('input');
    input.type = inputMode === 'tel' ? 'tel' : 'text';
    input.id = id;
    input.name = id;
    input.value = value || '';
    input.autocomplete = autocomplete || 'off';

    if (inputMode) {
      input.inputMode = inputMode;
    }

    const labelElement = document.createElement('label');
    labelElement.htmlFor = id;
    labelElement.textContent = label;

    wrap.append(labelElement, input);

    return wrap;
  }

  function createContactPhoneField() {
    const field = createTextInput('meditrendy_contact_phone', labels.contactPhone, settings.contactPhone, 'tel', 'tel');
    field.classList.add(phoneClass);
    return field;
  }

  function createInvoiceBlock() {
    const block = document.createElement('div');
    block.className = blockClass;

    const checkboxWrap = document.createElement('label');
    checkboxWrap.className = 'wc-block-components-checkbox meditrendy-checkout-invoice-fields__toggle';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'meditrendy_invoice_required';
    checkbox.name = 'meditrendy_invoice_required';
    checkbox.checked = !!settings.invoiceRequired;

    const checkboxText = document.createElement('span');
    checkboxText.textContent = labels.invoiceRequired;

    checkboxWrap.append(checkbox, checkboxText);

    const fields = document.createElement('div');
    fields.className = 'meditrendy-checkout-invoice-fields__details';

    const cityRow = document.createElement('div');
    cityRow.className = 'meditrendy-checkout-invoice-fields__row';
    cityRow.append(
      createTextInput('meditrendy_invoice_city', labels.invoiceCity, settings.invoiceCity, 'address-level2'),
      createTextInput('meditrendy_invoice_postcode', labels.invoicePostcode, settings.invoicePostcode, 'postal-code')
    );

    fields.append(
      createTextInput('meditrendy_company_name', labels.companyName, settings.companyName, 'organization'),
      createTextInput('meditrendy_company_code', labels.companyCode, settings.companyCode, 'off'),
      createTextInput('meditrendy_invoice_street', labels.invoiceStreet, settings.invoiceStreet, 'address-line1'),
      cityRow
    );

    block.append(checkboxWrap, fields);

    return block;
  }

  function findCheckoutEndTarget() {
    const form = document.querySelector('.wc-block-checkout__form, form.wc-block-checkout__form');

    if (!form) {
      return null;
    }

    return {
      container: form,
      before: form.querySelector('.wc-block-checkout__actions')
    };
  }

  function findContactTarget() {
    let contact = document.querySelector('.wc-block-checkout__contact-fields');

    if (!contact) {
      const emailCandidate = document.querySelector('#email, input[type="email"], input[autocomplete="email"]');
      contact = emailCandidate ? emailCandidate.closest('.wc-block-components-checkout-step--with-step-number, fieldset, .wc-block-checkout__form') : null;
    }

    if (!contact) {
      return null;
    }

    const email = contact.querySelector('#email, input[type="email"], input[autocomplete="email"]');
    const emailWrap = email ? findFieldWrapper(email) : null;

    return {
      container: emailWrap && emailWrap.parentElement ? emailWrap.parentElement : contact,
      after: emailWrap || email
    };
  }

  function findFieldWrapper(element) {
    if (!element) {
      return null;
    }

    return element.closest('.wc-block-components-text-input, .wc-block-components-address-form__state, .wc-block-components-state-input, .wc-block-components-country-input, .wc-block-components-select-input, .components-base-control, .wc-block-components-address-autocomplete-container') || element.parentElement;
  }

  function syncInvoiceBlockVisibility(block) {
    const checkbox = block.querySelector('#meditrendy_invoice_required');
    const details = block.querySelector('.meditrendy-checkout-invoice-fields__details');

    if (!checkbox || !details) {
      return;
    }

    details.hidden = !checkbox.checked;
  }

  function getPhoneField() {
    return document.querySelector('#meditrendy_contact_phone');
  }

  function getPayload(block) {
    const checkbox = block.querySelector('#meditrendy_invoice_required');
    const companyName = block.querySelector('#meditrendy_company_name');
    const companyCode = block.querySelector('#meditrendy_company_code');
    const invoiceStreet = block.querySelector('#meditrendy_invoice_street');
    const invoiceCity = block.querySelector('#meditrendy_invoice_city');
    const invoicePostcode = block.querySelector('#meditrendy_invoice_postcode');
    const contactPhone = getPhoneField();
    const payload = new URLSearchParams();

    payload.set('action', 'meditrendy_save_checkout_invoice_fields');
    payload.set('nonce', settings.nonce || '');
    payload.set('invoice_required', checkbox && checkbox.checked ? '1' : '');
    payload.set('contact_phone', contactPhone ? contactPhone.value : '');
    payload.set('company_name', companyName ? companyName.value : '');
    payload.set('company_code', companyCode ? companyCode.value : '');
    payload.set('invoice_street', invoiceStreet ? invoiceStreet.value : '');
    payload.set('invoice_city', invoiceCity ? invoiceCity.value : '');
    payload.set('invoice_postcode', invoicePostcode ? invoicePostcode.value : '');

    return payload;
  }

  function getInputValue(selectors) {
    const inputs = Array.from(document.querySelectorAll(selectors));
    const input = inputs.find(function (field) {
      return ((field.autocomplete || '') + ' ' + (field.name || '') + ' ' + (field.id || '')).indexOf('shipping') !== -1 && field.offsetParent !== null && field.closest('[hidden]') === null;
    }) || inputs.find(function (field) {
      const meta = ((field.autocomplete || '') + ' ' + (field.name || '') + ' ' + (field.id || '')).toLowerCase();
      return meta.indexOf('billing') === -1 && field.offsetParent !== null && field.closest('[hidden]') === null;
    }) || inputs.find(function (field) {
      return field.offsetParent !== null && field.closest('[hidden]') === null;
    }) || inputs.find(function (field) {
      const meta = ((field.autocomplete || '') + ' ' + (field.name || '') + ' ' + (field.id || '')).toLowerCase();
      return meta.indexOf('billing') === -1;
    }) || inputs[0];

    return input ? input.value || '' : '';
  }

  function getWooStore(store) {
    if (!window.wp || !window.wp.data || !window.wc || !window.wc.wcBlocksData || !window.wc.wcBlocksData[store]) {
      return null;
    }

    return {
      select: window.wp.data.select(window.wc.wcBlocksData[store]),
      dispatch: window.wp.data.dispatch(window.wc.wcBlocksData[store])
    };
  }

  function getVisibleShippingAddress() {
    return {
      first_name: getInputValue('input[autocomplete*="given-name"], input[name*="first_name"]'),
      last_name: getInputValue('input[autocomplete*="family-name"], input[name*="last_name"]'),
      address_1: getInputValue('input[autocomplete*="address-line1"]'),
      address_2: getInputValue('input[autocomplete*="address-line2"]'),
      city: getInputValue('input[autocomplete*="address-level2"]'),
      postcode: getInputValue('input[autocomplete*="postal-code"]'),
      country: getInputValue('select[autocomplete*="country"], input[autocomplete*="country"]'),
      state: ''
    };
  }

  function getInvoiceAddress() {
    const block = document.querySelector(`.${blockClass}`);
    const checkbox = block ? block.querySelector('#meditrendy_invoice_required') : null;

    return {
      invoiceRequired: !!(checkbox && checkbox.checked),
      company: block && block.querySelector('#meditrendy_company_name') ? block.querySelector('#meditrendy_company_name').value : '',
      address_1: block && block.querySelector('#meditrendy_invoice_street') ? block.querySelector('#meditrendy_invoice_street').value : '',
      city: block && block.querySelector('#meditrendy_invoice_city') ? block.querySelector('#meditrendy_invoice_city').value : '',
      postcode: block && block.querySelector('#meditrendy_invoice_postcode') ? block.querySelector('#meditrendy_invoice_postcode').value : ''
    };
  }

  function compactAddress(address) {
    return Object.assign({
      first_name: '',
      last_name: '',
      company: '',
      address_1: '',
      address_2: '',
      city: '',
      state: '',
      postcode: '',
      country: '',
      phone: '',
      email: ''
    }, address || {});
  }

  function syncWooCheckoutAddresses(reason) {
    const store = getWooStore('cartStore');

    if (!store || !store.select || !store.dispatch || typeof store.select.getCartData !== 'function') {
      return;
    }

    const cartData = store.select.getCartData() || {};
    const currentShipping = compactAddress(cartData.shippingAddress || {});
    const currentBilling = compactAddress(cartData.billingAddress || {});
    const visibleShipping = getVisibleShippingAddress();
    const invoice = getInvoiceAddress();
    const contactPhone = getPhoneField() ? getPhoneField().value : '';
    const email = getInputValue('#email, input[type="email"], input[autocomplete="email"]') || currentBilling.email || '';
    const mergedShipping = compactAddress(Object.assign({}, currentShipping, {
      phone: contactPhone || currentShipping.phone
    }));
    const baseBilling = compactAddress(Object.assign({}, currentBilling, currentShipping, visibleShipping, {
      first_name: visibleShipping.first_name || currentShipping.first_name || currentBilling.first_name,
      last_name: visibleShipping.last_name || currentShipping.last_name || currentBilling.last_name,
      country: visibleShipping.country || currentShipping.country || currentBilling.country || 'LT',
      phone: contactPhone || currentShipping.phone || currentBilling.phone,
      email: email
    }));
    const nextBilling = invoice.invoiceRequired ? compactAddress(Object.assign({}, baseBilling, {
      company: invoice.company,
      address_1: invoice.address_1,
      address_2: '',
      city: invoice.city,
      postcode: invoice.postcode
    })) : compactAddress(Object.assign({}, baseBilling, {
      company: ''
    }));
    const billingSignature = JSON.stringify(nextBilling);
    const shippingSignature = JSON.stringify(mergedShipping);

    lastComputedBillingAddress = nextBilling;

    if (typeof store.dispatch.setBillingAddress === 'function' && billingSignature !== lastBillingSignature) {
      lastBillingSignature = billingSignature;
      store.dispatch.setBillingAddress(nextBilling);
    }

    if (typeof store.dispatch.setShippingAddress === 'function' && shippingSignature !== lastShippingSignature) {
      lastShippingSignature = shippingSignature;
      store.dispatch.setShippingAddress(mergedShipping);
    }
  }

  function syncHiddenBillingNativeFields(reason) {
    const store = getWooStore('cartStore');
    const cartData = store && store.select && typeof store.select.getCartData === 'function' ? store.select.getCartData() || {} : {};
    const billing = compactAddress(lastComputedBillingAddress || cartData.billingAddress || cartData.shippingAddress || {});
    const values = {
      billing_first_name: billing.first_name,
      billing_last_name: billing.last_name,
      billing_company: billing.company,
      billing_address_1: billing.address_1,
      billing_address_2: billing.address_2,
      billing_city: billing.city,
      billing_state: billing.state,
      billing_postcode: billing.postcode,
      billing_country: billing.country || 'LT',
      billing_phone: billing.phone,
      billing_email: billing.email
    };

    document.querySelectorAll('.wc-block-checkout__billing-fields input, .wc-block-checkout__billing-fields select, .wc-block-checkout__billing-fields textarea, input[name^="billing_"], select[name^="billing_"], textarea[name^="billing_"]').forEach(function (field) {
      if (field.type === 'checkbox' || field.type === 'radio' || field.type === 'hidden') {
        return;
      }

      const hidden = field.hidden || field.closest('[hidden]') !== null || field.offsetParent === null;
      const value = Object.prototype.hasOwnProperty.call(values, field.name) ? values[field.name] : '';

      if (value && field.value !== value) {
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }

      if (hidden) {
        if (field.required) {
          field.dataset.meditrendyWasRequired = '1';
        }

        field.required = false;
        field.setAttribute('aria-required', 'false');
        field.disabled = true;
      } else if (field.dataset.meditrendyWasRequired === '1') {
        field.required = true;
        field.removeAttribute('aria-required');
        field.disabled = false;
      }
    });
  }

  function saveInvoiceFields(block, immediate) {
    if (!settings.ajaxUrl || !settings.nonce || !block) {
      return Promise.resolve();
    }

    window.clearTimeout(saveTimer);

    const run = function () {
      const payload = getPayload(block);
      const serialized = payload.toString();

      if (serialized === lastPayload) {
        return Promise.resolve();
      }

      lastPayload = serialized;
      saving = window.fetch(settings.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: serialized
      }).catch(function () {
        lastPayload = '';
      });

      return saving;
    };

    if (immediate) {
      return run();
    }

    saveTimer = window.setTimeout(run, 250);
    return Promise.resolve();
  }

  function bindInvoiceBlock(block) {
    if (block.dataset.meditrendyInvoiceReady === '1') {
      return;
    }

    block.dataset.meditrendyInvoiceReady = '1';
    syncInvoiceBlockVisibility(block);
    saveInvoiceFields(block, true);

    block.addEventListener('change', function () {
      syncInvoiceBlockVisibility(block);
      syncWooCheckoutAddresses('invoice change');
      saveInvoiceFields(block, true);
    });

    block.addEventListener('input', function () {
      syncWooCheckoutAddresses('invoice input');
      saveInvoiceFields(block, false);
    });

    document.addEventListener('click', function (event) {
      const submitButton = event.target.closest('.wc-block-components-checkout-place-order-button, button[type="submit"]');

      if (!submitButton) {
        return;
      }

      syncWooCheckoutAddresses('place order click');
      syncHiddenBillingNativeFields('place order click');
      saveInvoiceFields(block, true);
    }, true);
  }

  function bindContactPhone(field) {
    if (field.dataset.meditrendyPhoneReady === '1') {
      return;
    }

    field.dataset.meditrendyPhoneReady = '1';
    field.addEventListener('input', function () {
      const block = document.querySelector(`.${blockClass}`);
      syncWooCheckoutAddresses('phone input');
      saveInvoiceFields(block, false);
    });

    field.addEventListener('change', function () {
      const block = document.querySelector(`.${blockClass}`);
      syncWooCheckoutAddresses('phone change');
      saveInvoiceFields(block, true);
    });
  }

  function ensureContactPhoneField() {
    const target = findContactTarget();

    if (!target) {
      return;
    }

    if (!target.after) {
      return;
    }

    let field = document.querySelector(`.${phoneClass}`);

    if (!field) {
      field = createContactPhoneField();
    }

    if (field.parentElement !== target.container || field.previousElementSibling !== target.after) {
      target.after.insertAdjacentElement('afterend', field);
    }

    bindContactPhone(field);
  }

  function ensureInvoiceBlock() {
    const target = findCheckoutEndTarget();

    if (!target) {
      return;
    }

    let block = document.querySelector(`.${blockClass}`);

    if (!block) {
      block = createInvoiceBlock();
    }

    if (block.parentElement !== target.container || (target.before && block.nextElementSibling !== target.before)) {
      target.container.insertBefore(block, target.before || null);
    }

    bindInvoiceBlock(block);
  }

  function syncBillingAddressLabel() {
    document.querySelectorAll('.wc-block-checkout__billing-fields h2, .wc-block-checkout__billing-fields legend, .wc-block-checkout__billing-fields .wc-block-components-title').forEach(function (element) {
      const label = element.textContent.trim();

      if (billingAddressLabels.indexOf(label) !== -1) {
        element.textContent = labels.invoiceAddress;
      }
    });
  }

  function isPickupSelected() {
    const checkedInputs = Array.from(document.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked'));

    return checkedInputs.some(function (input) {
      const text = [
        input.value,
        input.id,
        input.name,
        input.closest('label') ? input.closest('label').textContent : '',
        input.closest('.wc-block-components-radio-control__option') ? input.closest('.wc-block-components-radio-control__option').textContent : ''
      ].join(' ').toLowerCase();

      return pickupLabels.some(function (label) {
        return text.indexOf(label) !== -1;
      });
    });
  }

  function forceCheckboxChecked(checkbox) {
    if (!checkbox || checkbox.checked) {
      return false;
    }

    checkbox.click();

    if (!checkbox.checked) {
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event('input', { bubbles: true }));
      checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }

    return true;
  }

  function hideNativeAddressFields() {
    const pickup = isPickupSelected();
    document.documentElement.classList.toggle('meditrendy-checkout-pickup-selected', pickup);

    document.querySelectorAll('label, .wc-block-components-checkbox, .wc-block-components-address-card, .wc-block-checkout__use-address-for-billing').forEach(function (element) {
      const text = (element.textContent || '').trim().toLowerCase();

      if (!text) {
        return;
      }

      if (billingToggleLabels.some(function (label) { return text.indexOf(label) !== -1; })) {
        const checkbox = element.matches('input[type="checkbox"]') ? element : element.querySelector('input[type="checkbox"]');

        forceCheckboxChecked(checkbox);

        const wrapper = element.closest('.wc-block-components-checkbox, .wc-block-checkout__use-address-for-billing, .wc-block-components-address-card, .wc-block-components-address-address-wrapper') || element;
        wrapper.hidden = true;
      }
    });

    document.querySelectorAll('.wc-block-checkout__use-address-for-billing input[type="checkbox"]').forEach(function (checkbox) {
      forceCheckboxChecked(checkbox);
    });

    document.querySelectorAll('input[autocomplete*=" tel"], input[autocomplete="tel"], input[name*="_phone"]').forEach(function (input) {
      const wrapper = findFieldWrapper(input);
      if (wrapper && !wrapper.classList.contains(phoneClass)) {
        wrapper.hidden = true;
      }
    });

    document.querySelectorAll('input[autocomplete*="address-level1"], select[autocomplete*="address-level1"], input[name*="_state"], select[name*="_state"]').forEach(function (input) {
      const wrapper = findFieldWrapper(input);
      if (wrapper) {
        wrapper.hidden = true;
      }
    });

    document.querySelectorAll('.wc-block-checkout__billing-fields').forEach(function (section) {
      section.hidden = true;
    });

    document.querySelectorAll('.wc-block-components-address-form').forEach(function (form) {
      const isAddressForm = form.querySelector('[autocomplete*="address-line1"], [autocomplete*="postal-code"], [autocomplete*="address-level2"]');

      if (!isAddressForm) {
        return;
      }

      form.querySelectorAll('[autocomplete*="address-line1"], [autocomplete*="address-line2"], [autocomplete*="postal-code"], [autocomplete*="address-level2"], [autocomplete*="country"]').forEach(function (input) {
        const wrapper = findFieldWrapper(input);

        if (wrapper) {
          wrapper.hidden = pickup;
        }
      });
    });
  }

  function syncCheckoutInvoiceUi() {
    ensureContactPhoneField();
    ensureInvoiceBlock();
    syncBillingAddressLabel();
    hideNativeAddressFields();
    syncWooCheckoutAddresses('ui sync');
    syncHiddenBillingNativeFields('ui sync');
  }

  const observer = new MutationObserver(syncCheckoutInvoiceUi);

  function init() {
    syncCheckoutInvoiceUi();

    document.addEventListener('change', function (event) {
      if (event.target && event.target.matches('input, select')) {
        syncWooCheckoutAddresses('field change');
        syncHiddenBillingNativeFields('field change');
        window.setTimeout(syncCheckoutInvoiceUi, 50);
      }
    }, true);

    document.addEventListener('input', function (event) {
      if (event.target && event.target.matches('input, select, textarea')) {
        window.clearTimeout(addressSyncTimer);
        addressSyncTimer = window.setTimeout(function () {
          syncWooCheckoutAddresses('document input');
          syncHiddenBillingNativeFields('document input');
        }, 50);
      }
    }, true);

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
