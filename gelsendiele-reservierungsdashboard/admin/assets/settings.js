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

  const availabilityForm = document.querySelector('[data-gelsendiele-availability-form]');
  if (availabilityForm) {
    const list = availabilityForm.querySelector('[data-gelsendiele-availability-list]');
    const template = availabilityForm.querySelector('[data-gelsendiele-rule-template]');
    const add = availabilityForm.querySelector('[data-gelsendiele-add-rule]');
    const empty = availabilityForm.querySelector('[data-gelsendiele-empty-rules]');

    const updateEmpty = () => {
      if (empty && list) empty.hidden = Boolean(list.querySelector('[data-gelsendiele-rule]'));
    };
    const updateRule = (rule) => {
      const type = rule.querySelector('[data-gelsendiele-rule-type]')?.value || 'closed';
      const showTime = ['special_open', 'blocked_time', 'capacity'].includes(type);
      rule.querySelectorAll('[data-gelsendiele-rule-group="time"]').forEach((field) => { field.hidden = !showTime; });
      rule.querySelectorAll('[data-gelsendiele-rule-group="capacity"]').forEach((field) => { field.hidden = type !== 'capacity'; });
    };

    list?.querySelectorAll('[data-gelsendiele-rule]').forEach(updateRule);
    list?.addEventListener('change', (event) => {
      if (!event.target.matches('[data-gelsendiele-rule-type]')) return;
      const rule = event.target.closest('[data-gelsendiele-rule]');
      if (rule) updateRule(rule);
    });
    list?.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-gelsendiele-remove-rule]');
      if (!remove) return;
      remove.closest('[data-gelsendiele-rule]')?.remove();
      updateEmpty();
    });
    add?.addEventListener('click', () => {
      if (!list || !template) return;
      const index = Number.parseInt(list.dataset.nextIndex || '0', 10);
      list.dataset.nextIndex = String(index + 1);
      const wrapper = document.createElement('div');
      wrapper.innerHTML = template.innerHTML.replaceAll('__RULE_INDEX__', String(index));
      const rule = wrapper.querySelector('[data-gelsendiele-rule]');
      if (!rule) return;
      const id = rule.querySelector('[data-gelsendiele-rule-id]');
      if (id) id.value = `rule-${Date.now().toString(36)}-${index}`;
      list.appendChild(rule);
      updateRule(rule);
      updateEmpty();
      rule.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    updateEmpty();
  }

  document.querySelectorAll('[data-gelsensystem-form-field]').forEach((row) => {
    const enabled = row.querySelector('[data-gelsensystem-field-enabled]');
    const required = row.querySelector('[data-gelsensystem-field-required]');
    if (!enabled || !required) return;
    const sync = () => {
      required.disabled = !enabled.checked;
      if (!enabled.checked) required.checked = false;
    };
    enabled.addEventListener('change', sync);
    sync();
  });

  document.querySelectorAll('.gelsensystem-email-template').forEach((template) => {
    const recipient = template.querySelector('[data-gelsensystem-recipient]');
    const custom = recipient?.closest('.gelsensystem-template-grid')?.querySelector('input[type="email"]');
    if (!recipient || !custom) return;
    const sync = () => { custom.closest('label').hidden = recipient.value !== 'custom'; };
    recipient.addEventListener('change', sync);
    sync();
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
