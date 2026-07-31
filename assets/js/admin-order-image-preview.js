document.addEventListener('DOMContentLoaded', () => {
  const modal = document.querySelector('[data-meditrendy-order-image-modal]');

  if (!modal) {
    return;
  }

  const image = modal.querySelector('.meditrendy-order-image-modal__image');
  const caption = modal.querySelector('.meditrendy-order-image-modal__caption');
  const closeButton = modal.querySelector('.meditrendy-order-image-modal__close');
  const itemData = window.meditrendyOrderImagePreview?.items || {};
  let opener = null;

  const enhanceThumbnails = (root = document) => {
    root.querySelectorAll('tr[data-order_item_id] .wc-order-item-thumbnail img').forEach((thumbnailImage) => {
      const row = thumbnailImage.closest('tr[data-order_item_id]');
      const existingTrigger = thumbnailImage.closest('[data-meditrendy-order-image-preview]');
      const preview = row ? itemData[row.dataset.order_item_id] : null;

      if (existingTrigger || !preview?.url) {
        return;
      }

      const thumbnail = thumbnailImage.closest('.wc-order-item-thumbnail');

      if (!thumbnail) {
        return;
      }

      thumbnail.dataset.meditrendyOrderImagePreview = '';
      thumbnail.dataset.imageUrl = preview.url;
      thumbnail.dataset.imageTitle = preview.title || '';
      thumbnail.setAttribute('role', 'button');
      thumbnail.setAttribute('tabindex', '0');
      thumbnail.setAttribute('aria-label', preview.label || preview.title || '');
    });
  };

  const closeModal = () => {
    if (modal.hidden) {
      return;
    }

    modal.hidden = true;
    document.body.classList.remove('meditrendy-order-image-modal-open');
    image.removeAttribute('src');
    image.alt = '';
    caption.textContent = '';

    if (opener && document.contains(opener)) {
      opener.focus();
    }

    opener = null;
  };

  const openModal = (trigger) => {
    const imageUrl = trigger.dataset.imageUrl || trigger.href || '';
    const title = trigger.dataset.imageTitle || '';

    if (!imageUrl) {
      return;
    }

    opener = trigger;
    image.src = imageUrl;
    image.alt = title;
    caption.textContent = title;
    modal.hidden = false;
    document.body.classList.add('meditrendy-order-image-modal-open');
    closeButton.focus();
  };

  document.addEventListener('click', (event) => {
    const previewLink = event.target.closest('[data-meditrendy-order-image-preview]');

    if (previewLink) {
      event.preventDefault();
      openModal(previewLink);
      return;
    }

    if (event.target.closest('[data-meditrendy-order-image-close]')) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    const previewTrigger = event.target.closest('[data-meditrendy-order-image-preview]');

    if (modal.hidden && previewTrigger && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      openModal(previewTrigger);
      return;
    }

    if (modal.hidden) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
    } else if (event.key === 'Tab') {
      event.preventDefault();
      closeButton.focus();
    }
  });

  enhanceThumbnails();

  const orderItems = document.querySelector('.woocommerce_order_items');

  if (orderItems) {
    new MutationObserver(() => enhanceThumbnails(orderItems)).observe(orderItems, {
      childList: true,
      subtree: true,
    });
  }
});
