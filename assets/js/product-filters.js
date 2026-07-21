(function () {
  var selector = '[data-mt-native-filters]';
  var uid = 0;
  var countTimers = new WeakMap();
  var filterStates = new WeakMap();
  const productRequests = new WeakMap();

  function ensureFormId(form) {
    if (!form.id) {
      uid += 1;
      form.id = 'mt-native-filters-' + uid;
    }

    return form.id;
  }

  function associateControls(panel, form) {
    var formId = ensureFormId(form);

    panel.querySelectorAll('input, select, button').forEach(function (control) {
      control.setAttribute('form', formId);
    });
  }

  function filterControls(form, controlSelector) {
    var panel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
    var controls = [];

    function collect(root) {
      if (!root) return;

      root.querySelectorAll(controlSelector).forEach(function (control) {
        if (controls.indexOf(control) === -1) {
          controls.push(control);
        }
      });
    }

    collect(form);
    collect(panel);
    document.querySelectorAll('.mt-native-filter-body.is-open').forEach(collect);

    controls = controls.filter(function (control) {
      return control.name;
    });

    return controls;
  }

  function ajaxUrl(form) {
    return form.dataset.ajaxUrl || (window.MeditrendyNativeFilters && window.MeditrendyNativeFilters.ajaxUrl) || '';
  }

  function ajaxNonce(form) {
    return form.dataset.nonce || (window.MeditrendyNativeFilters && window.MeditrendyNativeFilters.nonce) || '';
  }

  function formForElement(element) {
    if (!element) return document.querySelector(selector);

    if (element.form && element.form.matches && element.form.matches(selector)) {
      return element.form;
    }

    return element.closest(selector) || document.querySelector(selector);
  }

  function isFilterControl(element) {
    return element
      && element.matches('select, input[type="checkbox"], input[type="number"]')
      && (element.closest('.mt-native-filters-panel') || element.closest('.mt-native-filter-body'));
  }

  function cleanName(name) {
    return (name || '').replace(/\[\]$/, '');
  }

  function getFilterState(form) {
    var state = filterStates.get(form);

    if (!state) {
      state = {};
      filterStates.set(form, state);
    }

    return state;
  }

  function addFilterValue(form, name, value) {
    name = cleanName(name);
    if (!name || !value) return;

    var state = getFilterState(form);

    if (!state[name]) {
      state[name] = [];
    }

    if (state[name].indexOf(value) === -1) {
      state[name].push(value);
    }
  }

  function removeFilterValue(form, name, value) {
    name = cleanName(name);
    if (!name) return;

    var state = getFilterState(form);

    if (!state[name]) return;

    state[name] = state[name].filter(function (current) {
      return current !== value;
    });

    if (!state[name].length) {
      delete state[name];
    }
  }

  function clearFilterValue(form, name) {
    name = cleanName(name);
    if (!name) return;

    var state = getFilterState(form);
    delete state[name];
  }

  function setControlState(form, control) {
    if (!control || !control.name) return;

    if (control.matches('input[type="checkbox"]')) {
      if (control.checked) {
        addFilterValue(form, control.name, control.value);
      } else {
        removeFilterValue(form, control.name, control.value);
      }

      return;
    }

    clearFilterValue(form, control.name);

    if (control.value) {
      addFilterValue(form, control.name, control.value);
    }
  }

  function syncStateFromControls(form) {
    filterStates.set(form, {});

    filterControls(form, 'select, input[type="checkbox"], input[type="number"]').forEach(function (control) {
      if (control.matches('input[type="checkbox"]') && !control.checked) return;
      if (control.matches('select') && !control.value) return;
      if (control.matches('input[type="number"]') && !control.value) return;

      setControlState(form, control);
    });
  }

  function stateEntries(form) {
    var state = getFilterState(form);
    var entries = [];

    Object.keys(state).forEach(function (name) {
      state[name].forEach(function (value) {
        entries.push([name === 'mt_min_price' || name === 'mt_max_price' ? name : name + '[]', value]);
      });
    });

    return entries;
  }

  function controlSelected(form, control) {
    var state = getFilterState(form);
    var values = state[cleanName(control.name)] || [];

    return values.indexOf(control.value) !== -1;
  }

  function restoreDropdown(filter) {
    var body = filter && filter._mtDropdownBody;
    var placeholder = filter && filter._mtDropdownPlaceholder;

    if (!body || !placeholder || !placeholder.parentNode) return;

    body.classList.remove('is-open');
    body.style.removeProperty('--mt-filter-left');
    body.style.removeProperty('--mt-filter-top');
    placeholder.parentNode.insertBefore(body, placeholder);
    placeholder.remove();
    filter._mtDropdownBody = null;
    filter._mtDropdownPlaceholder = null;
  }

  function portalDropdown(filter, form) {
    if (!filter || isMobile()) return;

    var body = filter.querySelector('.mt-native-filter-body');
    if (!body || filter._mtDropdownBody) return;

    var placeholder = document.createComment('mt-native-filter-body');
    body.parentNode.insertBefore(placeholder, body);
    body.dataset.filter = filter.dataset.filter || '';
    associateControls(body, form);
    document.body.appendChild(body);
    body.classList.add('is-open');

    filter._mtDropdownBody = body;
    filter._mtDropdownPlaceholder = placeholder;
  }

  function portalPanel(form) {
    var panel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
    if (!panel || form._mtPanelPlaceholder || !isMobile()) return;

    var placeholder = document.createComment('mt-native-filters-panel');
    panel.parentNode.insertBefore(placeholder, panel);
    associateControls(panel, form);
    document.body.appendChild(panel);
    panel.classList.add('is-open');

    form._mtPanel = panel;
    form._mtPanelPlaceholder = placeholder;
  }

  function restorePanel(form) {
    var panel = form._mtPanel;
    var placeholder = form._mtPanelPlaceholder;

    if (!panel || !placeholder || !placeholder.parentNode) return;

    panel.classList.remove('is-open');
    placeholder.parentNode.insertBefore(panel, placeholder);
    placeholder.remove();
    form._mtPanelPlaceholder = null;
  }

  function closeAllExcept(form, current) {
    var panel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;

    if (isMobile()) {
      form.classList.toggle('is-layered', !!current);
      return;
    }

    panel.querySelectorAll('.mt-native-filter.is-open').forEach(function (filter) {
      if (filter !== current) {
        filter.classList.remove('is-open');
        filter.querySelector('.mt-native-filter-heading').setAttribute('aria-expanded', 'false');
        restoreDropdown(filter);
      }
    });

    form.classList.toggle('is-layered', !!current);
  }

  function expandSelectedMobileFilters(form) {
    if (!isMobile()) return;

    var panel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
    if (!panel) return;

    panel.querySelectorAll('.mt-native-filter').forEach(function (filter) {
      var hasSelected = !!filter.querySelector('input[type="checkbox"]:checked, input[type="number"]:not([value=""]), select option:checked:not([value=""])');
      var heading = filter.querySelector('.mt-native-filter-heading');

      filter.classList.toggle('is-open', hasSelected);

      if (heading) {
        heading.setAttribute('aria-expanded', String(hasSelected));
      }
    });
  }

  function containsPortaledPart(form, target) {
    if (form.contains(target)) return true;
    if (form._mtPanel && form._mtPanel.contains(target)) return true;

    var insideDropdown = false;
    document.querySelectorAll('.mt-native-filter-body.is-open').forEach(function (body) {
      if (body.contains(target)) {
        insideDropdown = true;
      }
    });

    return insideDropdown;
  }

  function setLoading(form) {
    var targets = productTargets();

    form.classList.add('is-loading');
    form.removeAttribute('data-filter-error');
    document.documentElement.classList.add('mt-native-products-loading');
    setSubmitText(form, labels().loading || 'Kraunama...');

    if (targets.products) {
      targets.products.classList.add('is-mt-products-loading');
    } else {
      document.body.classList.add('is-mt-products-loading-fallback');
    }
  }

  function clearLoading(form) {
    form.classList.remove('is-loading');
    document.documentElement.classList.remove('mt-native-products-loading');
    document.querySelectorAll('.is-mt-products-loading').forEach(function (products) {
      products.classList.remove('is-mt-products-loading');
    });
    document.body.classList.remove('is-mt-products-loading-fallback');
  }

  function filterError(form, reason, detail) {
    if (form) {
      form.dataset.filterError = reason;
    }

    window.dispatchEvent(new CustomEvent('meditrendy:filters-error', {
      detail: { reason: reason, detail: detail || null }
    }));
  }

  function labels() {
    return (window.MeditrendyNativeFilters && window.MeditrendyNativeFilters.labels) || {};
  }

  function settings() {
    return (window.MeditrendyNativeFilters && window.MeditrendyNativeFilters.settings) || {};
  }

  function isMobile() {
    return window.matchMedia('(max-width: 980px)').matches;
  }

  function positionDropdown(filter) {
    if (!filter || isMobile()) return;

    var heading = filter.querySelector('.mt-native-filter-heading');
    var body = filter._mtDropdownBody || filter.querySelector('.mt-native-filter-body');
    if (!heading || !body) return;

    var rect = heading.getBoundingClientRect();
    var bodyWidth = body.offsetWidth || 280;
    var left = Math.min(rect.left, window.innerWidth - bodyWidth - 16);

    body.style.setProperty('--mt-filter-left', Math.round(Math.max(16, left)) + 'px');
    body.style.setProperty('--mt-filter-top', Math.round(rect.bottom + 8) + 'px');
  }

  function submitForm(form, options) {
    var url = buildFilterUrl(form);

    loadFilteredProducts(form, url, options || {});
  }

  function buildFilterUrl(form) {
    var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    var paramsToRemove = [
      'paged',
      'product-page',
      'mt_min_price',
      'mt_max_price',
      'filter_group_color',
      'filter_color',
      'filter_color-group',
      'filter_size',
      'filter_length',
      'filter_ilgis',
      'filter_kelniu-ilgis',
      'filter_pants-length',
      'filter_brand'
    ];

    (form.dataset.filterParams || '').split(',').forEach(function (param) {
      param = param.trim();

      if (param) {
        paramsToRemove.push(param);
        paramsToRemove.push(param + '[]');
      }
    });

    Object.keys(getFilterState(form)).forEach(function (name) {
      paramsToRemove.push(name);
      paramsToRemove.push(name + '[]');
    });

    Array.from(url.searchParams.keys()).forEach(function (param) {
      if (param.indexOf('filter_') === 0) {
        paramsToRemove.push(param);
      }
    });

    paramsToRemove.forEach(function (param) {
      url.searchParams.delete(param);
    });

    stateEntries(form).forEach(function (entry) {
      url.searchParams.append(entry[0], entry[1]);
    });

    return url;
  }

  function isProductGrid(element) {
    if (!element || element.closest(selector) || element.closest('.mt-native-filters-panel')) {
      return false;
    }

    return element.matches('ul.products')
      || element.querySelector('.product, li.product, .woocommerce-loop-product__title');
  }

  function productCardSelector() {
    return '.product-loop, .entry-product, li.product, .woocommerce-loop-product__title, .tp-image-wrapper';
  }

  function productCardCount(element) {
    if (!element) return 0;

    var entryProducts = element.querySelectorAll('.entry-product').length;
    if (entryProducts) return entryProducts;

    var listProducts = element.querySelectorAll('li.product, .product').length;
    if (listProducts) return listProducts;

    var titles = element.querySelectorAll('.woocommerce-loop-product__title, .tp-image-wrapper').length;
    return titles;
  }

  function findRepeatedProductWrapper(root) {
    var firstCard = Array.prototype.find.call(root.querySelectorAll(productCardSelector()), function (card) {
      return !card.closest(selector)
        && !card.closest('.mt-native-filters-panel')
        && !card.closest('.mt-native-filter-debug');
    });

    if (!firstCard) {
      return null;
    }

    var current = firstCard.parentElement;
    var best = null;

    while (current && current !== document.body && current.nodeType === 1) {
      if (current.closest(selector) || current.closest('.mt-native-filters-panel')) {
        break;
      }

      var count = productCardCount(current);

      if (count >= 2) {
        best = current;

        if (
          current.matches('ul, .products, [class*="grid"], [class*="Grid"], [class*="loop"], [class*="Loop"], [class*="archive"], [class*="Archive"]')
          || current.children.length >= 2
        ) {
          break;
        }
      }

      current = current.parentElement;
    }

    return best;
  }

  function findProductGrid(root) {
    if (root === document) {
      var explicitWrapper = document.querySelector('#products-wrapper');

      if (explicitWrapper) {
        return explicitWrapper;
      }
    }

    var candidates = Array.prototype.slice.call(root.querySelectorAll('ul.products, .products'));
    var grid = candidates.find(isProductGrid) || findRepeatedProductWrapper(root);

    return grid;
  }

  function productTargets() {
    return {
      products: findProductGrid(document),
      pagination: findPagination(document),
      resultCount: document.querySelector('.woocommerce-result-count')
    };
  }

  function updateProductCategoryDescription(url) {
    var isFirstPage = currentPageFromUrl(url) === 1;

    document.querySelectorAll('.mt-product-category-description').forEach(function (description) {
      description.hidden = !isFirstPage;
    });
  }

  function paginationSelector() {
    return '.x-paginate, .x-pagination, .woocommerce-pagination, .pagination, .page-numbers, [class*="pagination"], [class*="Pagination"]';
  }

  function paginationContainers(root) {
    var candidates = Array.prototype.slice.call(root.querySelectorAll(paginationSelector()));

    return candidates.filter(function (pagination) {
      if (pagination.matches('a, span')) {
        return false;
      }

      if (pagination.closest(selector) || pagination.closest('.mt-native-filters-panel')) {
        return false;
      }

      if (!pagination.querySelector('a[href], .page-numbers')) {
        return false;
      }

      return !candidates.some(function (other) {
        return other !== pagination && other.contains(pagination);
      });
    });
  }

  function findPagination(root) {
    var candidates = paginationContainers(root);
    var products = findProductGrid(document);

    if (products) {
      var afterProducts = candidates.filter(function (pagination) {
        return !!(products.compareDocumentPosition(pagination) & Node.DOCUMENT_POSITION_FOLLOWING);
      });

      if (afterProducts.length) {
        candidates = afterProducts;
      }
    }

    candidates.sort(function (a, b) {
      var aScore = a.matches('.x-paginate, .x-pagination') ? 0 : (a.matches('.pagination') ? 1 : 2);
      var bScore = b.matches('.x-paginate, .x-pagination') ? 0 : (b.matches('.pagination') ? 1 : 2);

      return aScore - bScore;
    });

    return candidates[0] || null;
  }

  function isPaginationLink(link) {
    if (!link || link.closest(selector) || link.closest('.mt-native-filters-panel')) {
      return false;
    }

    if (link.matches('.page-numbers, .next, .prev')) {
      return true;
    }

    return !!link.closest(paginationSelector());
  }

  function replaceOrRemove(current, next) {
    if (current && next) {
      current.replaceWith(next);
      return next;
    }

    if (current && !next) {
      current.remove();
      return null;
    }

    return current;
  }

  function replaceOrInsertPagination(current, next, products) {
    if (current && next) {
      current.replaceWith(next);
      return next;
    }

    if (current && !next) {
      current.remove();
      return null;
    }

    if (!current && next && products) {
      products.insertAdjacentElement('afterend', next);
      return next;
    }

    return current;
  }

  function firstText(root, selector) {
    var element = root.querySelector(selector);

    return element ? element.textContent.trim() : '';
  }

  function firstAttr(root, selector, attr) {
    var element = root.querySelector(selector);

    return element ? element.getAttribute(attr) : '';
  }

  function normalizeImageSrc(src) {
    return (src || '').replace(/-\d+x\d+(\.[a-zA-Z0-9]+)(\?.*)?$/, '$1$2');
  }

  function normalizePriceText(price) {
    price = (price || '').replace(/\s+/g, ' ').trim();

    if (price.indexOf('€') > 0) {
      price = '€' + price.replace('€', '').trim();
    }

    return price;
  }

  function classFrom(target, selector, fallback) {
    var element = target.querySelector(selector);

    return element ? element.className : fallback;
  }

  function buildCornerstoneProductCard(product, target, template) {
    template = template || target.querySelector('.product-loop');
    var href = firstAttr(product, '.entry-product a, a', 'href');
    var imgSrc = normalizeImageSrc(firstAttr(product, '.tp-image, img', 'src'));
    var imgAlt = 'Medicininės palaidinės';
    var titleText = firstText(product, 'h3, .woocommerce-loop-product__title');
    var priceText = normalizePriceText(firstText(product, '.price'));

    if (template) {
      var clone = template.cloneNode(true);
      var title = clone.querySelector('.x-text-content-text h3.x-text-content-text-primary, h3');
      var price = clone.querySelector('.x-text-content-text span.x-text-content-text-primary');

      clone.querySelectorAll('a').forEach(function (link) {
        link.href = href || '#';
      });

      clone.querySelectorAll('img').forEach(function (image) {
        image.src = imgSrc || '';
        image.removeAttribute('srcset');
        image.removeAttribute('sizes');
        image.removeAttribute('width');
        image.removeAttribute('height');
        image.alt = imgAlt || '';
        image.loading = 'lazy';
      });

      if (title) {
        title.textContent = titleText;
      }

      if (price) {
        price.textContent = priceText;
      }

      return clone;
    }

    var col = document.createElement('div');
    var productCart = document.createElement('div');
    var bg = document.createElement('div');
    var bgLayer = document.createElement('div');
    var imageLink = document.createElement('a');
    var image = document.createElement('img');
    var titleWrap = document.createElement('div');
    var titleContent = document.createElement('div');
    var titleContentText = document.createElement('div');
    var title = document.createElement('h3');
    var priceWrap = document.createElement('div');
    var priceContent = document.createElement('div');
    var priceContentText = document.createElement('div');
    var price = document.createElement('span');

    col.className = classFrom(target, '.product-loop', 'x-col product-loop');
    productCart.className = classFrom(target, '.product-cart', 'x-div product-cart');
    bg.className = 'x-bg';
    bg.setAttribute('aria-hidden', 'true');
    bgLayer.className = 'x-bg-layer-upper-custom';
    imageLink.className = classFrom(target, '.product-cart .x-image, .product-loop .x-image', 'x-image');
    imageLink.href = href || '#';
    image.src = imgSrc || '';
    image.alt = imgAlt || '';
    image.loading = 'lazy';
    titleWrap.className = classFrom(target, '.product-loop > .x-text:nth-of-type(1)', 'x-text x-text-headline');
    titleContent.className = 'x-text-content';
    titleContentText.className = 'x-text-content-text';
    title.className = 'x-text-content-text-primary';
    title.textContent = titleText;
    priceWrap.className = classFrom(target, '.product-loop > .x-text:nth-of-type(2)', 'x-text x-text-headline');
    priceContent.className = 'x-text-content';
    priceContentText.className = 'x-text-content-text';
    price.className = 'x-text-content-text-primary';
    price.textContent = priceText;

    bg.appendChild(bgLayer);
    imageLink.appendChild(image);
    productCart.appendChild(bg);
    productCart.appendChild(imageLink);
    titleContentText.appendChild(title);
    titleContent.appendChild(titleContentText);
    titleWrap.appendChild(titleContent);
    priceContentText.appendChild(price);
    priceContent.appendChild(priceContentText);
    priceWrap.appendChild(priceContent);
    col.appendChild(productCart);
    col.appendChild(titleWrap);
    col.appendChild(priceWrap);

    return col;
  }

  function replaceCornerstoneProducts(target, nextProducts) {
    if (!target || target.id !== 'products-wrapper') {
      return false;
    }

    var inner = target.querySelector('.x-row-inner') || target;
    var products = Array.prototype.slice.call(nextProducts.querySelectorAll('li.product'));
    var template = target.querySelector('.product-loop');

    inner.innerHTML = '';

    if (!products.length) {
      var empty = document.createElement('div');
      empty.className = 'x-col product-loop mt-native-no-products';
      empty.dataset.defaultText = labels().noProducts || 'Produktų nerasta';
      empty.textContent = 'Produktų nerasta';
      empty.textContent = empty.dataset.defaultText;
      inner.appendChild(empty);
      return true;
    }

    products.forEach(function (product) {
      inner.appendChild(buildCornerstoneProductCard(product, target, template));
    });

    return true;
  }

  function replaceProductCardsHtml(target, html) {
    if (!target || target.id !== 'products-wrapper' || !html) {
      return false;
    }

    const nextTarget = htmlToElement(html);

    if (!nextTarget) {
      return false;
    }

    target.replaceWith(nextTarget);

    return true;
  }

  function htmlToElement(html) {
    var template = document.createElement('template');

    template.innerHTML = (html || '').trim();

    return template.content.firstElementChild;
  }

  function closeMobilePanel(form) {
    var trigger = form.querySelector('.mt-native-filters-trigger');

    form.classList.remove('is-open');
    restorePanel(form);

    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    }

    document.documentElement.classList.remove('mt-native-filters-lock');
    document.body.classList.remove('mt-native-filters-lock');

    scrollToTop();
  }

  function scrollToTop() {
    window.requestAnimationFrame(function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  function loadFilteredProducts(form, url, options) {
    options = options || {};

    var targets = productTargets();

    if (!window.fetch || !ajaxUrl(form)) {
      clearLoading(form);
      filterError(form, 'missing_ajax');
      return;
    }

    window.clearTimeout(countTimers.get(form));

    const previousRequest = productRequests.get(form);

    if (previousRequest && previousRequest.controller) {
      previousRequest.controller.abort();
    }

    const controller = window.AbortController ? new AbortController() : null;
    const request = { controller: controller };
    productRequests.set(form, request);

    setLoading(form);

    var data = selectedPayload(form);
    data.append('action', 'meditrendy_native_filters_products');
    data.append('mt_filter_paged', String(currentPageFromUrl(url)));
    data.append('mt_filter_url', url.toString());

    url.searchParams.forEach(function (value, key) {
      if (key.indexOf('mt_') === 0 && key !== 'mt_filter_paged' && key !== 'mt_filter_url') {
        data.append(key, value);
      }
    });

    fetch(ajaxUrl(form), {
      method: 'POST',
      credentials: 'same-origin',
      body: data,
      signal: controller ? controller.signal : undefined
    })
      .then(function (response) {
        if (productRequests.get(form) !== request) {
          return null;
        }

        if (!response.ok) {
          throw new Error('Filter request failed');
        }

        return response.json();
      })
      .then(function (response) {
        if (!response || productRequests.get(form) !== request) {
          return;
        }

        if (!response || !response.success || !response.data) {
          filterError(form, 'bad_response', response);
          return;
        }

        var nextProducts = htmlToElement(response.data.productsHtml);
        var nextPagination = htmlToElement(response.data.paginationHtml);
        var nextResultCount = htmlToElement(response.data.resultCountHtml);

        if (!nextProducts) {
          filterError(form, 'missing_products_html', response.data);
          return;
        }

        if (!targets.products) {
          filterError(form, 'missing_products_target', {
            productsHtml: response.data.productsHtml
          });
          return;
        }

        if (
          !replaceProductCardsHtml(targets.products, response.data.productCardsHtml)
          && !replaceCornerstoneProducts(targets.products, nextProducts)
        ) {
          replaceOrRemove(targets.products, nextProducts);
        }
        replaceOrInsertPagination(targets.pagination, nextPagination, targets.products);
        replaceOrRemove(targets.resultCount, nextResultCount);
        updateActiveRows(form);
        updateFilterOptionCounts(form, response.data.optionCounts);
        updatePricePlaceholders(form, response.data.priceRange);
        updateActiveChips(form);
        closeAllExcept(form, null);
        if (options.closeMobilePanel) {
          closeMobilePanel(form);
        }
        if (options.scrollTop) {
          scrollToTop();
        }
        setProductsLoadedText(form);
        updateProductCategoryDescription(url);

        if (!options.replaceHistory) {
          window.history.pushState({ mtNativeFilters: true }, '', url.toString());
        }
      })
      .catch(function (error) {
        if (error && error.name === 'AbortError') {
          return;
        }

        filterError(form, 'request_failed', error);
        resetSubmitText(form);
      })
      .finally(function () {
        if (productRequests.get(form) === request) {
          productRequests.delete(form);
          clearLoading(form);
        }
      });
  }

  function updateActiveRows(form) {
    filterControls(form, 'input[type="checkbox"]').forEach(function (input) {
      var row = input.closest('li');

      input.checked = controlSelected(form, input);

      if (row) {
        row.classList.toggle('is-active', controlSelected(form, input));
      }
    });

    filterControls(form, 'input[type="number"], select').forEach(function (control) {
      var values = getFilterState(form)[cleanName(control.name)] || [];

      if (!values.length) {
        control.value = '';
        return;
      }

      control.value = values[0];
    });
  }

  function updateFilterOptionCounts(form, optionCounts) {
    if (!optionCounts) return;

    filterControls(form, 'input[type="checkbox"]').forEach(function (input) {
      var row = input.closest('li');
      var filterCounts = optionCounts[cleanName(input.name)] || {};
      var count = filterCounts[input.value];
      var selected = controlSelected(form, input);

      if (count === undefined || count === null || !row) {
        return;
      }

      count = parseInt(count, 10) || 0;
      row.dataset.filterCount = `(${String(count)})`;
      row.classList.toggle('is-unavailable', !!settings().disableUnavailable && !selected && count < 1);
      input.disabled = !!settings().disableUnavailable && !selected && count < 1;

      var countElement = row.querySelector('.mt-native-filter-option-count');

      if (countElement && settings().showCounts !== false) {
        countElement.textContent = `(${String(count)})`;
      }
    });
  }

  function updatePricePlaceholders(form, priceRange) {
    if (!priceRange) return;

    var minInput = filterControls(form, 'input[name="mt_min_price"]')[0];
    var maxInput = filterControls(form, 'input[name="mt_max_price"]')[0];

    if (minInput) {
      minInput.placeholder = priceRange.min || '';
    }

    if (maxInput) {
      maxInput.placeholder = priceRange.max || '';
    }
  }

  function activeChipLabel(input) {
    var row = input.closest('li');
    var label = row && row.querySelector('label');
    var body = input.closest('.mt-native-filter-body');
    var filter = input.closest('.mt-native-filter');

    if (!filter && body && body.dataset.filter) {
      filter = document.querySelector('.mt-native-filter[data-filter="' + body.dataset.filter + '"]');
    }

    var title = filter && filter.querySelector('.mt-native-filter-heading span');
    var value = input.value;

    if (label) {
      var name = label.querySelector('span:not(.mt-native-color-dot):not(.mt-native-filter-option-count)');

      value = name ? name.textContent.trim() : label.textContent.trim();
    }

    if (input.name === 'mt_min_price') {
      value = 'min: ' + input.value;
    }

    if (input.name === 'mt_max_price') {
      value = 'max: ' + input.value;
    }

    return (title ? title.textContent.trim() + ': ' : '') + value;
  }

  function updateActiveChips(form) {
    var checkedInputs = filterControls(form, 'input[type="checkbox"], input[type="number"], select').filter(function (input) {
      if (input.matches('input[type="checkbox"]')) {
        return controlSelected(form, input);
      }

      return !!input.value;
    });

    checkedInputs = checkedInputs.filter(function (input) {
      return controlSelected(form, input);
    });
    var container = document.querySelector('.mt-native-active-filters');

    if (!checkedInputs.length) {
      if (container) {
        container.remove();
      }

      return;
    }

    if (!container) {
      container = document.createElement('div');
      container.className = 'mt-native-active-filters';
      container.setAttribute('aria-label', 'Aktyvus filtrai');
      form.insertAdjacentElement('afterend', container);
    }

    container.innerHTML = '';

    checkedInputs.forEach(function (input) {
      var chip = document.createElement('a');
      var label = document.createElement('span');
      var remove = document.createElement('span');

      chip.href = '#';
      chip.className = 'mt-native-active-filter';
      chip.dataset.filterName = input.name;
      chip.dataset.filterValue = input.value;
      label.textContent = activeChipLabel(input);
      remove.className = 'mt-native-active-filter-remove';
      remove.setAttribute('aria-hidden', 'true');
      chip.appendChild(label);
      chip.appendChild(remove);
      container.appendChild(chip);
    });

    var reset = document.createElement('a');
    reset.href = '#';
    reset.className = 'mt-native-active-filter mt-native-active-filter-reset';
    reset.dataset.filtersReset = '1';
    reset.textContent = labels().reset || 'Reset';
    container.appendChild(reset);
  }

  function clearSelections(form) {
    filterStates.set(form, {});

    updateActiveRows(form);
    updateActiveChips(form);
  }

  function resetSubmitText(form) {
    setSubmitText(form, labels().submit || 'Rodyti rezultatus');
  }

  function setProductsLoadedText(form) {
    setSubmitText(form, labels().showProducts || 'Rodyti produktus');
  }

  function setSubmitText(form, text) {
    var panel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
    var submit = panel && panel.querySelector('.mt-native-filters-submit');

    if (submit) {
      submit.textContent = text;
    }
  }

  function selectedPayload(form) {
    var data = new FormData();

    data.append('nonce', ajaxNonce(form));

    if (form.dataset.contextTaxonomy && form.dataset.contextTerm) {
      data.append('mt_filter_context_taxonomy', form.dataset.contextTaxonomy);
      data.append('mt_filter_context_term', form.dataset.contextTerm);
    }

    stateEntries(form).forEach(function (entry) {
      data.append(entry[0], entry[1]);
    });

    return data;
  }

  function currentPageFromUrl(url) {
    var paged = url.searchParams.get('paged') || url.searchParams.get('product-page');
    var pathMatch = url.pathname.match(/\/page\/([0-9]+)\/?$/);

    if (!paged && pathMatch) {
      paged = pathMatch[1];
    }

    return Math.max(1, parseInt(paged || '1', 10));
  }

  function paginationUrlFromCurrentFilters(form, link) {
    var linkUrl = new URL(link.href, window.location.href);
    var page = currentPageFromUrl(linkUrl);
    var url = buildFilterUrl(form);

    url.searchParams.delete('paged');
    url.searchParams.delete('product-page');

    if (page > 1) {
      url.searchParams.set('paged', String(page));
    }

    return url;
  }

  function requestCount(form) {
    if (!ajaxUrl(form) || !window.fetch) {
      return;
    }

    var panel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
    var submit = panel && panel.querySelector('.mt-native-filters-submit');

    if (submit) {
      submit.textContent = labels().loading || 'Skaičiuojama...';
    }

    fetch(ajaxUrl(form), {
      method: 'POST',
      credentials: 'same-origin',
      body: (function () {
        var data = selectedPayload(form);
        data.append('action', 'meditrendy_native_filters_count');
        return data;
      })()
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (response) {

        if (!response || !response.success || !response.data) return;

        resetSubmitText(form);
        updateFilterOptionCounts(form, response.data.optionCounts);
        updatePricePlaceholders(form, response.data.priceRange);
      })
      .catch(function () {
        resetSubmitText(form);
      });
  }

  function scheduleCount(form) {
    window.clearTimeout(countTimers.get(form));
    countTimers.set(form, window.setTimeout(function () {
      requestCount(form);
    }, 180));
  }

  function setup(form) {
    if (form.dataset.nativeFiltersReady === '1') return;

    form.dataset.nativeFiltersReady = '1';

    var trigger = form.querySelector('.mt-native-filters-trigger');
    var close = form.querySelector('.mt-native-filters-close');
    var panel = form.querySelector('.mt-native-filters-panel');

    ensureFormId(form);
    syncStateFromControls(form);
    updateActiveRows(form);
    updateActiveChips(form);

    trigger.addEventListener('click', function () {
      portalPanel(form);
      expandSelectedMobileFilters(form);
      form.classList.add('is-open');
      if (form._mtPanel) {
        form._mtPanel.classList.add('is-open');
      }
      trigger.setAttribute('aria-expanded', 'true');
      document.documentElement.classList.add('mt-native-filters-lock');
      document.body.classList.add('mt-native-filters-lock');
    });

    close.addEventListener('click', function () {
      form.classList.remove('is-open');
      restorePanel(form);
      trigger.setAttribute('aria-expanded', 'false');
      document.documentElement.classList.remove('mt-native-filters-lock');
      document.body.classList.remove('mt-native-filters-lock');
    });

    panel.querySelectorAll('.mt-native-filter-heading').forEach(function (heading) {
      heading.addEventListener('click', function () {
        var filter = heading.closest('.mt-native-filter');
        var isOpen = filter.classList.contains('is-open');

        closeAllExcept(form, filter);

        if (isOpen) {
          restoreDropdown(filter);
        }

        filter.classList.toggle('is-open', !isOpen);
        heading.setAttribute('aria-expanded', String(!isOpen));
        form.classList.toggle('is-layered', !isOpen);

        if (!isOpen) {
          portalDropdown(filter, form);
          positionDropdown(filter);
        }
      });
    });

    panel.querySelectorAll('select, input[type="checkbox"], input[type="number"]').forEach(function (control) {
      control.addEventListener('change', function () {
        setControlState(form, control);
        updateActiveRows(form);
        updateActiveChips(form);
      });
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (isMobile()) {
        if (!form.classList.contains('is-loading')) {
          closeMobilePanel(form);
        }
        return;
      }

      submitForm(form, { closeMobilePanel: true });
    });

    panel.querySelectorAll('.mt-native-filters-submit').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
      });
    });

    document.addEventListener('click', function (event) {
      var chip = event.target.closest('.mt-native-active-filter, .mt-native-filters-reset');

      if (!chip || (!chip.closest('.mt-native-active-filters') && !chip.closest(selector))) return;

      event.preventDefault();

      if (chip.dataset.filtersReset === '1' || chip.classList.contains('mt-native-filters-reset')) {
        clearSelections(form);
        submitForm(form);
        return;
      }

      removeFilterValue(form, chip.dataset.filterName, chip.dataset.filterValue);

      updateActiveRows(form);
      updateActiveChips(form);
      submitForm(form);
    });

    document.addEventListener('click', function (event) {
      var pageLink = event.target.closest('a[href]');

      if (!isPaginationLink(pageLink)) return;

      event.preventDefault();
      syncStateFromControls(form);
      loadFilteredProducts(form, paginationUrlFromCurrentFilters(form, pageLink), { scrollTop: true });
    });

    document.addEventListener('click', function (event) {
      if (containsPortaledPart(form, event.target)) return;

      closeAllExcept(form, null);
    });

    window.addEventListener('resize', function () {
      if (!isMobile()) {
        restorePanel(form);
      }

      var activePanel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
      activePanel.querySelectorAll('.mt-native-filter.is-open').forEach(positionDropdown);
    });

    window.addEventListener('scroll', function () {
      var activePanel = form.querySelector('.mt-native-filters-panel') || form._mtPanel;
      activePanel.querySelectorAll('.mt-native-filter.is-open').forEach(positionDropdown);
    }, true);
  }

  function boot() {
    document.querySelectorAll(selector).forEach(setup);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  document.addEventListener('change', function (event) {
    if (!isFilterControl(event.target)) return;

    var form = formForElement(event.target);

    if (!form) return;


    setControlState(form, event.target);
    updateActiveRows(form);
    updateActiveChips(form);

    submitForm(form);
  });

  document.addEventListener('click', function (event) {
    var submit = event.target.closest('.mt-native-filters-submit');

    if (!submit) return;

    var form = formForElement(submit);

    if (!form) return;

    event.preventDefault();
    if (isMobile()) {
      if (!form.classList.contains('is-loading')) {
        closeMobilePanel(form);
      }
      return;
    }

    submitForm(form, { closeMobilePanel: true });
  });

  window.addEventListener('pageshow', function () {
    document.querySelectorAll(selector).forEach(function (form) {
      clearLoading(form);
    });
  });

  window.addEventListener('popstate', function () {
    var form = document.querySelector(selector);

    if (form) {
      loadFilteredProducts(form, new URL(window.location.href), { replaceHistory: true });
    }
  });
})();
