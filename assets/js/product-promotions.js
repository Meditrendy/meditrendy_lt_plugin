(function () {
  const copiedLabel = 'Nukopijuota';

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
    const originalLabel = button.textContent;

    copyText(code).then(function () {
      button.textContent = copiedLabel;

      window.setTimeout(function () {
        button.textContent = originalLabel;
      }, 1600);
    });
  });
}());
