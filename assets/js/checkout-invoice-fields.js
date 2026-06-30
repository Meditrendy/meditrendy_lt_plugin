(function () {
  'use strict';

  const settings = window.meditrendyCheckoutInvoice || {};
  const blockClass = 'meditrendy-checkout-invoice-fields';
  const phoneClass = 'meditrendy-checkout-contact-phone';
  const nameClass = 'meditrendy-checkout-contact-name';
  const billingAddressLabels = ['Billing address', 'Pirkėjo adresas'];
  const pickupLabels = ['atsiėmimas', 'atsiimimas', 'pickup', 'collection'];
  const billingToggleLabels = ['naudoti tą patį adresą', 'use same address', 'same address'];
  const labels = Object.assign({
    contactPhone: 'Telefonas',
    firstName: 'Vardas',
    lastName: 'Pavardė',
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
    const input = field.querySelector('input');

    if (input) {
      input.required = false;
      input.setAttribute('aria-required', 'true');
    }

    const error = document.createElement('div');
    error.className = 'meditrendy-checkout-invoice-fields__error';
    error.hidden = true;
    field.append(error);

    field.classList.add(phoneClass);
    return field;
  }

  function createContactNameFields() {
    const fields = document.createElement('div');
    fields.className = `${nameClass} meditrendy-checkout-invoice-fields__row`;
    fields.append(
      createTextInput('meditrendy_contact_first_name', labels.firstName, '', 'given-name'),
      createTextInput('meditrendy_contact_last_name', labels.lastName, '', 'family-name')
    );

    return fields;
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

  function setPhoneError(message) {
    const field = getPhoneField();
    const wrap = field ? field.closest(`.${phoneClass}`) : null;
    const error = wrap ? wrap.querySelector('.meditrendy-checkout-invoice-fields__error') : null;

    if (!field || !error) {
      return;
    }

    error.textContent = message || '';
    error.hidden = !message;
    field.setAttribute('aria-invalid', message ? 'true' : 'false');
  }

  function validateContactPhone() {
    const field = getPhoneField();

    if (!field) {
      return true;
    }

    const value = (field.value || '').trim();
    let message = '';

    if (!value) {
      message = labels.phoneRequired || 'Įveskite telefono numerį.';
    }

    setPhoneError(message);

    if (message) {
      field.focus({ preventScroll: true });
      field.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return false;
    }

    return true;
  }

  function getContactNameValues() {
    const firstName = document.querySelector('#meditrendy_contact_first_name');
    const lastName = document.querySelector('#meditrendy_contact_last_name');

    return {
      first_name: firstName && firstName.offsetParent !== null ? firstName.value || '' : '',
      last_name: lastName && lastName.offsetParent !== null ? lastName.value || '' : ''
    };
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

  function isVisibleCustomerField(field) {
    return !!field &&
      field.offsetParent !== null &&
      field.closest('[hidden]') === null &&
      field.closest(`.${blockClass}`) === null &&
      field.type !== 'hidden';
  }

  function getFieldLabelText(field) {
    const label = field.id ? document.querySelector(`label[for="${field.id}"]`) : null;
    const wrapper = findFieldWrapper(field);

    return [
      label ? label.textContent : '',
      wrapper ? wrapper.textContent : '',
      field.placeholder || '',
      field.getAttribute('aria-label') || '',
      field.autocomplete || '',
      field.name || '',
      field.id || ''
    ].join(' ').trim().replace(/\s+/g, ' ').toLowerCase();
  }

  function getInputValueByLabel(labelParts) {
    const inputs = Array.from(document.querySelectorAll('input, select, textarea'));
    const field = inputs.find(function (input) {
      if (!isVisibleCustomerField(input)) {
        return false;
      }

      const text = getFieldLabelText(input);
      return labelParts.some(function (labelPart) {
        return text.indexOf(labelPart) !== -1;
      });
    });

    return field ? field.value || '' : '';
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
    const contactName = getContactNameValues();
    const firstName = contactName.first_name || getInputValue('input[autocomplete*="given-name"], input[name*="first_name"], input[id*="first_name"]') || getInputValueByLabel(['vardas', 'first name', 'given name']);
    const lastName = contactName.last_name || getInputValue('input[autocomplete*="family-name"], input[name*="last_name"], input[id*="last_name"]') || getInputValueByLabel(['pavardė', 'pavarde', 'last name', 'surname', 'family name']);

    return {
      first_name: firstName,
      last_name: lastName,
      address_1: getInputValue('input[autocomplete*="address-line1"]'),
      address_2: getInputValue('input[autocomplete*="address-line2"]'),
      city: getInputValue('input[autocomplete*="address-level2"]'),
      postcode: getInputValue('input[autocomplete*="postal-code"]'),
      country: getInputValue('select[autocomplete*="country"], input[autocomplete*="country"]'),
      state: ''
    };
  }

  function getScopedInputValue(container, selectors) {
    if (!container) {
      return '';
    }

    const input = Array.from(container.querySelectorAll(selectors)).find(isVisibleCustomerField);
    return input ? input.value || '' : '';
  }

  function getVisibleBillingAddress() {
    const section = document.querySelector('.wc-block-checkout__billing-fields');

    return {
      first_name: getScopedInputValue(section, 'input[autocomplete*="given-name"], input[name*="first_name"], input[id*="first_name"]'),
      last_name: getScopedInputValue(section, 'input[autocomplete*="family-name"], input[name*="last_name"], input[id*="last_name"]'),
      company: getScopedInputValue(section, 'input[autocomplete*="organization"], input[name*="company"], input[id*="company"]'),
      address_1: getScopedInputValue(section, 'input[autocomplete*="address-line1"]'),
      address_2: getScopedInputValue(section, 'input[autocomplete*="address-line2"]'),
      city: getScopedInputValue(section, 'input[autocomplete*="address-level2"]'),
      postcode: getScopedInputValue(section, 'input[autocomplete*="postal-code"]'),
      country: getScopedInputValue(section, 'select[autocomplete*="country"], input[autocomplete*="country"]'),
      state: getScopedInputValue(section, 'input[autocomplete*="address-level1"], select[autocomplete*="address-level1"], input[name*="_state"], select[name*="_state"]')
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

  function getPickupAddressFallback() {
    return compactAddress(Object.assign({
      address_1: 'Verkių g. 42, D81',
      city: 'Vilnius',
      postcode: 'LT-09117',
      country: 'LT'
    }, settings.pickupAddress || {}));
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
    const pickup = isPickupSelected();
    const contactPhone = getPhoneField() ? getPhoneField().value : '';
    const email = getInputValue('#email, input[type="email"], input[autocomplete="email"]') || currentBilling.email || '';
    const mergedShipping = compactAddress(Object.assign(
      {},
      currentShipping,
      visibleShipping,
      {
        first_name: visibleShipping.first_name || currentShipping.first_name,
        last_name: visibleShipping.last_name || currentShipping.last_name,
        country: visibleShipping.country || currentShipping.country || 'LT',
        phone: contactPhone || currentShipping.phone
      }
    ));
    const baseBilling = pickup ? compactAddress(Object.assign({}, currentBilling, {
      first_name: visibleBilling.first_name || visibleShipping.first_name || currentShipping.first_name || currentBilling.first_name,
      last_name: visibleBilling.last_name || visibleShipping.last_name || currentShipping.last_name || currentBilling.last_name,
      company: currentBilling.company,
      address_1: visibleBilling.address_1 || currentBilling.address_1,
      address_2: visibleBilling.address_2 || currentBilling.address_2,
      city: visibleBilling.city || currentBilling.city,
      state: '',
      postcode: visibleBilling.postcode || currentBilling.postcode,
      country: visibleBilling.country || currentBilling.country || 'LT',
      phone: contactPhone || currentBilling.phone || currentShipping.phone,
      email: email
    })) : compactAddress(Object.assign({}, currentBilling, currentShipping, visibleShipping, {
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

      if (hidden) {
        if (value && field.value !== value) {
          field.value = value;
          field.dispatchEvent(new Event('input', { bubbles: true }));
          field.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (field.required) {
          field.dataset.meditrendyWasRequired = '1';
        }

        if (!field.disabled) {
          field.dataset.meditrendyWasDisabled = '1';
        }

        field.required = false;
        field.setAttribute('aria-required', 'false');
        field.disabled = true;
      } else {
        if (field.dataset.meditrendyWasRequired === '1') {
          field.required = true;
          field.removeAttribute('aria-required');
          delete field.dataset.meditrendyWasRequired;
        }

        if (field.dataset.meditrendyWasDisabled === '1') {
          field.disabled = false;
          delete field.dataset.meditrendyWasDisabled;
        }
      }
    });
  }

  function syncHiddenPickupShippingFields() {
    const pickup = isPickupSelected();

    document.querySelectorAll('.wc-block-components-address-form input, .wc-block-components-address-form select, .wc-block-components-address-form textarea').forEach(function (field) {
      if (field.type === 'checkbox' || field.type === 'radio' || field.type === 'hidden') {
        return;
      }

      const meta = ((field.autocomplete || '') + ' ' + (field.name || '') + ' ' + (field.id || '')).toLowerCase();
      const isAddressField = meta.indexOf('address-line') !== -1 || meta.indexOf('address-level') !== -1 || meta.indexOf('postal-code') !== -1 || meta.indexOf('postcode') !== -1 || meta.indexOf('country') !== -1 || meta.indexOf('state') !== -1;

      if (!isAddressField) {
        return;
      }

      const hidden = field.hidden || field.closest('[hidden]') !== null || field.offsetParent === null;

      if (pickup && hidden) {
        if (field.required) {
          field.dataset.meditrendyWasRequired = '1';
        }

        field.required = false;
        field.setAttribute('aria-required', 'false');
        field.disabled = true;
      } else if (!pickup && field.dataset.meditrendyWasRequired === '1') {
        field.required = true;
        field.removeAttribute('aria-required');
        field.disabled = false;
      }
    });
  }

  function patchCheckoutPayloadForPickup(payload) {
    if (!payload || !isPickupSelected()) {
      return payload;
    }

    const pickupAddress = getPickupAddressFallback();
    const invoice = getInvoiceAddress();
    const visibleShipping = getVisibleShippingAddress();
    const visibleBilling = getVisibleBillingAddress();
    const currentBilling = compactAddress(payload.billing_address || {});
    const currentShipping = compactAddress(payload.shipping_address || {});
    const contactPhone = getPhoneField() ? getPhoneField().value : '';
    const firstName = currentBilling.first_name || visibleBilling.first_name || currentShipping.first_name || visibleShipping.first_name;
    const lastName = currentBilling.last_name || visibleBilling.last_name || currentShipping.last_name || visibleShipping.last_name;
    const phone = contactPhone || currentShipping.phone || currentBilling.phone;
    const email = currentBilling.email || getInputValue('#email, input[type="email"], input[autocomplete="email"]');
    const invoiceAddress = invoice.invoiceRequired ? {
      company: invoice.company || visibleBilling.company || currentBilling.company,
      address_1: invoice.address_1 || visibleBilling.address_1 || currentBilling.address_1,
      address_2: '',
      city: invoice.city || visibleBilling.city || currentBilling.city,
      postcode: invoice.postcode || visibleBilling.postcode || currentBilling.postcode,
      country: visibleBilling.country || currentBilling.country || pickupAddress.country,
      state: visibleBilling.state || currentBilling.state
    } : visibleBilling;

    payload.shipping_address = compactAddress(Object.assign({}, currentShipping, pickupAddress, {
      first_name: firstName,
      last_name: lastName,
      phone: phone
    }));

    payload.billing_address = compactAddress(Object.assign({}, currentBilling, invoiceAddress, {
      first_name: firstName,
      last_name: lastName,
      country: invoiceAddress.country || currentBilling.country || pickupAddress.country,
      phone: phone,
      email: email
    }));

    return payload;
  }

  function patchCheckoutPayload(payload) {
    if (!payload) {
      return payload;
    }

    if (isPickupSelected()) {
      return patchCheckoutPayloadForPickup(payload);
    }

    const contactPhone = getPhoneField() ? getPhoneField().value : '';

    if (!contactPhone) {
      return payload;
    }

    payload.billing_address = compactAddress(Object.assign({}, payload.billing_address || {}, {
      phone: contactPhone
    }));

    payload.shipping_address = compactAddress(Object.assign({}, payload.shipping_address || {}, {
      phone: contactPhone
    }));

    return payload;
  }

  function getPickupCheckoutPayload() {
    if (!isPickupSelected()) {
      return null;
    }

    const store = getWooStore('cartStore');

    if (!store || !store.select || typeof store.select.getCartData !== 'function') {
      return null;
    }

    const cartData = store.select.getCartData() || {};
    return patchCheckoutPayloadForPickup({
      billing_address: cartData.billingAddress || {},
      shipping_address: cartData.shippingAddress || {}
    });
  }

  function preparePickupAddressForFinalRequest() {
    const payload = getPickupCheckoutPayload();

    if (!payload) {
      return;
    }

    lastComputedBillingAddress = payload.billing_address;
  }

  function installCheckoutRequestPatch() {
    if (!window.fetch || window.fetch.__meditrendyCheckoutPayloadPatched) {
      return;
    }

    const originalFetch = window.fetch;

    const patchCheckoutJsonBody = function (body) {
      try {
        const payload = JSON.parse(body);
        const patchedPayload = patchCheckoutPayload(payload);
        return JSON.stringify(patchedPayload);
      } catch (error) {
        return body;
      }
    };

    window.fetch = function (input, init) {
      const url = typeof input === 'string' ? input : input && input.url ? input.url : '';
      const method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
      const isCheckoutRequest = url.indexOf('/wc/store/') !== -1 && url.indexOf('/checkout') !== -1;
      const fetchContext = this;

      if (isCheckoutRequest && method === 'POST' && init && typeof init.body === 'string') {
        init = Object.assign({}, init, {
          body: patchCheckoutJsonBody(init.body)
        });
      } else if (isCheckoutRequest && method === 'POST' && typeof Request !== 'undefined' && input instanceof Request && (!init || typeof init.body === 'undefined')) {
        return input.clone().text().then(function (body) {
          const patchedRequest = new Request(input, {
            body: patchCheckoutJsonBody(body)
          });
          return originalFetch.call(fetchContext, patchedRequest, init);
        }).catch(function () {
          return originalFetch.call(fetchContext, input, init);
        });
      }

      const request = originalFetch.call(this, input, init);

      if (!isCheckoutRequest) {
        return request;
      }

      return request.then(function (response) {
        return response;
      }).catch(function (error) {
        throw error;
      });
    };

    window.fetch.__meditrendyCheckoutPayloadPatched = true;
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
      if (!isPickupSelected()) {
        syncWooCheckoutAddresses('invoice change');
      }
      saveInvoiceFields(block, true);
    });

    block.addEventListener('input', function () {
      if (!isPickupSelected()) {
        syncWooCheckoutAddresses('invoice input');
      }
      saveInvoiceFields(block, false);
    });

    document.addEventListener('click', function (event) {
      const submitButton = event.target.closest('.wc-block-components-checkout-place-order-button, button[type="submit"]');

      if (!submitButton) {
        return;
      }

      if (!validateContactPhone()) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      if (isPickupSelected()) {
        syncWooCheckoutAddresses('place order click');
        preparePickupAddressForFinalRequest();
      } else {
        syncWooCheckoutAddresses('place order click');
      }
      syncHiddenBillingNativeFields('place order click');
      syncHiddenPickupShippingFields();
      saveInvoiceFields(block, true);
    }, true);

    document.addEventListener('submit', function (event) {
      const form = event.target.closest('.wc-block-checkout__form, form.wc-block-checkout__form');

      if (!form) {
        return;
      }

      if (!validateContactPhone()) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      if (isPickupSelected()) {
        syncWooCheckoutAddresses('place order submit');
        preparePickupAddressForFinalRequest();
      } else {
        syncWooCheckoutAddresses('place order submit');
      }
      syncHiddenBillingNativeFields('place order submit');
      syncHiddenPickupShippingFields();
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
      setPhoneError('');
      if (!isPickupSelected()) {
        syncWooCheckoutAddresses('phone input');
      }
      saveInvoiceFields(block, false);
    });

    field.addEventListener('change', function () {
      const block = document.querySelector(`.${blockClass}`);
      if (!isPickupSelected()) {
        syncWooCheckoutAddresses('phone change');
      }
      saveInvoiceFields(block, true);
    });
  }

  function prefillContactNameFields(block) {
    if (!block || !isPickupSelected()) {
      return;
    }

    const firstName = block.querySelector('#meditrendy_contact_first_name');
    const lastName = block.querySelector('#meditrendy_contact_last_name');

    if (!firstName || !lastName || (firstName.value && lastName.value)) {
      return;
    }

    const store = getWooStore('cartStore');
    const cartData = store && store.select && typeof store.select.getCartData === 'function' ? store.select.getCartData() || {} : {};
    const billing = compactAddress(cartData.billingAddress || {});
    const shipping = compactAddress(cartData.shippingAddress || {});

    if (!firstName.value) {
      firstName.value = billing.first_name || shipping.first_name || '';
    }

    if (!lastName.value) {
      lastName.value = billing.last_name || shipping.last_name || '';
    }
  }

  function syncContactNameFieldsVisibility(block) {
    if (!block) {
      return;
    }

    const pickup = isPickupSelected();
    block.hidden = !pickup;

    block.querySelectorAll('input').forEach(function (input) {
      input.required = pickup;
      input.disabled = !pickup;
      input.setAttribute('aria-required', pickup ? 'true' : 'false');
    });

    prefillContactNameFields(block);
  }

  function bindContactNameFields(block) {
    if (block.dataset.meditrendyNameReady === '1') {
      return;
    }

    block.dataset.meditrendyNameReady = '1';
    block.addEventListener('input', function () {
      syncWooCheckoutAddresses('contact name input');
      syncHiddenBillingNativeFields('contact name input');
    });

    block.addEventListener('change', function () {
      syncWooCheckoutAddresses('contact name change');
      syncHiddenBillingNativeFields('contact name change');
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

  function ensureContactNameFields() {
    const phone = getPhoneField();
    const phoneWrap = phone ? phone.closest(`.${phoneClass}`) : null;
    const target = phoneWrap && phoneWrap.parentElement ? {
      container: phoneWrap.parentElement,
      after: phoneWrap
    } : findContactTarget();

    if (!target || !target.container || !target.after) {
      return;
    }

    let fields = document.querySelector(`.${nameClass}`);

    if (!fields) {
      fields = createContactNameFields();
    }

    if (fields.parentElement !== target.container || fields.previousElementSibling !== target.after) {
      target.after.insertAdjacentElement('afterend', fields);
    }

    bindContactNameFields(fields);
    syncContactNameFieldsVisibility(fields);
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
      section.hidden = !pickup;
    });

    document.querySelectorAll('.wc-block-components-address-form').forEach(function (form) {
      const isAddressForm = form.querySelector('[autocomplete*="address-line1"], [autocomplete*="postal-code"], [autocomplete*="address-level2"]');

      if (!isAddressForm) {
        return;
      }

      const isBillingForm = form.closest('.wc-block-checkout__billing-fields') !== null;
      const shouldHideAddressFields = pickup && !isBillingForm;

      form.querySelectorAll('[autocomplete*="address-line1"], [autocomplete*="address-line2"], [autocomplete*="postal-code"], [autocomplete*="address-level2"], [autocomplete*="country"]').forEach(function (input) {
        const wrapper = findFieldWrapper(input);

        if (wrapper) {
          wrapper.hidden = shouldHideAddressFields;
        }
      });
    });
  }

  function syncCheckoutInvoiceUi() {
    ensureContactPhoneField();
    ensureInvoiceBlock();
    syncBillingAddressLabel();
    hideNativeAddressFields();
    if (!isPickupSelected()) {
      syncWooCheckoutAddresses('ui sync');
    }
    syncHiddenBillingNativeFields('ui sync');
    syncHiddenPickupShippingFields();
  }

  const observer = new MutationObserver(syncCheckoutInvoiceUi);

  function init() {
    installCheckoutRequestPatch();
    syncCheckoutInvoiceUi();

    document.addEventListener('change', function (event) {
      if (event.target && event.target.matches('input, select')) {
        if (!isPickupSelected()) {
          syncWooCheckoutAddresses('field change');
        }
        syncHiddenBillingNativeFields('field change');
        syncHiddenPickupShippingFields();
        window.setTimeout(syncCheckoutInvoiceUi, 50);
      }
    }, true);

    document.addEventListener('input', function (event) {
      if (event.target && event.target.matches('input, select, textarea')) {
        window.clearTimeout(addressSyncTimer);
        addressSyncTimer = window.setTimeout(function () {
          if (!isPickupSelected()) {
            syncWooCheckoutAddresses('document input');
          }
          syncHiddenBillingNativeFields('document input');
          syncHiddenPickupShippingFields();
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
