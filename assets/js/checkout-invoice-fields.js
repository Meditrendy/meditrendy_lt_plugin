(function () {
  'use strict';

  const settings = window.meditrendyCheckoutInvoice || {};
  const blockClass = 'meditrendy-checkout-invoice-fields';
  const billingAddressLabels = ['Billing address', 'Pirk\u0117jo adresas'];
  const labels = Object.assign({
    invoiceRequired: 'Reikia s\u0105skaitos fakt\u016bros \u012fmonei',
    companyName: '\u012emon\u0117s pavadinimas (neb\u016btina)',
    companyCode: '\u012emon\u0117s kodas (neb\u016btina)',
    invoiceAddress: 'Adresas s\u0105skaitai'
  }, settings.labels || {});

  let saveTimer = null;
  let lastPayload = '';
  let saving = null;
  let replayingSubmit = false;

  function createTextInput(id, label, value, autocomplete) {
    const wrap = document.createElement('div');
    wrap.className = 'meditrendy-checkout-invoice-fields__field';

    const input = document.createElement('input');
    input.type = 'text';
    input.id = id;
    input.name = id;
    input.value = value || '';
    input.autocomplete = autocomplete || 'off';

    const labelElement = document.createElement('label');
    labelElement.htmlFor = id;
    labelElement.textContent = label;

    wrap.append(labelElement, input);

    return wrap;
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
    fields.append(
      createTextInput('meditrendy_company_name', labels.companyName, settings.companyName, 'organization'),
      createTextInput('meditrendy_company_code', labels.companyCode, settings.companyCode, 'off')
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

  function syncInvoiceBlockVisibility(block) {
    const checkbox = block.querySelector('#meditrendy_invoice_required');
    const details = block.querySelector('.meditrendy-checkout-invoice-fields__details');

    if (!checkbox || !details) {
      return;
    }

    details.hidden = !checkbox.checked;
  }

  function getPayload(block) {
    const checkbox = block.querySelector('#meditrendy_invoice_required');
    const companyName = block.querySelector('#meditrendy_company_name');
    const companyCode = block.querySelector('#meditrendy_company_code');
    const payload = new URLSearchParams();

    payload.set('action', 'meditrendy_save_checkout_invoice_fields');
    payload.set('nonce', settings.nonce || '');
    payload.set('invoice_required', checkbox && checkbox.checked ? '1' : '');
    payload.set('company_name', companyName ? companyName.value : '');
    payload.set('company_code', companyCode ? companyCode.value : '');

    return payload;
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
      saveInvoiceFields(block, true);
    });

    block.addEventListener('input', function () {
      saveInvoiceFields(block, false);
    });

    document.addEventListener('click', function (event) {
      const submitButton = event.target.closest('.wc-block-components-checkout-place-order-button, button[type="submit"]');

      if (!submitButton || replayingSubmit) {
        return;
      }

      const payload = getPayload(block).toString();

      if (payload === lastPayload) {
        return;
      }

      event.preventDefault();
      event.stopImmediatePropagation();

      saveInvoiceFields(block, true).finally(function () {
        replayingSubmit = true;
        submitButton.click();
        replayingSubmit = false;
      });
    }, true);
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

  function syncCheckoutInvoiceUi() {
    ensureInvoiceBlock();
    syncBillingAddressLabel();
  }

  const observer = new MutationObserver(syncCheckoutInvoiceUi);

  function init() {
    syncCheckoutInvoiceUi();

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
