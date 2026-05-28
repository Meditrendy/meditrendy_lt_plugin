(function () {
  'use strict';

  var toggleSelector = '[data-meditrendy-invoice-toggle]';
  var dependentSelector = '[data-meditrendy-invoice-dependent]';

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

  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches(toggleSelector)) {
      syncInvoiceFields();
    }
  });

  var observer = new MutationObserver(syncInvoiceFields);

  function init() {
    syncInvoiceFields();

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
