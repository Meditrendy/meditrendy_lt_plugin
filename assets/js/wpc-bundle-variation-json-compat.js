(function () {
  function parseVariations(form) {
    try {
      const parsed = JSON.parse(form.getAttribute('data-product_variations') || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function getSelectData(form) {
    return Array.from(form.querySelectorAll('select[name^="attribute_"]')).map(function (select) {
      return {
        name: select.getAttribute('data-attribute_name') || select.getAttribute('name') || '',
        values: Array.from(select.options)
          .map(function (option) {
            return option.value || '';
          })
          .filter(Boolean)
      };
    }).filter(function (data) {
      return data.name && data.values.length;
    });
  }

  function patchForm(form) {
    if (!form || form.dataset.mtWpcVariationJsonPatched === '1') {
      return false;
    }

    const variations = parseVariations(form);
    const selects = getSelectData(form);

    if (!variations.length || !selects.length) {
      return false;
    }

    const primary = selects.find(function (select) {
      return select.values.length === variations.length;
    });
    const singles = selects.filter(function (select) {
      return select.values.length === 1;
    });

    if (!primary) {
      return false;
    }

    let changed = false;

    variations.forEach(function (variation, index) {
      if (!variation || typeof variation !== 'object') {
        return;
      }

      if (!variation.attributes || Array.isArray(variation.attributes) || typeof variation.attributes !== 'object') {
        variation.attributes = {};
        changed = true;
      }

      if (!variation.attributes[primary.name] && primary.values[index]) {
        variation.attributes[primary.name] = primary.values[index];
        changed = true;
      }

      singles.forEach(function (single) {
        if (!variation.attributes[single.name] && single.values[0]) {
          variation.attributes[single.name] = single.values[0];
          changed = true;
        }
      });
    });

    if (!changed) {
      form.dataset.mtWpcVariationJsonPatched = '1';
      return false;
    }

    form.setAttribute('data-product_variations', JSON.stringify(variations));
    form.dataset.product_variations = JSON.stringify(variations);
    form.dataset.mtWpcVariationJsonPatched = '1';

    if (window.jQuery) {
      const $form = window.jQuery(form);
      $form.removeData('product_variations');
      $form.data('product_variations', variations);

      if ($form.data('WooVariationSwatches')) {
        try {
          $form.WooVariationSwatches('destroy');
        } catch (error) {
          // The plugin method is optional; Woo events below still refresh the form.
        }
      }

      $form.removeClass('wvs-loaded wvs-pro-loaded');
      $form.trigger('reload_product_variations');
      $form.trigger('wc_variation_form');
      window.jQuery(document).trigger('woo_variation_swatches_init');
    }

    return true;
  }

  function patchAll(root) {
    const scope = root && root.querySelectorAll ? root : document;
    const forms = scope.matches && scope.matches('form.woosb_variations_form')
      ? [scope]
      : Array.from(scope.querySelectorAll('form.woosb_variations_form'));

    forms.forEach(patchForm);
  }

  document.addEventListener('DOMContentLoaded', function () {
    patchAll(document);
    window.setTimeout(function () {
      patchAll(document);
    }, 50);
    window.setTimeout(function () {
      patchAll(document);
    }, 300);
  });

  if (window.jQuery) {
    window.jQuery(document).on('wc_variation_form.wvs woosb_init woosb_update', function (event) {
      const target = event && event.target && event.target.querySelectorAll ? event.target : document;
      patchAll(target);
    });
  }

  if (window.MutationObserver) {
    document.addEventListener('DOMContentLoaded', function () {
      if (!document.body) {
        return;
      }

      const observer = new MutationObserver(function (mutations) {
        const shouldPatch = mutations.some(function (mutation) {
          return Array.from(mutation.addedNodes).some(function (node) {
            return node.nodeType === 1
              && ((node.matches && node.matches('form.woosb_variations_form'))
                || (node.querySelector && node.querySelector('form.woosb_variations_form')));
          });
        });

        if (shouldPatch) {
          patchAll(document);
        }
      });

      observer.observe(document.body, { childList: true, subtree: true });
    });
  }
}());
