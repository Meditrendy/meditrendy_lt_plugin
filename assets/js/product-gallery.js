(() => {
  const installBundleVariationGalleryGuard = () => {
    const $ = window.jQuery;

    if (!$ || !$.fn || $.fn.meditrendyBundleGalleryGuardInstalled) {
      return false;
    }

    const originalImageUpdate = $.fn.wc_variations_image_update;
    const originalImageReset = $.fn.wc_variations_image_reset;

    if (typeof originalImageUpdate !== 'function' || typeof originalImageReset !== 'function') {
      return false;
    }

    const isBundleVariationForm = (collection) => (
      collection
      && typeof collection.filter === 'function'
      && collection.filter('form.woosb_variations_form').length > 0
    );

    $.fn.wc_variations_image_update = function (...args) {
      if (isBundleVariationForm(this)) {
        return this;
      }

      return originalImageUpdate.apply(this, args);
    };

    $.fn.wc_variations_image_reset = function (...args) {
      if (isBundleVariationForm(this)) {
        return this;
      }

      return originalImageReset.apply(this, args);
    };

    $.fn.meditrendyBundleGalleryGuardInstalled = true;

    return true;
  };

  const scheduleBundleVariationGalleryGuard = () => {
    if (installBundleVariationGalleryGuard()) {
      return;
    }

    window.setTimeout(installBundleVariationGalleryGuard, 100);
    window.setTimeout(installBundleVariationGalleryGuard, 500);
    window.setTimeout(installBundleVariationGalleryGuard, 1500);
  };

  const closeMeditrendyViewers = () => {
    document.querySelectorAll('.medviewer').forEach((viewer) => {
      viewer.classList.remove('open');
      viewer.setAttribute('aria-hidden', 'true');
      viewer.remove();
    });
  };

  const isPhotoSwipeOpen = () => {
    return Boolean(document.querySelector('.pswp[aria-hidden="false"], .pswp--open'));
  };

  const closeStackedLightboxes = () => {
    if (isPhotoSwipeOpen()) {
      closeMeditrendyViewers();
    }
  };

  const placeGalleryNavigation = () => {
    document.querySelectorAll('.woocommerce-product-gallery').forEach((gallery) => {
      const viewport = gallery.querySelector('.flex-viewport');
      const nav = gallery.querySelector(':scope > .flex-direction-nav, :scope .flex-direction-nav');

      if (!viewport || !nav || nav.parentElement === viewport) {
        return;
      }

      viewport.appendChild(nav);
      gallery.classList.add('mt-product-gallery-nav-ready');
    });
  };

  let attempts = 0;
  let observer = null;

  const schedulePlacement = () => {
    window.requestAnimationFrame(placeGalleryNavigation);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      schedulePlacement();
      scheduleBundleVariationGalleryGuard();
    });
  } else {
    schedulePlacement();
    scheduleBundleVariationGalleryGuard();
  }

  window.addEventListener('load', () => {
    placeGalleryNavigation();
    scheduleBundleVariationGalleryGuard();
  }, { once: true });

  document.addEventListener('click', (event) => {
    if (event.target.closest('.pswp__button--close, .pswp__bg, .pswp__scroll-wrap')) {
      window.setTimeout(closeMeditrendyViewers, 0);
      window.setTimeout(closeMeditrendyViewers, 250);
    }
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMeditrendyViewers();
    }
  });

  observer = new MutationObserver(() => {
    schedulePlacement();
    closeStackedLightboxes();
    attempts += 1;

    if (attempts > 40 || document.querySelector('.woocommerce-product-gallery .flex-viewport > .flex-direction-nav')) {
      observer.disconnect();
    }
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
  });

  new MutationObserver(closeStackedLightboxes).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['aria-hidden', 'class'],
    childList: true,
    subtree: true,
  });
})();
