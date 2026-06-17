(() => {
  const list = document.querySelector('[data-mt-popups-list]');
  const template = document.querySelector('#tmpl-meditrendy-popup-rule');
  const addButton = document.querySelector('[data-mt-popup-add]');

  if (!list || !template || !addButton) return;

  const updateIndexes = () => {
    list.querySelectorAll('[data-mt-popup-rule]').forEach((rule, index) => {
      const title = rule.querySelector('.mt-popup-rule__header h2');

      if (title) {
        title.textContent = `Popup ${index + 1}`;
      }

      rule.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/\[popups]\[[^\]]+]/, `[popups][${index}]`);
      });
    });
  };

  const updateDelayFields = (root = document) => {
    root.querySelectorAll('[data-mt-popup-rule]').forEach((rule) => {
      const select = rule.querySelector('[data-mt-popup-display-rule]');
      const field = rule.querySelector('[data-mt-popup-delay-field]');

      if (!select || !field) return;

      field.hidden = select.value !== 'delay';
    });
  };

  const openMediaPicker = (button) => {
    const field = button.closest('.mt-popup-field');

    if (!field || !window.wp || !wp.media) return;

    const input = field.querySelector('.mt-popup-image-id');
    const preview = field.querySelector('.mt-popup-image-preview');
    const remove = field.querySelector('.mt-popup-remove-image');

    const frame = wp.media({
      title: 'Choose popup graphic',
      button: {
        text: 'Use this image',
      },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      const previewUrl = attachment.sizes?.thumbnail?.url || attachment.url || '';

      if (input) {
        input.value = attachment.id || '';
      }

      if (preview) {
        preview.src = previewUrl;
      }

      if (remove) {
        remove.hidden = false;
      }
    });

    frame.open();
  };

  addButton.addEventListener('click', () => {
    const index = list.querySelectorAll('[data-mt-popup-rule]').length;
    const html = template.innerHTML.replaceAll('__INDEX__', String(index));
    const wrapper = document.createElement('div');

    wrapper.innerHTML = html.trim();
    list.appendChild(wrapper.firstElementChild);
    updateIndexes();
    updateDelayFields(list);
  });

  list.addEventListener('click', (event) => {
    const selectImage = event.target.closest('.mt-popup-select-image');

    if (selectImage) {
      openMediaPicker(selectImage);
      return;
    }

    const removeImage = event.target.closest('.mt-popup-remove-image');

    if (removeImage) {
      const field = removeImage.closest('.mt-popup-field');
      const input = field?.querySelector('.mt-popup-image-id');
      const preview = field?.querySelector('.mt-popup-image-preview');

      if (input) {
        input.value = '';
      }

      if (preview) {
        preview.removeAttribute('src');
      }

      removeImage.hidden = true;
      return;
    }

    const removeRule = event.target.closest('[data-mt-popup-remove]');

    if (removeRule) {
      removeRule.closest('[data-mt-popup-rule]')?.remove();

      if (!list.querySelector('[data-mt-popup-rule]')) {
        addButton.click();
      }

      updateIndexes();
    }
  });

  list.addEventListener('change', (event) => {
    if (event.target.matches('[data-mt-popup-display-rule]')) {
      updateDelayFields(event.target.closest('[data-mt-popup-rule]'));
    }
  });

  updateIndexes();
  updateDelayFields(list);
})();
