(() => {
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
    document.addEventListener('DOMContentLoaded', schedulePlacement);
  } else {
    schedulePlacement();
  }

  window.addEventListener('load', placeGalleryNavigation, { once: true });

  observer = new MutationObserver(() => {
    schedulePlacement();
    attempts += 1;

    if (attempts > 40 || document.querySelector('.woocommerce-product-gallery .flex-viewport > .flex-direction-nav')) {
      observer.disconnect();
    }
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
  });
})();
