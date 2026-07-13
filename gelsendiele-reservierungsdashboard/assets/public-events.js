(() => {
  'use strict';

  document.querySelectorAll('[data-gse-filters]').forEach((filters) => {
    const root = filters.closest('.gse-public-events');
    const cards = [...root.querySelectorAll('[data-gse-event]')];
    const statusButtons = [...filters.querySelectorAll('[data-gse-status]')];
    const dateInput = filters.querySelector('[data-gse-date]');
    const dateClear = filters.querySelector('[data-gse-date-clear]');
    const empty = root.querySelector('[data-gse-filter-empty]');
    let status = 'upcoming';

    const applyFilters = () => {
      const date = dateInput?.value || '';
      let visible = 0;
      cards.forEach((card) => {
        const matchesStatus = status === 'all' || card.dataset.status === status;
        const matchesDate = !date || card.dataset.date === date;
        card.hidden = !(matchesStatus && matchesDate);
        if (!card.hidden) visible += 1;
      });
      statusButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.gseStatus === status));
      if (empty) empty.hidden = visible > 0;
    };

    statusButtons.forEach((button) => button.addEventListener('click', () => {
      status = button.dataset.gseStatus || 'upcoming';
      applyFilters();
    }));
    dateInput?.addEventListener('change', applyFilters);
    dateClear?.addEventListener('click', () => {
      if (dateInput) dateInput.value = '';
      applyFilters();
    });
    applyFilters();
  });

  const popup = document.querySelector('[data-gse-popup]');
  if (!popup) return;
  const storageKey = `gse-event-popup-${popup.dataset.eventId || 'event'}`;
  let dismissed = false;
  try { dismissed = window.sessionStorage.getItem(storageKey) === 'dismissed'; } catch (error) {}
  const close = () => {
    popup.hidden = true;
    document.body.classList.remove('gse-popup-open');
    try { window.sessionStorage.setItem(storageKey, 'dismissed'); } catch (error) {}
  };
  if (!dismissed) {
    window.setTimeout(() => {
      popup.hidden = false;
      document.body.classList.add('gse-popup-open');
      popup.querySelector('.gse-event-popup__close')?.focus();
    }, 650);
  }
  popup.querySelectorAll('[data-gse-popup-close]').forEach((button) => button.addEventListener('click', close));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !popup.hidden) close();
  });
})();
