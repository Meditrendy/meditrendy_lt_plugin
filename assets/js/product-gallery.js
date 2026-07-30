(() => {
  let aiNoticeId = 0;
  const aiNoticeTimers = new WeakMap();

  const clearAiNoticeTimer = (notice) => {
    const timer = aiNoticeTimers.get(notice);

    if (timer) {
      window.clearTimeout(timer);
      aiNoticeTimers.delete(notice);
    }
  };

  const hideAiNotice = (notice, suppressHover = false) => {
    clearAiNoticeTimer(notice);
    notice.classList.remove('is-open');
    notice.classList.toggle('is-auto-hidden', suppressHover);

    const button = notice.querySelector('.mt-product-gallery-ai-notice__button');

    if (button) {
      button.setAttribute('aria-expanded', 'false');
    }
  };

  const scheduleAiNoticeHide = (notice) => {
    clearAiNoticeTimer(notice);

    const timer = window.setTimeout(() => {
      hideAiNotice(notice, true);
    }, 4000);

    aiNoticeTimers.set(notice, timer);
  };

  const closeAiNotices = (exception = null) => {
    document.querySelectorAll('.mt-product-gallery-ai-notice.is-open').forEach((notice) => {
      if (notice === exception) {
        return;
      }

      hideAiNotice(notice);
    });
  };

  const installAiNotices = () => {
    const config = window.MeditrendyProductGallery?.aiNotice;

    if (!config?.text || !config?.label) {
      return;
    }

    document.querySelectorAll('.woocommerce-product-gallery').forEach((gallery) => {
      if (!gallery.querySelector('.woocommerce-product-gallery__image')) {
        return;
      }

      let controls = gallery.querySelector(':scope > .mt-product-gallery-meta');
      let notice = gallery.querySelector('.mt-product-gallery-ai-notice');

      if (!controls) {
        controls = document.createElement('div');
        controls.className = 'mt-product-gallery-meta';
        gallery.appendChild(controls);
      }

      if (!notice) {
        aiNoticeId += 1;

        const tooltipId = `mt-product-gallery-ai-tooltip-${aiNoticeId}`;
        const button = document.createElement('button');
        const tooltip = document.createElement('span');

        notice = document.createElement('div');
        notice.className = 'mt-product-gallery-ai-notice';

        button.type = 'button';
        button.className = 'mt-product-gallery-ai-notice__button';
        button.setAttribute('aria-label', config.label);
        button.setAttribute('aria-describedby', tooltipId);
        button.setAttribute('aria-expanded', 'false');
        button.textContent = 'i';

        tooltip.id = tooltipId;
        tooltip.className = 'mt-product-gallery-ai-notice__tooltip';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.textContent = config.text;

        notice.append(button, tooltip);
        notice.addEventListener('mouseenter', () => {
          notice.classList.remove('is-auto-hidden');
          scheduleAiNoticeHide(notice);
        });
        notice.addEventListener('mouseleave', () => {
          if (!notice.classList.contains('is-open')) {
            clearAiNoticeTimer(notice);
          }

          notice.classList.remove('is-auto-hidden');
        });
      }

      const counter = gallery.querySelector(':scope > .product-gallery-counter');

      if (counter) {
        controls.appendChild(counter);
      }

      controls.appendChild(notice);
    });
  };

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

  let galleryLayoutRefreshTimer = 0;
  const observedGalleries = new WeakSet();

  const refreshGalleryLayout = () => {
    const $ = window.jQuery;

    if (!$ || !$.fn || typeof $.fn.flexslider !== 'function') {
      return;
    }

    placeGalleryNavigation();
    $(window).trigger('resize');
  };

  const scheduleGalleryLayoutRefresh = (delay = 0) => {
    window.clearTimeout(galleryLayoutRefreshTimer);
    galleryLayoutRefreshTimer = window.setTimeout(refreshGalleryLayout, delay);
  };

  const observeGalleryLayout = () => {
    if (typeof ResizeObserver === 'undefined') {
      return;
    }

    document.querySelectorAll('.woocommerce-product-gallery').forEach((gallery) => {
      if (observedGalleries.has(gallery)) {
        return;
      }

      observedGalleries.add(gallery);
      new ResizeObserver(() => scheduleGalleryLayoutRefresh()).observe(gallery);
    });
  };

  let attempts = 0;
  let observer = null;

  const schedulePlacement = () => {
    window.requestAnimationFrame(() => {
      installAiNotices();
      placeGalleryNavigation();
      observeGalleryLayout();
      scheduleGalleryLayoutRefresh(0);
    });
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
    schedulePlacement();
    window.setTimeout(scheduleGalleryLayoutRefresh, 100);
    window.setTimeout(scheduleGalleryLayoutRefresh, 500);
    window.setTimeout(scheduleGalleryLayoutRefresh, 1200);
    scheduleBundleVariationGalleryGuard();
  }, { once: true });

  document.addEventListener('load', (event) => {
    if (event.target.matches('.woocommerce-product-gallery img')) {
      scheduleGalleryLayoutRefresh(0);
    }
  }, true);

  document.addEventListener('click', (event) => {
    const aiNoticeButton = event.target.closest('.mt-product-gallery-ai-notice__button');

    if (aiNoticeButton) {
      const notice = aiNoticeButton.closest('.mt-product-gallery-ai-notice');
      const willOpen = !notice.classList.contains('is-open');

      event.preventDefault();
      event.stopPropagation();
      closeAiNotices(notice);
      notice.classList.remove('is-auto-hidden');
      notice.classList.toggle('is-open', willOpen);
      aiNoticeButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

      if (willOpen) {
        scheduleAiNoticeHide(notice);
      } else {
        clearAiNoticeTimer(notice);
      }

      return;
    }

    closeAiNotices();

    if (event.target.closest('.pswp__button--close, .pswp__bg, .pswp__scroll-wrap')) {
      window.setTimeout(closeMeditrendyViewers, 0);
      window.setTimeout(closeMeditrendyViewers, 250);
    }
  }, true);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAiNotices();
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
