(() => {
  'use strict';

  document.querySelectorAll('[data-gelsendiele-day]').forEach((day) => {
    const blocks = day.querySelector('[data-gelsendiele-blocks]');
    const template = day.querySelector('[data-gelsendiele-block-template]');
    const add = day.querySelector('[data-gelsendiele-add-block]');
    if (!blocks || !template || !add) return;

    add.addEventListener('click', () => {
      const nextIndex = blocks.querySelectorAll('.gelsendiele-time-block').length;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
      const block = wrapper.firstElementChild;
      if (block) blocks.appendChild(block);
    });

    day.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-gelsendiele-remove-block]');
      if (!remove) return;
      remove.closest('.gelsendiele-time-block')?.remove();
    });
  });

  const selectLogo = document.querySelector('[data-gelsendiele-select-logo]');
  const logoUrl = document.querySelector('[data-gelsendiele-logo-url]');
  const logoId = document.querySelector('[data-gelsendiele-logo-id]');
  const preview = document.querySelector('[data-gelsendiele-logo-preview]');
  selectLogo?.addEventListener('click', () => {
    if (!window.wp?.media) return;
    const frame = window.wp.media({ title: 'Logo auswählen', button: { text: 'Logo verwenden' }, multiple: false, library: { type: 'image' } });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first()?.toJSON();
      if (!attachment) return;
      if (logoId) logoId.value = String(attachment.id || 0);
      if (logoUrl) logoUrl.value = attachment.url || '';
      if (preview) {
        preview.replaceChildren();
        const image = document.createElement('img');
        image.src = attachment.url || '';
        image.alt = 'Logo-Vorschau';
        preview.appendChild(image);
      }
    });
    frame.open();
  });
})();
