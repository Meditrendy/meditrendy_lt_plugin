(() => {
  const banner = document.querySelector('[data-mt-marketing-banner]');

  if (!banner) return;

  const formatCountdown = (seconds) => {
    const safeSeconds = Math.max(0, seconds);
    const days = Math.floor(safeSeconds / 86400);
    const hours = Math.floor((safeSeconds % 86400) / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const remainingSeconds = safeSeconds % 60;

    if (days > 0) {
      return `${days} d. ${String(hours).padStart(2, '0')} val. ${String(minutes).padStart(2, '0')} min. ${String(remainingSeconds).padStart(2, '0')} sek.`;
    }

    if (hours > 0) {
      return `${String(hours).padStart(2, '0')} val. ${String(minutes).padStart(2, '0')} min. ${String(remainingSeconds).padStart(2, '0')} sek.`;
    }

    return `${String(minutes).padStart(2, '0')} min. ${String(remainingSeconds).padStart(2, '0')} sek.`;
  };

  const updateCountdowns = () => {
    banner.querySelectorAll('[data-mt-marketing-countdown]').forEach((node) => {
      const endsAt = Number.parseInt(node.getAttribute('data-mt-marketing-countdown'), 10);
      const seconds = Math.max(0, endsAt - Math.floor(Date.now() / 1000));

      node.textContent = formatCountdown(seconds);

      if (seconds <= 0) {
        banner.remove();
      }
    });
  };

  banner.addEventListener('click', async (event) => {
    const closeButton = event.target.closest('[data-mt-marketing-close]');

    if (closeButton) {
      banner.remove();
      return;
    }

    const copyButton = event.target.closest('[data-mt-marketing-copy]');

    if (!copyButton) return;

    const code = copyButton.getAttribute('data-mt-marketing-copy') || '';
    const copiedLabel = copyButton.getAttribute('data-copied-label') || copyButton.textContent;
    const copyLabel = copyButton.getAttribute('data-copy-label') || copyButton.textContent;

    try {
      await navigator.clipboard.writeText(code);
      copyButton.textContent = copiedLabel;
      copyButton.classList.add('is-copied');

      window.setTimeout(() => {
        copyButton.textContent = copyLabel;
        copyButton.classList.remove('is-copied');
      }, 1800);
    } catch (error) {
      const fallback = document.createElement('textarea');
      fallback.value = code;
      fallback.setAttribute('readonly', '');
      fallback.style.position = 'fixed';
      fallback.style.top = '-9999px';
      document.body.appendChild(fallback);
      fallback.select();
      document.execCommand('copy');
      fallback.remove();
    }
  });

  updateCountdowns();
  window.setInterval(updateCountdowns, 1000);
})();
