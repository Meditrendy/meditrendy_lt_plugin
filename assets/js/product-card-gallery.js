(() => {
  const gallerySelector = '[data-mt-product-card-gallery]';
  const viewportSelector = '[data-mt-product-card-gallery-viewport]';

  const updateActiveDot = (gallery) => {
    const viewport = gallery.querySelector(viewportSelector);
    const dots = gallery.querySelectorAll('[data-mt-product-card-gallery-slide]');

    if (!viewport || !dots.length || !viewport.clientWidth) {
      return;
    }

    const activeIndex = Math.max(
      0,
      Math.min(dots.length - 1, Math.round(viewport.scrollLeft / viewport.clientWidth))
    );

    dots.forEach((dot, index) => {
      const isActive = index === activeIndex;

      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-current', isActive ? 'true' : 'false');
    });
  };

  const goToSlide = (gallery, index) => {
    const viewport = gallery.querySelector(viewportSelector);

    if (!viewport) {
      return;
    }

    viewport.scrollTo({
      left: index * viewport.clientWidth,
      behavior: 'smooth',
    });
  };

  document.addEventListener('click', (event) => {
    const control = event.target.closest('[data-mt-product-card-gallery-direction], [data-mt-product-card-gallery-slide]');

    if (!control) {
      return;
    }

    const gallery = control.closest(gallerySelector);
    const viewport = gallery ? gallery.querySelector(viewportSelector) : null;

    if (!gallery || !viewport) {
      return;
    }

    const dots = gallery.querySelectorAll('[data-mt-product-card-gallery-slide]');
    const currentIndex = Math.round(viewport.scrollLeft / viewport.clientWidth);
    let nextIndex = Number.parseInt(control.dataset.mtProductCardGallerySlide, 10);

    if (control.dataset.mtProductCardGalleryDirection) {
      nextIndex = control.dataset.mtProductCardGalleryDirection === 'next'
        ? currentIndex + 1
        : currentIndex - 1;
    }

    if (!Number.isInteger(nextIndex) || !dots.length) {
      return;
    }

    event.preventDefault();
    goToSlide(gallery, (nextIndex + dots.length) % dots.length);
  });

  document.addEventListener('scroll', (event) => {
    const viewport = event.target.closest ? event.target.closest(viewportSelector) : null;

    if (viewport) {
      updateActiveDot(viewport.closest(gallerySelector));
    }
  }, true);
})();
