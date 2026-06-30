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

  function getProductId(form) {
    const raw = form.getAttribute('data-product_id') || form.querySelector('[name="product_id"]')?.value || '';
    const productId = parseInt(raw, 10);

    return Number.isFinite(productId) && productId > 0 ? productId : 0;
  }

  function refreshVariationForm(form, variations) {
    form.setAttribute('data-product_variations', JSON.stringify(variations));
    form.dataset.product_variations = JSON.stringify(variations);

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
      $form.trigger('check_variations');
      window.jQuery(document).trigger('woo_variation_swatches_init');
      window.jQuery(document.body).trigger('woosb_update');
    }
  }

  function fetchMissingVariations(form) {
    const config = window.meditrendyWpcBundleVariations || {};
    const productId = getProductId(form);

    if (!config.ajaxUrl || !config.action || !productId || form.dataset.mtWpcVariationJsonFetching === '1') {
      return false;
    }

    form.dataset.mtWpcVariationJsonFetching = '1';

    const url = new URL(config.ajaxUrl, window.location.href);
    url.searchParams.set('action', config.action);
    url.searchParams.set('product_id', String(productId));

    window.fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json'
      }
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Variation request failed');
        }

        return response.json();
      })
      .then(function (payload) {
        const variations = payload && payload.success && payload.data && Array.isArray(payload.data.variations)
          ? payload.data.variations
          : [];

        if (!variations.length) {
          return;
        }

        form.dataset.mtWpcVariationJsonPatched = '0';
        refreshVariationForm(form, variations);
        patchForm(form);
      })
      .catch(function () {
        // Keep Woo/WPC's native behavior if the fallback endpoint is unavailable.
      })
      .finally(function () {
        form.dataset.mtWpcVariationJsonFetching = '0';
      });

    return true;
  }

  function ensureVariationAttributes(variation) {
    if (!variation || typeof variation !== 'object') {
      return null;
    }

    if (!variation.attributes || Array.isArray(variation.attributes) || typeof variation.attributes !== 'object') {
      variation.attributes = {};
    }

    return variation.attributes;
  }

  function fillSingleSelectAttributes(variations, singles) {
    let changed = false;

    variations.forEach(function (variation) {
      const attributes = ensureVariationAttributes(variation);

      if (!attributes) {
        return;
      }

      singles.forEach(function (single) {
        if (!attributes[single.name] && single.values[0]) {
          attributes[single.name] = single.values[0];
          changed = true;
        }
      });
    });

    return changed;
  }

  function fillByDirectOptionOrder(variations, select) {
    if (!select || select.values.length !== variations.length) {
      return false;
    }

    let changed = false;

    variations.forEach(function (variation, index) {
      const attributes = ensureVariationAttributes(variation);

      if (!attributes || !select.values[index]) {
        return;
      }

      if (!attributes[select.name]) {
        attributes[select.name] = select.values[index];
        changed = true;
      }
    });

    return changed;
  }

  function groupKeyForVariation(variation, otherSelects) {
    const attributes = ensureVariationAttributes(variation);

    if (!attributes) {
      return '';
    }

    const keyParts = [];

    for (const select of otherSelects) {
      const value = attributes[select.name] || (select.values.length === 1 ? select.values[0] : '');

      if (!value) {
        return '';
      }

      keyParts.push(select.name + '=' + value);
    }

    return keyParts.join('|');
  }

  function fillByGroupedOptionOrder(variations, selects) {
    let changed = false;

    selects.forEach(function (targetSelect) {
      const otherSelects = selects.filter(function (select) {
        return select.name !== targetSelect.name;
      });
      const groups = new Map();

      variations.forEach(function (variation, index) {
        const attributes = ensureVariationAttributes(variation);

        if (!attributes || attributes[targetSelect.name]) {
          return;
        }

        const key = groupKeyForVariation(variation, otherSelects);

        if (!key) {
          return;
        }

        if (!groups.has(key)) {
          groups.set(key, []);
        }

        groups.get(key).push({ variation, index });
      });

      groups.forEach(function (group) {
        if (group.length !== targetSelect.values.length) {
          return;
        }

        group.sort(function (left, right) {
          return left.index - right.index;
        });

        group.forEach(function (entry, index) {
          const attributes = ensureVariationAttributes(entry.variation);
          const value = targetSelect.values[index] || '';

          if (attributes && value && !attributes[targetSelect.name]) {
            attributes[targetSelect.name] = value;
            changed = true;
          }
        });
      });
    });

    return changed;
  }

  function patchForm(form) {
    if (!form || form.dataset.mtWpcVariationJsonPatched === '1') {
      return false;
    }

    const variations = parseVariations(form);
    const selects = getSelectData(form);

    if (!variations.length) {
      fetchMissingVariations(form);
      return false;
    }

    if (!selects.length) {
      return false;
    }

    const singles = selects.filter(function (select) {
      return select.values.length === 1;
    });

    let changed = false;

    changed = fillSingleSelectAttributes(variations, singles) || changed;

    selects.forEach(function (select) {
      changed = fillByDirectOptionOrder(variations, select) || changed;
    });

    changed = fillByGroupedOptionOrder(variations, selects) || changed;

    if (!changed) {
      form.dataset.mtWpcVariationJsonPatched = '1';
      return false;
    }

    form.dataset.mtWpcVariationJsonPatched = '1';
    refreshVariationForm(form, variations);

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
