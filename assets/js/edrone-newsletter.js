(function () {
  function setMessage(form, message, isError) {
    const messageEl = form.querySelector('[data-mt-edrone-newsletter-message]');

    if (!messageEl) {
      return;
    }

    messageEl.textContent = message || '';
    messageEl.classList.toggle('is-error', !!isError);
    messageEl.classList.toggle('is-success', !isError && !!message);
  }

  function setLoading(form, isLoading) {
    form.classList.toggle('is-loading', isLoading);
    form.querySelectorAll('input, button').forEach((field) => {
      field.disabled = isLoading;
    });
  }

  function handleSubmit(event) {
    const form = event.currentTarget;
    const formData = new FormData(form);
    const settings = window.MeditrendyEdroneNewsletter || {};

    event.preventDefault();
    setMessage(form, '', false);
    setLoading(form, true);

    fetch(settings.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    })
      .then((response) => response.json())
      .then((response) => {
        const message = response && response.data && response.data.message
          ? response.data.message
          : 'Nepavyko užsiprenumeruoti. Pabandykite dar kartą.';

        if (!response || !response.success) {
          setMessage(form, message, true);
          return;
        }

        form.reset();
        setMessage(form, message, false);
      })
      .catch(() => {
        setMessage(form, 'Nepavyko užsiprenumeruoti. Pabandykite dar kartą.', true);
      })
      .finally(() => {
        setLoading(form, false);
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-mt-edrone-newsletter]').forEach((form) => {
      form.addEventListener('submit', handleSubmit);
    });
  });
}());
