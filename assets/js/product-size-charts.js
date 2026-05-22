(() => {
  let activeModal = null;
  let previousFocus = null;
  let activeDialog = null;

  function updateScrollState(dialog) {
    if (!dialog) {
      return;
    }

    const maxScroll = dialog.scrollHeight - dialog.clientHeight;
    const scrollTop = dialog.scrollTop;

    dialog.classList.toggle('mt-product-size-chart-scrollable', maxScroll > 2);
    dialog.classList.toggle('mt-product-size-chart-can-scroll-up', scrollTop > 2);
    dialog.classList.toggle('mt-product-size-chart-can-scroll-down', scrollTop < maxScroll - 2);
  }

  function updateContentScrollState(content) {
    if (!content) {
      return;
    }

    const maxScroll = content.scrollWidth - content.clientWidth;
    const scrollLeft = Math.abs(content.scrollLeft);

    content.classList.toggle('mt-product-size-chart-content-scrollable', maxScroll > 2);
    content.classList.toggle('mt-product-size-chart-can-scroll-left', scrollLeft > 2);
    content.classList.toggle('mt-product-size-chart-can-scroll-right', scrollLeft < maxScroll - 2);
  }

  function bindDialogScroll(dialog) {
    if (!dialog || dialog.dataset.mtSizeChartScrollBound) {
      return;
    }

    dialog.dataset.mtSizeChartScrollBound = '1';
    dialog.addEventListener('scroll', () => updateScrollState(dialog), { passive: true });
  }

  function bindContentScroll(content) {
    if (!content || content.dataset.mtSizeChartScrollBound) {
      return;
    }

    content.dataset.mtSizeChartScrollBound = '1';
    content.addEventListener('scroll', () => updateContentScrollState(content), { passive: true });
  }

  function openModal(modal) {
    if (!modal) {
      return;
    }

    previousFocus = document.activeElement;
    activeModal = modal;
    modal.hidden = false;
    document.documentElement.classList.add('mt-product-size-chart-is-open');

    const dialog = modal.querySelector('.mt-product-size-chart-dialog');

    if (dialog) {
      const content = dialog.querySelector('[data-mt-size-chart-content]');
      activeDialog = dialog;
      bindDialogScroll(dialog);
      bindContentScroll(content);
      dialog.focus({ preventScroll: true });
      window.requestAnimationFrame(() => {
        updateScrollState(dialog);
        updateContentScrollState(content);
      });
    }
  }

  function closeModal() {
    if (!activeModal) {
      return;
    }

    activeModal.hidden = true;
    activeModal = null;
    activeDialog = null;
    document.documentElement.classList.remove('mt-product-size-chart-is-open');

    if (previousFocus && typeof previousFocus.focus === 'function') {
      previousFocus.focus({ preventScroll: true });
    }
  }

  document.addEventListener('click', (event) => {
    const openButton = event.target.closest('[data-mt-size-chart-open]');

    if (openButton) {
      const targetId = openButton.getAttribute('aria-controls');
      openModal(targetId ? document.getElementById(targetId) : null);
      return;
    }

    if (event.target.closest('[data-mt-size-chart-close]')) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal();
    }
  });

  window.addEventListener('resize', () => {
    updateScrollState(activeDialog);
    updateContentScrollState(activeDialog ? activeDialog.querySelector('[data-mt-size-chart-content]') : null);
  }, { passive: true });
})();
