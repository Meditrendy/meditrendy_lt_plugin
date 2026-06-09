(function () {
  function formatCountdown(seconds) {
    const safeSeconds = Math.max(0, seconds);
    const days = Math.floor(safeSeconds / 86400);
    const hours = Math.floor((safeSeconds % 86400) / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainingSeconds = safeSeconds % 60;

    if (days > 0) {
      return days + ' d. ' + String(hours).padStart(2, '0') + ' val. ' + String(minutes).padStart(2, '0') + ' min.';
    }

    if (hours > 0) {
      return String(hours).padStart(2, '0') + ' val. ' + String(minutes).padStart(2, '0') + ' min.';
    }

    return String(minutes).padStart(2, '0') + ' min. ' + String(remainingSeconds).padStart(2, '0') + ' sek.';
  }

  function updateCountdowns() {
    document.querySelectorAll('[data-mt-promo-countdown]').forEach(function (element) {
      const timestamp = parseInt(element.getAttribute('data-mt-promo-countdown'), 10);

      if (!timestamp) {
        return;
      }

      const seconds = Math.max(0, timestamp - Math.floor(Date.now() / 1000));
      element.textContent = formatCountdown(seconds);
    });
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }

    const input = document.createElement('input');
    input.value = text;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.top = '-9999px';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();

    return Promise.resolve();
  }

  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-mt-copy-coupon]');

    if (!button) {
      return;
    }

    const code = button.getAttribute('data-mt-copy-coupon');
    const originalLabel = button.getAttribute('data-mt-copy-label') || 'Kopijuoti kodą';
    const copiedLabel = button.getAttribute('data-mt-copied-label') || 'Nukopijuota';

    copyText(code).then(function () {
      button.classList.add('is-copied');
      button.setAttribute('aria-label', copiedLabel);

      window.setTimeout(function () {
        button.classList.remove('is-copied');
        button.setAttribute('aria-label', originalLabel);
      }, 1600);
    });
  });

  updateCountdowns();
  window.setInterval(updateCountdowns, 1000);
}());
