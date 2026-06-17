const meditrendyInitConfigurablePopup = () => {
  const popup = document.querySelector('[data-mt-configurable-popup]');

  if (!popup) return;

  const popupId = popup.getAttribute('data-popup-id') || 'default';
  const seenKey = `meditrendy_popup_seen_${popupId}`;

  try {
    if (window.sessionStorage.getItem(seenKey) === '1') {
      popup.remove();
      return;
    }
  } catch (error) {
    // Some privacy modes can block storage; the popup should still work.
  }

  let shown = false;
  let closed = false;

  const showPopup = () => {
    if (shown || closed) return;

    shown = true;

    try {
      window.sessionStorage.setItem(seenKey, '1');
    } catch (error) {
      // Storage is best-effort only.
    }

    popup.hidden = false;
    document.documentElement.classList.add('mt-configurable-popup-open');

    window.requestAnimationFrame(() => {
      popup.classList.add('is-visible');
    });
  };

  const closePopup = () => {
    if (closed) return;

    closed = true;
    popup.classList.remove('is-visible');
    document.documentElement.classList.remove('mt-configurable-popup-open');

    window.setTimeout(() => {
      popup.hidden = true;
    }, 180);
  };

  popup.addEventListener('click', (event) => {
    if (event.target.closest('[data-mt-popup-close]')) {
      closePopup();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !popup.hidden) {
      closePopup();
    }
  });

  const displayRule = popup.getAttribute('data-display-rule') || 'delay';

  if (displayRule === 'immediate') {
    showPopup();
    return;
  }

  if (displayRule === 'scroll') {
    window.addEventListener('scroll', showPopup, { once: true, passive: true });
    window.addEventListener('wheel', showPopup, { once: true, passive: true });
    window.addEventListener('touchmove', showPopup, { once: true, passive: true });
    return;
  }

  const delaySeconds = Number.parseInt(popup.getAttribute('data-delay-seconds') || '0', 10);

  window.setTimeout(showPopup, Math.max(0, delaySeconds) * 1000);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', meditrendyInitConfigurablePopup, { once: true });
} else {
  meditrendyInitConfigurablePopup();
}
