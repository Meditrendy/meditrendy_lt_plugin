(function () {
  'use strict';

  if (
    !window.wc ||
    !window.wc.blocksCheckout ||
    typeof window.wc.blocksCheckout.registerCheckoutFilters !== 'function'
  ) {
    return;
  }

  const namespace = 'meditrendy-wpc-bundle-blocks-compat';

  function getBundleData(extensions, args) {
    const cartItem = args && args.cartItem ? args.cartItem : {};
    const extensionData = extensions && extensions.meditrendy_woosb ? extensions.meditrendy_woosb : {};

    return {
      woosb_bundles: !!(cartItem.woosb_bundles || extensionData.woosb_bundles),
      woosb_bundled: !!(cartItem.woosb_bundled || extensionData.woosb_bundled),
      woosb_hide_bundled: !!(cartItem.woosb_hide_bundled || extensionData.woosb_hide_bundled),
      woosb_fixed_price: !!(cartItem.woosb_fixed_price || extensionData.woosb_fixed_price)
    };
  }

  function isCartLineContext(args) {
    return args && (args.context === 'cart' || args.context === 'summary');
  }

  function addClass(className, nextClasses) {
    if (!className || nextClasses.indexOf(className) !== -1) {
      return nextClasses;
    }

    return `${nextClasses} ${className}`;
  }

  function cartItemClass(defaultValue, extensions, args) {
    if (!isCartLineContext(args)) {
      return defaultValue;
    }

    const bundleData = getBundleData(extensions, args);
    let nextValue = defaultValue || '';

    if (bundleData.woosb_bundles) {
      nextValue = addClass('woosb-bundles', nextValue);
    }

    if (bundleData.woosb_bundled) {
      nextValue = addClass('woosb-bundled', nextValue);
    }

    if (bundleData.woosb_hide_bundled) {
      nextValue = addClass('woosb-hide-bundled', nextValue);
    }

    if (bundleData.woosb_fixed_price) {
      nextValue = addClass('woosb-fixed-price', nextValue);
    }

    return nextValue;
  }

  function showRemoveItemLink(defaultValue, extensions, args) {
    if (!args || args.context !== 'cart') {
      return defaultValue;
    }

    return getBundleData(extensions, args).woosb_bundled ? false : defaultValue;
  }

  window.wc.blocksCheckout.registerCheckoutFilters(namespace, {
    cartItemClass,
    showRemoveItemLink
  });
}());
