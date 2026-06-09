(function () {
  'use strict';

  var toggleSelector = '[data-meditrendy-invoice-toggle]';
  var dependentSelector = '[data-meditrendy-invoice-dependent]';
  var billingAddressLabels = ['Billing address', 'Pirk\u0117jo adresas'];
  var invoiceAddressLabel = 'Adresas s\u0105skaitai';

  function getFieldWrap(input) {
    return input.closest(
      '.wc-block-components-text-input, .wc-block-components-checkbox, .components-base-control'
    ) || input.parentElement;
  }

  function triggerReactInput(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function syncInvoiceFields() {
    var toggle = document.querySelector(toggleSelector);
    var showFields = !!(toggle && toggle.checked);

    document.querySelectorAll(dependentSelector).forEach(function (input) {
      var wrap = getFieldWrap(input);

      if (!wrap) {
        return;
      }

      wrap.hidden = !showFields;

      if (!showFields && input.value) {
        input.value = '';
        triggerReactInput(input);
      }
    });
  }

  function syncBillingAddressLabel() {
    document.querySelectorAll('.wc-block-checkout__billing-fields h2, .wc-block-checkout__billing-fields legend, .wc-block-checkout__billing-fields .wc-block-components-title').forEach(function (element) {
      var label = element.textContent.trim();

      if (billingAddressLabels.indexOf(label) !== -1) {
        element.textContent = invoiceAddressLabel;
      }
    });
  }

  function syncCheckoutInvoiceUi() {
    syncInvoiceFields();
    syncBillingAddressLabel();
  }

  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches(toggleSelector)) {
      syncCheckoutInvoiceUi();
    }
  });

  var observer = new MutationObserver(syncCheckoutInvoiceUi);

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
