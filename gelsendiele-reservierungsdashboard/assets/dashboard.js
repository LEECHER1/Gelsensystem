(() => {
  'use strict';

  const loginForm = document.getElementById('gd-loginform');
  if (loginForm && typeof GDReservations !== 'undefined') {
    const loginStatus = document.getElementById('gd-login-status');
    const loginButton = loginForm.querySelector('.gd-login-submit');

    loginForm.addEventListener('submit', async event => {
      event.preventDefault();
      const username = loginForm.querySelector('[name="log"]')?.value.trim() || '';
      const password = loginForm.querySelector('[name="pwd"]')?.value || '';
      const remember = loginForm.querySelector('[name="rememberme"]')?.checked ? '1' : '';

      if (!username || !password) {
        if (loginStatus) loginStatus.textContent = 'Bitte Benutzername und Passwort eingeben.';
        return;
      }

      if (loginButton) {
        loginButton.disabled = true;
        loginButton.textContent = 'Anmeldung läuft …';
      }
      if (loginStatus) {
        loginStatus.classList.remove('is-error');
        loginStatus.textContent = '';
      }

      try {
        const response = await fetch(GDReservations.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: new URLSearchParams({
            action: 'gd_dashboard_login',
            nonce: GDReservations.loginNonce || '',
            username,
            password,
            remember
          })
        });
        const result = await response.json();
        if (!result.success) throw new Error(result?.data?.message || 'Anmeldung fehlgeschlagen.');
        window.location.replace(result?.data?.redirect || GDReservations.dashboardUrl || window.location.href);
      } catch (error) {
        if (loginStatus) {
          loginStatus.classList.add('is-error');
          loginStatus.textContent = error.message || 'Anmeldung fehlgeschlagen.';
        }
        if (loginButton) {
          loginButton.disabled = false;
          loginButton.textContent = 'Anmelden';
        }
      }
    });
  }

  const centralSectionShell = document.querySelector('.gd-dashboard-shell[data-app-section]:not([data-default-view])');
  if (centralSectionShell) {
    const centralSystemDarkMode = window.matchMedia('(prefers-color-scheme: dark)');
    const centralThemeButtons = [...centralSectionShell.querySelectorAll('[data-theme-button]')];
    let centralTheme = '';
    try { centralTheme = window.localStorage.getItem('gd-dashboard-theme') || ''; } catch (error) {}
    if (centralTheme !== 'dark' && centralTheme !== 'light') {
      centralTheme = centralSystemDarkMode.matches ? 'dark' : 'light';
    }
    const applyCentralTheme = (theme, persist = false) => {
      centralTheme = theme === 'dark' ? 'dark' : 'light';
      document.documentElement.dataset.gdTheme = centralTheme;
      document.documentElement.classList.add('gd-dashboard-runtime');
      centralThemeButtons.forEach((button) => {
        button.setAttribute('aria-pressed', centralTheme === 'dark' ? 'true' : 'false');
        button.setAttribute('aria-label', centralTheme === 'dark' ? 'Helle Darstellung aktivieren' : 'Dark Mode aktivieren');
        button.setAttribute('title', centralTheme === 'dark' ? 'Helle Darstellung aktivieren' : 'Dark Mode aktivieren');
      });
      const centralThemeColor = document.getElementById('gd-theme-color');
      if (centralThemeColor) centralThemeColor.content = centralTheme === 'dark' ? '#111713' : '#315b2d';
      if (persist) {
        try { window.localStorage.setItem('gd-dashboard-theme', centralTheme); } catch (error) {}
      }
    };
    applyCentralTheme(centralTheme);
    centralThemeButtons.forEach((button) => button.addEventListener('click', () => {
      applyCentralTheme(centralTheme === 'dark' ? 'light' : 'dark', true);
    }));
    centralSystemDarkMode.addEventListener?.('change', (event) => {
      let savedTheme = '';
      try { savedTheme = window.localStorage.getItem('gd-dashboard-theme') || ''; } catch (error) {}
      if (savedTheme !== 'dark' && savedTheme !== 'light') applyCentralTheme(event.matches ? 'dark' : 'light');
    });
  }

  const menuManager = document.querySelector('.gelsensystem-menu-manager');
  if (menuManager) {
    const categorySelect = menuManager.querySelector('[data-gdg-category-select]');
    const categoryValue = categorySelect?.querySelector('[data-gdg-category-value]');
    const categoryLabel = categorySelect?.querySelector('[data-gdg-category-label]');
    const categoryToggle = categorySelect?.querySelector('[data-gdg-category-toggle]');
    const categoryMenu = categorySelect?.querySelector('[data-gdg-category-menu]');
    const categoryDialog = menuManager.querySelector('[data-gdg-category-dialog]');
    const categoryName = categoryDialog?.querySelector('[data-gdg-category-name]');
    const categoryOrder = categoryDialog?.querySelector('[data-gdg-category-order]');
    const categoryActive = categoryDialog?.querySelector('[data-gdg-category-active]');
    const categoryStatus = categoryDialog?.querySelector('[data-gdg-category-status]');
    const categorySave = categoryDialog?.querySelector('[data-gdg-category-save]');
    const itemForm = menuManager.querySelector('.gelsensystem-menu-item-form');
    let categoryDialogReturnFocus = null;

    const setCategoryMenuOpen = (open) => {
      if (!categoryMenu || !categoryToggle) return;
      categoryMenu.hidden = !open;
      categoryToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      categorySelect?.classList.toggle('is-open', open);
    };

    const selectCategory = (id, name) => {
      if (categoryValue) categoryValue.value = String(id || '');
      if (categoryLabel) categoryLabel.textContent = name || 'Bitte wählen';
      categoryMenu?.querySelectorAll('[data-gdg-category-option]').forEach((option) => {
        option.setAttribute('aria-selected', option.dataset.categoryId === String(id) ? 'true' : 'false');
      });
      setCategoryMenuOpen(false);
    };

    const setCategoryDialogOpen = (open) => {
      if (!categoryDialog) return;
      categoryDialog.classList.toggle('is-open', open);
      categoryDialog.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.classList.toggle('gd-dialog-open', open);
      if (open) {
        categoryDialogReturnFocus = document.activeElement;
        if (categoryStatus) categoryStatus.textContent = '';
        window.setTimeout(() => categoryName?.focus(), 30);
      } else {
        categoryDialogReturnFocus?.focus?.();
      }
    };

    categoryToggle?.addEventListener('click', () => setCategoryMenuOpen(categoryMenu?.hidden !== false));
    categoryMenu?.addEventListener('click', (event) => {
      const option = event.target.closest('[data-gdg-category-option]');
      if (option) selectCategory(option.dataset.categoryId, option.dataset.categoryName || option.textContent.trim());
      if (event.target.closest('[data-gdg-category-add]')) {
        setCategoryMenuOpen(false);
        setCategoryDialogOpen(true);
      }
    });
    categoryDialog?.querySelectorAll('[data-gdg-category-close]').forEach((button) => button.addEventListener('click', () => setCategoryDialogOpen(false)));

    categorySave?.addEventListener('click', async () => {
      const name = categoryName?.value.trim() || '';
      if (!name) {
        if (categoryStatus) categoryStatus.textContent = 'Bitte einen Kategorienamen eingeben.';
        categoryName?.focus();
        return;
      }
      categorySave.disabled = true;
      categorySave.textContent = 'Wird erstellt …';
      if (categoryStatus) categoryStatus.textContent = '';
      try {
        const response = await fetch(GDReservations.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: new URLSearchParams({
            action: 'gdg_save_menu_category',
            nonce: GDReservations.menuNonce || '',
            name,
            sort_order: categoryOrder?.value || '0',
            active: categoryActive?.checked ? '1' : ''
          })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result?.data?.message || 'Die Kategorie konnte nicht erstellt werden.');
        const data = result.data || {};
        const option = document.createElement('button');
        option.type = 'button';
        option.setAttribute('role', 'option');
        option.setAttribute('data-gdg-category-option', '');
        option.dataset.categoryId = String(data.id);
        option.dataset.categoryName = String(data.name);
        option.setAttribute('aria-selected', 'false');
        option.textContent = String(data.name);
        categoryMenu?.querySelector('[data-gdg-category-add]')?.before(option);
        selectCategory(data.id, data.name);
        itemForm?.querySelector('[data-gdg-item-submit]')?.removeAttribute('disabled');
        itemForm?.querySelector('[data-gdg-category-hint]')?.remove();
        if (categoryName) categoryName.value = '';
        if (categoryOrder) categoryOrder.value = String((Number.parseInt(categoryOrder.value || '0', 10) || 0) + 10);
        setCategoryDialogOpen(false);
      } catch (error) {
        if (categoryStatus) categoryStatus.textContent = error.message || 'Die Kategorie konnte nicht erstellt werden.';
      } finally {
        categorySave.disabled = false;
        categorySave.textContent = 'Kategorie erstellen';
      }
    });

    itemForm?.addEventListener('submit', (event) => {
      if (!categoryValue?.value) {
        event.preventDefault();
        setCategoryMenuOpen(true);
        categoryToggle?.focus();
      }
    });
    menuManager.querySelectorAll('[data-gdg-delete-form]').forEach((form) => form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Diesen Eintrag wirklich löschen?')) event.preventDefault();
    }));
    document.addEventListener('click', (event) => {
      if (categorySelect && !categorySelect.contains(event.target)) setCategoryMenuOpen(false);
    });
  }

  const sidebar = document.querySelector('.gelsensystem-sidebar');
  const sidebarToggle = sidebar?.querySelector('[data-sidebar-toggle]');
  const desktopSidebar = window.matchMedia('(min-width: 1025px)');

  function applySidebarState(collapsed, persist = false) {
    const isCollapsed = Boolean(collapsed && desktopSidebar.matches);
    document.body.classList.toggle('gd-sidebar-collapsed', isCollapsed);
    if (sidebarToggle) {
      sidebarToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      sidebarToggle.setAttribute('aria-label', isCollapsed ? 'Seitenleiste ausklappen' : 'Seitenleiste einklappen');
      sidebarToggle.setAttribute('title', isCollapsed ? 'Seitenleiste ausklappen' : 'Seitenleiste einklappen');
    }
    if (persist) {
      try { window.localStorage.setItem('gd-sidebar-collapsed', isCollapsed ? '1' : '0'); } catch (error) {}
    }
  }

  if (sidebar && sidebarToggle) {
    let sidebarCollapsed = false;
    try { sidebarCollapsed = window.localStorage.getItem('gd-sidebar-collapsed') === '1'; } catch (error) {}
    applySidebarState(sidebarCollapsed);
    sidebarToggle.addEventListener('click', () => {
      sidebarCollapsed = !document.body.classList.contains('gd-sidebar-collapsed');
      applySidebarState(sidebarCollapsed, true);
    });
    desktopSidebar.addEventListener?.('change', () => applySidebarState(sidebarCollapsed));
  }

  const appDrawer = document.getElementById('gelsensystem-app-drawer');
  const appDrawerToggle = document.querySelector('[data-gd-app-drawer-toggle]');
  const appDrawerMobile = window.matchMedia('(max-width: 1024px)');
  if (appDrawer && appDrawerToggle) {
    const setAppDrawerOpen = (open) => {
      const isOpen = Boolean(open && appDrawerMobile.matches);
      document.body.classList.toggle('gd-app-drawer-open', isOpen);
      appDrawerToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      appDrawerToggle.setAttribute('aria-label', isOpen ? 'Bereiche schließen' : 'Bereiche öffnen');
      if (appDrawerMobile.matches) appDrawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
      else appDrawer.removeAttribute('aria-hidden');
    };

    setAppDrawerOpen(false);
    appDrawerToggle.addEventListener('click', () => setAppDrawerOpen(!document.body.classList.contains('gd-app-drawer-open')));
    document.querySelectorAll('[data-gd-app-drawer-close]').forEach((button) => button.addEventListener('click', () => setAppDrawerOpen(false)));
    appDrawer.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setAppDrawerOpen(false)));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && document.body.classList.contains('gd-app-drawer-open')) setAppDrawerOpen(false);
    });
    appDrawerMobile.addEventListener?.('change', () => setAppDrawerOpen(false));
  }

  document.querySelectorAll('.gelsensystem-events-form').forEach((form) => {
    const eventStart = form.querySelector('[data-gse-event-start]');
    const eventEnd = form.querySelector('[data-gse-event-end]');
    const allDayInput = form.querySelector('[data-gse-all-day]');
    const eventTimeInputs = [...form.querySelectorAll('[data-gse-event-time]')];
    const popupEnabled = form.querySelector('[data-gse-popup-enabled]');
    const popupStart = form.querySelector('[data-gse-popup-start]');
    const popupEnd = form.querySelector('[data-gse-popup-end]');
    const popupSchedule = form.querySelector('[data-gse-popup-schedule]');
    const mediaOpenButton = form.querySelector('[data-gse-media-open]');
    const imageIdsInput = form.querySelector('[data-gse-image-ids]');
    const imagePreview = form.querySelector('[data-gse-image-preview]');
    const pagePickerToggle = form.querySelector('[data-gse-page-picker-toggle]');
    const pagePicker = form.querySelector('[data-gse-page-picker]');
    const linkInput = form.querySelector('[data-gse-link-input]');
    const previousDay = (dateValue) => {
      if (!dateValue) return '';
      const date = new Date(`${dateValue}T12:00:00Z`);
      date.setUTCDate(date.getUTCDate() - 1);
      return date.toISOString().slice(0, 10);
    };
    const syncPopupDates = () => {
      if (popupStart?.dataset.auto === '1' && eventStart?.value) popupStart.value = previousDay(eventStart.value);
      if (popupEnd?.dataset.auto === '1') popupEnd.value = eventEnd?.value || eventStart?.value || '';
    };
    const syncEventDates = () => {
      if (eventEnd?.dataset.auto === '1' && eventStart?.value) eventEnd.value = eventStart.value;
    };
    const applyAllDayState = () => {
      const allDay = Boolean(allDayInput?.checked);
      eventTimeInputs.forEach((input) => {
        input.disabled = allDay;
        input.setAttribute('aria-disabled', allDay ? 'true' : 'false');
      });
    };
    const applyPopupState = () => {
      const enabled = Boolean(popupEnabled?.checked);
      if (popupSchedule) popupSchedule.hidden = !enabled;
      popupSchedule?.classList.toggle('is-active', enabled);
      if (popupStart) popupStart.required = enabled;
      if (popupEnd) popupEnd.required = enabled;
      if (enabled) syncPopupDates();
    };
    popupStart?.addEventListener('input', () => { popupStart.dataset.auto = '0'; });
    popupEnd?.addEventListener('input', () => { popupEnd.dataset.auto = '0'; });
    eventEnd?.addEventListener('input', () => { eventEnd.dataset.auto = '0'; });
    eventStart?.addEventListener('change', () => {
      syncEventDates();
      syncPopupDates();
    });
    eventEnd?.addEventListener('change', syncPopupDates);
    allDayInput?.addEventListener('change', applyAllDayState);
    popupEnabled?.addEventListener('change', applyPopupState);
    syncEventDates();
    applyAllDayState();
    applyPopupState();

    const selectedImageIds = () => (imageIdsInput?.value || '')
      .split(',')
      .map((value) => Number(value))
      .filter((value, index, values) => value > 0 && values.indexOf(value) === index)
      .slice(0, 12);
    const refreshImageLabels = () => {
      const cards = imagePreview ? [...imagePreview.querySelectorAll('[data-gse-image-id]')] : [];
      cards.forEach((card, index) => {
        const label = card.querySelector('div>span');
        if (label) label.textContent = index === 0 ? 'Titelbild' : 'Eventfoto';
      });
      if (imagePreview) imagePreview.hidden = cards.length === 0;
    };
    const setSelectedImages = (attachments) => {
      if (!imageIdsInput || !imagePreview) return;
      const images = attachments.filter((attachment) => attachment?.id).slice(0, 12);
      imageIdsInput.value = images.map((attachment) => attachment.id).join(',');
      imagePreview.replaceChildren();
      images.forEach((attachment, index) => {
        const card = document.createElement('article');
        card.dataset.gseImageId = String(attachment.id);
        const image = document.createElement('img');
        image.src = attachment.thumbnail || attachment.sizes?.medium?.url || attachment.sizes?.thumbnail?.url || attachment.url || '';
        image.alt = attachment.alt || attachment.title || '';
        const controls = document.createElement('div');
        const label = document.createElement('span');
        label.textContent = index === 0 ? 'Titelbild' : 'Eventfoto';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.gseImageRemove = '';
        remove.textContent = 'Entfernen';
        controls.append(label, remove);
        card.append(image, controls);
        imagePreview.append(card);
      });
      refreshImageLabels();
    };
    imagePreview?.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-gse-image-remove]');
      if (!remove || !imageIdsInput) return;
      const card = remove.closest('[data-gse-image-id]');
      const removeId = Number(card?.dataset.gseImageId || 0);
      imageIdsInput.value = selectedImageIds().filter((id) => id !== removeId).join(',');
      card?.remove();
      refreshImageLabels();
    });
    if (mediaOpenButton && typeof GDReservations !== 'undefined') {
      let mediaDialog = null;
      let mediaItems = [];
      let mediaSelection = new Set();
      let mediaLastFocus = null;

      const mediaRequest = async (body) => {
        const response = await fetch(GDReservations.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body
        });
        const result = await response.json();
        if (!response.ok || !result?.success) {
          throw new Error(result?.data?.message || 'Die Mediathek konnte nicht geladen werden.');
        }
        return result.data;
      };
      const existingAttachment = (imageId) => {
        const card = imagePreview?.querySelector(`[data-gse-image-id="${imageId}"]`);
        const image = card?.querySelector('img');
        return image ? { id: imageId, thumbnail: image.src, url: image.src, alt: image.alt, title: image.alt } : null;
      };
      const ensureMediaDialog = () => {
        if (mediaDialog) return mediaDialog;
        mediaDialog = document.createElement('div');
        mediaDialog.className = 'gse-media-dialog';
        mediaDialog.hidden = true;
        mediaDialog.innerHTML = `
          <button type="button" class="gse-media-dialog__backdrop" data-gse-media-close aria-label="Mediathek schließen"></button>
          <section class="gse-media-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="gse-media-dialog-title">
            <header>
              <div><span>WORDPRESS-MEDIATHEK</span><h2 id="gse-media-dialog-title">Eventfotos auswählen</h2></div>
              <button type="button" class="gse-media-dialog__close" data-gse-media-close aria-label="Mediathek schließen">×</button>
            </header>
            <div class="gse-media-dialog__toolbar">
              <label><span class="screen-reader-text">Mediathek durchsuchen</span><input type="search" data-gse-media-search placeholder="Mediathek durchsuchen"></label>
              <button type="button" class="button" data-gse-media-search-submit>Suchen</button>
              <label class="button button-primary gse-media-dialog__upload">Bilder hochladen<input type="file" accept="image/*" multiple data-gse-media-upload></label>
            </div>
            <p class="gse-media-dialog__status" data-gse-media-status role="status">Bilder werden geladen …</p>
            <div class="gse-media-dialog__grid" data-gse-media-grid></div>
            <footer><strong data-gse-media-count>0 von 12 ausgewählt</strong><button type="button" class="button button-primary" data-gse-media-apply>Für Event übernehmen</button></footer>
          </section>`;
        document.body.append(mediaDialog);
        return mediaDialog;
      };
      const updateMediaCount = () => {
        const count = mediaDialog?.querySelector('[data-gse-media-count]');
        if (count) count.textContent = `${mediaSelection.size} von 12 ausgewählt`;
      };
      const renderMediaItems = () => {
        const grid = mediaDialog?.querySelector('[data-gse-media-grid]');
        const status = mediaDialog?.querySelector('[data-gse-media-status]');
        if (!grid) return;
        grid.replaceChildren();
        mediaItems.forEach((attachment) => {
          const selected = mediaSelection.has(Number(attachment.id));
          const button = document.createElement('button');
          button.type = 'button';
          button.className = selected ? 'is-selected' : '';
          button.dataset.gseMediaId = String(attachment.id);
          button.setAttribute('aria-pressed', selected ? 'true' : 'false');
          const image = document.createElement('img');
          image.src = attachment.thumbnail || attachment.url || '';
          image.alt = attachment.alt || '';
          const title = document.createElement('span');
          title.textContent = attachment.title || `Bild ${attachment.id}`;
          button.append(image, title);
          grid.append(button);
        });
        if (status) status.textContent = mediaItems.length ? `${mediaItems.length} Bilder in der Mediathek` : 'Keine Bilder gefunden.';
        updateMediaCount();
      };
      const loadMediaItems = async (search = '') => {
        const status = mediaDialog?.querySelector('[data-gse-media-status]');
        if (status) status.textContent = 'Bilder werden geladen …';
        const body = new URLSearchParams({ action: 'gse_media_library', nonce: GDReservations.eventMediaNonce, search });
        try {
          mediaItems = await mediaRequest(body);
          renderMediaItems();
        } catch (error) {
          if (status) status.textContent = error.message;
        }
      };
      const closeMediaDialog = () => {
        if (!mediaDialog) return;
        mediaDialog.hidden = true;
        document.documentElement.classList.remove('gse-media-dialog-open');
        mediaLastFocus?.focus();
      };

      mediaOpenButton.addEventListener('click', () => {
        ensureMediaDialog();
        mediaSelection = new Set(selectedImageIds());
        mediaLastFocus = document.activeElement;
        mediaDialog.hidden = false;
        document.documentElement.classList.add('gse-media-dialog-open');
        mediaDialog.querySelector('[data-gse-media-search]')?.focus();
        loadMediaItems();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mediaDialog && !mediaDialog.hidden) closeMediaDialog();
      });
      document.addEventListener('click', async (event) => {
        if (!mediaDialog || mediaDialog.hidden || !event.target.closest('.gse-media-dialog')) return;
        if (event.target.closest('[data-gse-media-close]')) {
          closeMediaDialog();
          return;
        }
        const imageButton = event.target.closest('[data-gse-media-id]');
        if (imageButton) {
          const imageId = Number(imageButton.dataset.gseMediaId);
          if (mediaSelection.has(imageId)) mediaSelection.delete(imageId);
          else if (mediaSelection.size < 12) mediaSelection.add(imageId);
          renderMediaItems();
          return;
        }
        if (event.target.closest('[data-gse-media-search-submit]')) {
          loadMediaItems(mediaDialog.querySelector('[data-gse-media-search]')?.value || '');
          return;
        }
        if (event.target.closest('[data-gse-media-apply]')) {
          const attachments = [...mediaSelection].map((imageId) => mediaItems.find((item) => Number(item.id) === imageId) || existingAttachment(imageId)).filter(Boolean);
          setSelectedImages(attachments);
          closeMediaDialog();
        }
      });
      document.addEventListener('change', async (event) => {
        const input = event.target.closest('[data-gse-media-upload]');
        if (!input || !mediaDialog || mediaDialog.hidden || !input.files?.length) return;
        const status = mediaDialog.querySelector('[data-gse-media-status]');
        const body = new FormData();
        body.append('action', 'gse_media_upload');
        body.append('nonce', GDReservations.eventMediaNonce);
        [...input.files].slice(0, 12).forEach((file) => body.append('event_images[]', file));
        input.disabled = true;
        if (status) status.textContent = 'Bilder werden hochgeladen …';
        try {
          const uploaded = await mediaRequest(body);
          mediaItems = [...uploaded, ...mediaItems.filter((item) => !uploaded.some((upload) => Number(upload.id) === Number(item.id)))];
          uploaded.forEach((item) => {
            if (mediaSelection.size < 12) mediaSelection.add(Number(item.id));
          });
          renderMediaItems();
        } catch (error) {
          if (status) status.textContent = error.message;
        } finally {
          input.disabled = false;
          input.value = '';
        }
      });
    }

    pagePickerToggle?.addEventListener('click', () => {
      if (!pagePicker) return;
      pagePicker.hidden = !pagePicker.hidden;
      pagePickerToggle.setAttribute('aria-expanded', pagePicker.hidden ? 'false' : 'true');
    });
    pagePicker?.addEventListener('click', (event) => {
      const pageButton = event.target.closest('[data-gse-page-url]');
      if (!pageButton || !linkInput) return;
      linkInput.value = pageButton.dataset.gsePageUrl || '';
      linkInput.dispatchEvent(new Event('input', { bubbles: true }));
      pagePicker.hidden = true;
      pagePickerToggle?.setAttribute('aria-expanded', 'false');
    });

    form.addEventListener('submit', (event) => {
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }
      if (!form.checkValidity()) return;
      form.dataset.submitting = '1';
      const button = form.querySelector('[data-gse-submit]');
      const progress = form.querySelector('[data-gse-progress]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Wird gespeichert …';
      }
      if (progress) {
        progress.hidden = false;
        window.requestAnimationFrame(() => progress.classList.add('is-active'));
      }
    });
  });

  const shell = document.querySelector('.gd-dashboard-shell[data-default-view]');
  if (!shell || typeof GDReservations === 'undefined') return;

  const mainContent = document.querySelector('.gd-main-content');
  const list = document.getElementById('gd-booking-list');
  const loading = document.getElementById('gd-loading');
  const empty = document.getElementById('gd-empty');
  const search = document.getElementById('gd-booking-search');
  const searchClear = document.getElementById('gd-search-clear');
  const viewButtons = [...document.querySelectorAll('.gd-view-button[data-view]')];
  const refreshButtons = [...document.querySelectorAll('[data-refresh]')];
  const autoConfirmInputs = [...document.querySelectorAll('[data-auto-confirm]')];
  const autoConfirmFeedbacks = [...document.querySelectorAll('[data-auto-confirm-feedback]')];
  const refreshIntervalInputs = [...document.querySelectorAll('[data-refresh-interval]')];
  const refreshIntervalFeedbacks = [...document.querySelectorAll('[data-refresh-interval-feedback]')];
  const whatsappTemplateInputs = [...document.querySelectorAll('[data-whatsapp-template]')];
  const whatsappTemplateFeedbacks = [...document.querySelectorAll('[data-whatsapp-template-feedback]')];
  const whatsappTemplateSaveButtons = [...document.querySelectorAll('[data-save-whatsapp-template]')];
  const tableCountInputs = [...document.querySelectorAll('[data-table-count]')];
  const tableCountFeedbacks = [...document.querySelectorAll('[data-table-count-feedback]')];
  const tableCountSaveButtons = [...document.querySelectorAll('[data-save-table-count]')];
  const tableDefaultCapacityInputs = [...document.querySelectorAll('[data-table-default-capacity]')];
  const tableCapacityOverrideInputs = [...document.querySelectorAll('[data-table-capacity-overrides]')];
  const tableCapacityFeedbacks = [...document.querySelectorAll('[data-table-capacity-feedback]')];
  const tableCapacitySaveButtons = [...document.querySelectorAll('[data-save-table-capacity-settings]')];
  const exportCsvButtons = [...document.querySelectorAll('[data-export-csv]')];
  const exportXlsxButtons = [...document.querySelectorAll('[data-export-xlsx]')];
  const moreLayer = document.getElementById('gd-more-layer');
  const removalDialog = document.getElementById('gd-removal-dialog');
  const tablePickerDialog = document.getElementById('gd-table-picker-dialog');
  const tablePickerGrid = document.getElementById('gd-table-picker-grid');
  const tablePickerBookingName = document.getElementById('gd-table-picker-booking-name');
  const tablePickerCloseButtons = [...document.querySelectorAll('[data-close-table-picker]')];
  const tableConflictDialog = document.getElementById('gd-table-conflict-dialog');
  const tableConflictNumber = document.getElementById('gd-table-conflict-number');
  const tableConflictSummary = document.getElementById('gd-table-conflict-summary');
  const tableConflictBookings = document.getElementById('gd-table-conflict-bookings');
  const tableConflictConfirm = document.getElementById('gd-table-conflict-confirm');
  const tableConflictCloseButtons = [...document.querySelectorAll('[data-close-table-conflict]')];
  const removalBookingName = document.getElementById('gd-removal-booking-name');
  const removalCancelBookingButton = removalDialog?.querySelector('[data-cancel-booking]');
  const removalTrashButton = removalDialog?.querySelector('[data-move-trash]');
  const removalCloseButtons = [...document.querySelectorAll('[data-close-removal]')];
  const moreOpeners = [...document.querySelectorAll('[data-open-more]')];
  const moreClosers = [...document.querySelectorAll('[data-close-more]')];
  const manualDialog = document.getElementById('gd-manual-dialog');
  const manualForm = document.getElementById('gd-manual-form');
  const manualOpeners = [...document.querySelectorAll('[data-open-manual-booking]')];
  const manualClosers = [...document.querySelectorAll('[data-close-manual-booking]')];
  const manualDate = manualForm?.querySelector('[data-manual-date]');
  const manualDateButton = manualForm?.querySelector('[data-open-manual-calendar]');
  const manualDateLabel = manualForm?.querySelector('[data-manual-date-label]');
  const manualParty = manualForm?.querySelector('[data-manual-party]');
  const manualTime = manualForm?.querySelector('[data-manual-time]');
  const manualCustomTime = manualForm?.querySelector('[data-manual-custom-time]');
  const manualSlotsStatus = manualForm?.querySelector('[data-manual-slots-status]');
  const manualName = manualForm?.querySelector('[data-manual-name]');
  const manualPhone = manualForm?.querySelector('[data-manual-phone]');
  const manualEmail = manualForm?.querySelector('[data-manual-email]');
  const manualStatus = manualForm?.querySelector('[data-manual-status]');
  const manualGuestMessage = manualForm?.querySelector('[data-manual-guest-message]');
  const manualInternalComment = manualForm?.querySelector('[data-manual-internal-comment]');
  const manualTableValue = manualForm?.querySelector('[data-manual-table-value]');
  const manualAllowOccupied = manualForm?.querySelector('[data-manual-allow-occupied]');
  const manualTableLabel = manualForm?.querySelector('[data-manual-table-label]');
  const manualTableButton = manualForm?.querySelector('[data-open-manual-table]');
  const manualFeedback = manualForm?.querySelector('[data-manual-feedback]');
  const manualSubmit = manualForm?.querySelector('[data-manual-submit]');
  const manualBookingId = manualForm?.querySelector('[data-manual-booking-id]');
  const manualEyebrow = manualDialog?.querySelector('[data-manual-eyebrow]');
  const manualTitle = manualDialog?.querySelector('[data-manual-title]');
  const manualDescription = manualDialog?.querySelector('[data-manual-description]');
  const mobileTitle = document.getElementById('gd-mobile-title');
  const toast = document.getElementById('gd-toast');
  const networkBanner = document.getElementById('gd-network-banner');
  const installButton = document.querySelector('[data-install-app]');
  const iosInstallHint = document.querySelector('.gd-ios-install-hint');
  const themeButtons = [...document.querySelectorAll('[data-theme-button]')];
  const themeSwitches = [...document.querySelectorAll('[data-theme-switch]')];
  const themeColorMeta = document.getElementById('gd-theme-color');
  const systemDarkMode = window.matchMedia('(prefers-color-scheme: dark)');

  let currentView = shell.dataset.defaultView || 'pending';
  let searchTimer;
  let toastTimer;
  let deferredInstallPrompt = null;
  let lastLoadedAt = 0;
  let selectedTheme = null;
  let bookingRequestSequence = 0;
  let autoRefreshTimer = null;
  let refreshIntervalMinutes = Number(GDReservations.refreshInterval ?? 5);
  let whatsappMessageTemplate = String(GDReservations.whatsappTemplate || 'Hallo {name}, hier ist die Gelsendiele. Wir melden uns zu Ihrer Reservierung am {date} um {time} Uhr für {party}. Liebe Grüße, Ihr Gelsendiele-Team');
  let tableCount = Math.max(1, Math.min(300, Number(GDReservations.tableCount || 30)));
  let tableDefaultCapacity = Math.max(1, Math.min(50, Number(GDReservations.tableDefaultCapacity || 5)));
  let tableCapacityOverrides = GDReservations.tableCapacityOverrides || {};
  let removalContext = null;
  let tablePickerContext = null;
  let tableConflictContext = null;
  let manualSlotsRequest = 0;
  let manualSelectedTableInfo = null;
  let manualAvailableSlots = [];
  let manualDateOverride = false;
  let manualCalendarMonth = null;
  let manualWarningResolver = null;
  let manualEditingId = 0;
  let manualDesiredTime = '';
  let bookingsById = new Map();

  function setLoadingState(isLoading) {
    if (!loading) return;
    loading.hidden = !isLoading;
    loading.classList.toggle('is-visible', isLoading);
    loading.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
    loading.style.display = isLoading ? '' : 'none';
  }

  function updateAppViewportMetrics() {
    const viewport = window.visualViewport;
    const viewportHeight = viewport?.height || window.innerHeight || document.documentElement.clientHeight;
    const viewportWidth = viewport?.width || document.documentElement.clientWidth || window.innerWidth;

    if (viewportHeight > 0) {
      document.documentElement.style.setProperty('--gd-app-height', `${Math.round(viewportHeight)}px`);
    }

    if (viewportWidth > 0) {
      // visualViewport.width bleibt auch bei einem zuvor vergrößerten Browser-Zoom
      // die tatsächlich sichtbare Breite. Dadurch wird die App neu umbrochen, statt
      // rechts aus dem Bildschirm zu laufen.
      document.documentElement.style.setProperty('--gd-app-width', `${Math.round(viewportWidth)}px`);
    }
  }


  function initializePullToRefresh() {
    const touchCapable = window.matchMedia('(pointer: coarse)').matches || navigator.maxTouchPoints > 0;
    if (!mainContent || (!window.matchMedia('(max-width: 1024px)').matches && !touchCapable)) return;

    const indicator = document.createElement('div');
    indicator.className = 'gd-pull-refresh';
    indicator.setAttribute('aria-hidden', 'true');
    indicator.innerHTML = `
      <span class="gd-pull-refresh-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M20 11a8.1 8.1 0 0 0-14.7-4.7L3 9m0 0V4m0 5h5M4 13a8.1 8.1 0 0 0 14.7 4.7L21 15m0 0v5m0-5h-5"/></svg>
      </span>
      <span class="gd-pull-refresh-text">Zum Aktualisieren ziehen</span>`;
    mainContent.prepend(indicator);

    let startY = 0;
    let startX = 0;
    let distance = 0;
    let tracking = false;
    let refreshing = false;
    const threshold = 72;
    const maxPull = 108;

    const reset = () => {
      tracking = false;
      distance = 0;
      indicator.classList.remove('is-pulling', 'is-ready');
      indicator.style.setProperty('--gd-pull-distance', '0px');
      indicator.querySelector('.gd-pull-refresh-text').textContent = 'Zum Aktualisieren ziehen';
    };

    mainContent.addEventListener('touchstart', event => {
      if (refreshing || mainContent.scrollTop > 1 || event.touches.length !== 1) return;
      if (event.target.closest('input, textarea, select')) return;
      startY = event.touches[0].clientY;
      startX = event.touches[0].clientX;
      distance = 0;
      tracking = true;
    }, { passive: true });

    mainContent.addEventListener('touchmove', event => {
      if (!tracking || refreshing || event.touches.length !== 1) return;
      const dy = event.touches[0].clientY - startY;
      const dx = Math.abs(event.touches[0].clientX - startX);
      if (dy <= 0 || dx > Math.abs(dy) * 0.8) {
        reset();
        return;
      }

      event.preventDefault();
      distance = Math.min(maxPull, dy * 0.55);
      indicator.classList.add('is-pulling');
      indicator.classList.toggle('is-ready', distance >= threshold);
      indicator.style.setProperty('--gd-pull-distance', `${distance}px`);
      indicator.querySelector('.gd-pull-refresh-text').textContent = distance >= threshold
        ? 'Loslassen zum Aktualisieren'
        : 'Zum Aktualisieren ziehen';
    }, { passive: false });

    const finish = async () => {
      if (!tracking) return;
      const shouldRefresh = distance >= threshold;
      tracking = false;

      if (!shouldRefresh) {
        reset();
        return;
      }

      refreshing = true;
      indicator.classList.remove('is-ready');
      indicator.classList.add('is-refreshing');
      indicator.style.setProperty('--gd-pull-distance', '56px');
      indicator.querySelector('.gd-pull-refresh-text').textContent = 'Wird aktualisiert …';
      haptic([10, 35, 10]);

      try {
        await loadBookings({ quiet: true });
      } finally {
        refreshing = false;
        indicator.classList.remove('is-refreshing');
        reset();
      }
    };

    mainContent.addEventListener('touchend', finish, { passive: true });
    mainContent.addEventListener('touchcancel', reset, { passive: true });
  }

  function storedTheme() {
    try {
      const value = window.localStorage.getItem('gd-dashboard-theme');
      return value === 'dark' || value === 'light' ? value : null;
    } catch (error) {
      return null;
    }
  }

  function applyTheme(theme, persist = false) {
    selectedTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.gdTheme = selectedTheme;
    document.documentElement.classList.add('gd-dashboard-runtime');
    themeSwitches.forEach(input => { input.checked = selectedTheme === 'dark'; });
    themeButtons.forEach(button => {
      button.setAttribute('aria-pressed', selectedTheme === 'dark' ? 'true' : 'false');
      button.setAttribute('aria-label', selectedTheme === 'dark' ? 'Helle Darstellung aktivieren' : 'Dark Mode aktivieren');
    });
    if (themeColorMeta) themeColorMeta.content = selectedTheme === 'dark' ? '#111713' : '#315b2d';
    if (persist) {
      try { window.localStorage.setItem('gd-dashboard-theme', selectedTheme); } catch (error) {}
    }
  }

  function initializeTheme() {
    const saved = storedTheme();
    applyTheme(saved || (systemDarkMode.matches ? 'dark' : 'light'));
  }

  const viewTitles = {
    pending: 'Offene Reservierungen',
    today: 'Heute',
    upcoming: 'Kommende Reservierungen',
    confirmed: 'Bestätigte Reservierungen',
    all: 'Alle Reservierungen',
    trash: 'Papierkorb'
  };

  const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  }[char]));


  function normalizeWhatsAppPhone(value = '') {
    let phone = String(value).trim().replace(/[^0-9+]/g, '');
    if (!phone) return '';

    if (phone.startsWith('00')) phone = phone.slice(2);
    else if (phone.startsWith('+')) phone = phone.slice(1);

    // Österreichische lokale Rufnummern automatisch in das internationale Format bringen.
    if (phone.startsWith('0')) phone = `43${phone.slice(1)}`;

    return phone.replace(/\D/g, '');
  }

  function applyWhatsAppTemplate(template, booking) {
    const partyLabel = Number(booking.party) === 1 ? '1 Person' : `${booking.party} Personen`;
    const values = {
      name: booking.name || '',
      date: booking.date || '',
      time: booking.time || '',
      party: partyLabel
    };
    return String(template || '').replace(/\{(name|date|time|party)\}/gi, (match, key) => values[key.toLowerCase()] ?? match);
  }

  function whatsappUrl(booking) {
    const phone = normalizeWhatsAppPhone(booking.phone || '');
    if (!phone) return '';
    const message = applyWhatsAppTemplate(whatsappMessageTemplate, booking);
    return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
  }

  function whatsappButton(booking, iconOnly = false) {
    const url = whatsappUrl(booking);
    if (!url) return '';
    const label = iconOnly ? '<span class="screen-reader-text">WhatsApp</span>' : '<span class="gd-whatsapp-label">Per WhatsApp schreiben</span>';
    const iconClass = iconOnly ? ' gd-quick-icon-button' : '';
    return `<a class="gd-button gd-button-whatsapp${iconClass}" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp-Nachricht an ${escapeHtml(booking.name)} öffnen" title="WhatsApp">${icon('whatsapp')}${label}</a>`;
  }

  const icon = name => {
    const icons = {
      phone: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.7 3.7 6.6 2.9a2 2 0 0 0-2.4.8L2.8 6a2 2 0 0 0-.1 2c2.7 6.1 7.2 10.6 13.3 13.3a2 2 0 0 0 2-.1l2.3-1.4a2 2 0 0 0 .8-2.4l-.8-2.1a2 2 0 0 0-2.2-1.2l-2.3.5a2 2 0 0 0-1.2.8l-.6.8a15.7 15.7 0 0 1-6.2-6.2l.8-.6a2 2 0 0 0 .8-1.2l.5-2.3a2 2 0 0 0-1.2-2.2Z"/></svg>',
      mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m5 8 7 5 7-5"/></svg>',
      whatsapp: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.4-4.7A8.5 8.5 0 1 1 20.5 11.7Z"/><path d="M8.3 7.7c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.8 1.8c.1.3.1.5-.1.7l-.6.8c-.2.2-.1.4 0 .6.7 1.3 1.8 2.4 3.2 3 .2.1.4.1.6-.1l.9-1.1c.2-.2.4-.3.7-.2l1.8.9c.3.1.4.3.4.5 0 .3-.1 1.4-.8 2-.6.6-1.5.8-2.4.5-1-.3-2.6-.9-4.5-2.6-1.5-1.4-2.6-3-3-4.2-.4-1-.1-2 .4-2.6Z"/></svg>',
      chevron: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>',
      trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>',
      note: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h5M9 12h6M9 16h5"/></svg>',
      chat: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v10H9l-4 4z"/><path d="M8 9h8M8 12h5"/></svg>',
      restore: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10a8 8 0 1 1 2.3 7.7M4 10V5m0 5h5"/></svg>',
      check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
      close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
      details: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/></svg>',
      edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l11-11-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>',
      table: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 5.2h4"/><path d="M12 3.6v1.6"/><path d="M8.2 10.2h7.6"/><path d="M12 10.2v5.2"/><path d="M9.5 15.4h5"/><path d="M8.2 10.2V8.1c0-1 .8-1.8 1.8-1.8h4c1 0 1.8.8 1.8 1.8v2.1"/><path d="M6.1 9v4.2"/><path d="M17.9 9v4.2"/><path d="M4.9 9h2.9c.8 0 1.4.6 1.4 1.4v2.8"/><path d="M19.1 9h-2.9c-.8 0-1.4.6-1.4 1.4v2.8"/><path d="M4.9 17l1.2-3.8"/><path d="M19.1 17l-1.2-3.8"/></svg>'
    };
    return icons[name] || '';
  };

  async function request(action, data = {}) {
    if (!navigator.onLine) throw new Error(GDReservations.offline || 'Keine Internetverbindung.');
    const body = new URLSearchParams({ action, nonce: GDReservations.nonce, ...data });
    const response = await fetch(GDReservations.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body
    });
    const json = await response.json();
    if (!json.success) {
      const error = new Error(json.data?.message || GDReservations.error);
      error.data = json.data || {};
      error.status = response.status;
      throw error;
    }
    return json.data;
  }

  function showToast(message, isError = false) {
    if (!toast || !message) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.toggle('is-error', isError);
    toast.classList.add('is-visible');
    toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 2600);
  }

  function haptic(pattern = 12) {
    if ('vibrate' in navigator) navigator.vibrate(pattern);
  }

  function updateCounts(counts = {}) {
    Object.entries(counts).forEach(([key, value]) => {
      document.querySelectorAll(`[data-count="${key}"]`).forEach(el => {
        el.textContent = value;
        el.dataset.zero = Number(value) === 0 ? '1' : '0';
      });
    });
  }

  function syncViewUI() {
    viewButtons.forEach(button => {
      const active = button.dataset.view === currentView;
      button.classList.toggle('is-active', active);
      if (active) button.setAttribute('aria-current', 'page');
      else button.removeAttribute('aria-current');
    });
    if (mobileTitle) mobileTitle.textContent = viewTitles[currentView] || 'Reservierungen';
  }

  function statusClass(status) {
    return ['pending', 'confirmed', 'closed', 'cancelled', 'trash'].includes(status) ? status : 'other';
  }

  function quickActions(booking) {
    const whatsapp = whatsappButton(booking, true);
    if (booking.status === 'trash') {
      return `
        <button class="gd-button gd-button-primary gd-wide-action" data-restore="1">${icon('restore')}<span>Wiederherstellen</span></button>
        <button class="gd-button gd-button-danger-soft gd-wide-action" data-delete-permanently="1">${icon('trash')}<span>Endgültig löschen</span></button>`;
    }
    if (booking.status === 'pending') {
      return `
        <button class="gd-button gd-button-primary gd-quick-icon-button" data-status="confirmed" aria-label="Reservierung bestätigen" title="Bestätigen">${icon('check')}<span class="screen-reader-text">Bestätigen</span></button>
        <button class="gd-button gd-button-danger-soft gd-quick-icon-button" data-status="closed" aria-label="Reservierung ablehnen" title="Ablehnen">${icon('close')}<span class="screen-reader-text">Ablehnen</span></button>
        ${whatsapp}`;
    }
    if (booking.status === 'confirmed') {
      return `
        ${booking.phone ? `<a class="gd-button gd-button-secondary gd-quick-icon-button" href="tel:${escapeHtml(booking.phone)}" aria-label="${escapeHtml(booking.name)} anrufen" title="Anrufen">${icon('phone')}<span class="screen-reader-text">Anrufen</span></a>` : '<span class="gd-quick-action-placeholder" aria-hidden="true"></span>'}
        ${whatsapp || '<span class="gd-quick-action-placeholder" aria-hidden="true"></span>'}
        <button class="gd-button gd-button-secondary gd-quick-icon-button gd-quick-table-button${booking.tableNumber ? ' has-table-number' : ''}" data-open-table-picker aria-label="${booking.tableNumber ? `Tisch ${escapeHtml(booking.tableNumber)} ändern` : 'Tisch auswählen'}" title="${booking.tableNumber ? `Tisch ${escapeHtml(booking.tableNumber)}` : 'Tisch auswählen'}"><span class="gd-table-action-icon">${icon('table')}</span><span class="screen-reader-text">Tisch auswählen</span></button>`;
    }
    return `
      <button class="gd-button gd-button-secondary gd-quick-icon-button" data-status="pending" aria-label="Reservierung wieder öffnen" title="Wieder öffnen">${icon('restore')}<span class="screen-reader-text">Wieder öffnen</span></button>
      ${whatsapp}`;
  }

  function bookingCard(booking) {
    const formDetails = booking.formDetails || {};
    const preferenceItems = [
      formDetails.area ? `<span><strong>Bereich:</strong> ${escapeHtml(formDetails.area)}</span>` : '',
      formDetails.table ? `<span><strong>Tischwunsch:</strong> ${escapeHtml(formDetails.table)}</span>` : '',
      formDetails.highchair ? '<span><strong>Kinderstuhl:</strong> Ja</span>' : '',
      formDetails.dog ? '<span><strong>Hund:</strong> Ja</span>' : '',
      formDetails.allergies ? `<span><strong>Allergien:</strong> ${escapeHtml(formDetails.allergies)}</span>` : ''
    ].filter(Boolean);
    const preferences = preferenceItems.length
      ? `<div class="gd-message gd-guest-message"><div class="gd-message-heading"><span class="gd-guest-message-icon">${icon('note')}</span><strong>Zusätzliche Gastangaben</strong><span class="gd-source-badge">Formular</span></div><div class="gd-form-preferences">${preferenceItems.join('')}</div></div>`
      : '';
    const message = booking.message
      ? `<div class="gd-message gd-guest-message"><div class="gd-message-heading"><span class="gd-guest-message-icon">${icon('chat')}</span><strong>Nachricht vom Gast</strong><span class="gd-source-badge">Gast</span></div><p>${escapeHtml(booking.message)}</p></div>`
      : '';
    const phone = booking.phone
      ? `<div class="gd-contact-stack"><a class="gd-contact-link" href="tel:${escapeHtml(booking.phone)}"><span class="gd-contact-icon">${icon('phone')}</span><span class="gd-contact-value">${escapeHtml(booking.phone)}</span></a>${whatsappButton(booking, false)}</div>`
      : '<span class="gd-contact-empty">Keine Telefonnummer</span>';
    const email = booking.email
      ? `<a class="gd-contact-link" href="mailto:${escapeHtml(booking.email)}"><span class="gd-contact-icon">${icon('mail')}</span><span class="gd-contact-value">${escapeHtml(booking.email)}</span></a>`
      : '<span class="gd-contact-empty">Keine E-Mail</span>';
    const internalNoteIndicator = booking.internalComment
      ? `<span class="gd-note-indicator" title="Interne Team-Notiz vorhanden" aria-label="Interne Team-Notiz vorhanden">${icon('note')}</span>`
      : '';
    const guestMessageIndicator = booking.message
      ? `<span class="gd-guest-indicator" title="Nachricht vom Gast vorhanden" aria-label="Nachricht vom Gast vorhanden">${icon('chat')}</span>`
      : '';
    const messageIndicators = `${guestMessageIndicator}${internalNoteIndicator}`;
    const tableChip = booking.tableNumber
      ? `<span class="gd-table-chip">Tisch ${escapeHtml(booking.tableNumber)}${messageIndicators}</span>`
      : messageIndicators;

    return `
      <article class="gd-booking-card" data-booking-id="${booking.id}" data-booking-name="${escapeHtml(booking.name)}" data-table-number="${escapeHtml(booking.tableNumber || '')}">
        ${booking.status !== 'trash' ? `<button type="button" class="gd-card-edit-mini" data-edit-booking="1" aria-label="Reservierung bearbeiten" title="Reservierung bearbeiten">${icon('edit')}<span class="screen-reader-text">Reservierung bearbeiten</span></button><button type="button" class="gd-card-delete-mini" data-delete="1" aria-label="Stornieren oder aus der Liste entfernen" title="Stornieren oder entfernen">${icon('trash')}<span class="screen-reader-text">Reservierung entfernen</span></button>` : ''}
        <button type="button" class="gd-card-summary" data-toggle-card aria-expanded="false">
          <span class="gd-booking-date">
            <span>${escapeHtml(booking.date)}</span>
            <strong>${escapeHtml(booking.time)}</strong>
          </span>
          <span class="gd-summary-content">
            <span class="gd-summary-main">
              <h2>${escapeHtml(booking.name)}</h2>
              <span class="gd-guests">${booking.party} ${booking.party === 1 ? 'Person' : 'Personen'}${tableChip}</span>
            </span>
            <span class="gd-status gd-status-${statusClass(booking.status)}">${escapeHtml(booking.statusLabel)}</span>
          </span>
          <span class="gd-card-chevron">${icon('chevron')}</span>
        </button>

        <div class="gd-mobile-quick-actions">${quickActions(booking)}</div>

        <div class="gd-card-details">
          <div class="gd-contact-grid">
            <div><small>Telefon</small>${phone}</div>
            <div><small>E-Mail</small>${email}</div>
          </div>

          ${booking.status === 'trash' ? `
            <div class="gd-table-assignment gd-trash-summary">
              <div><small>Tisch</small><strong>${booking.tableNumber ? `Tisch ${escapeHtml(booking.tableNumber)}` : 'Nicht zugewiesen'}</strong></div>
              <div><small>Interne Notiz (Team)</small><p>${booking.internalComment ? escapeHtml(booking.internalComment) : 'Keine interne Notiz vorhanden.'}</p></div>
            </div>
          ` : `
            <div class="gd-table-assignment">
              <label for="gd-table-${booking.id}">Tischnummer</label>
              <div class="gd-table-controls">
                <input id="gd-table-${booking.id}" type="text" maxlength="30" value="${escapeHtml(booking.tableNumber || '')}" placeholder="z. B. 7 oder Terrasse 3" inputmode="text">
                <button type="button" class="gd-button gd-button-secondary" data-save-table="1">Speichern</button>
              </div>
              <span class="gd-table-feedback" aria-live="polite"></span>

              <div class="gd-comment-assignment">
                <label for="gd-comment-${booking.id}">Interne Notiz <span class="gd-team-only">nur Team</span></label>
                <textarea id="gd-comment-${booking.id}" maxlength="1000" rows="3" placeholder="z. B. Rückruf erledigt, Stammgast oder interne Information">${escapeHtml(booking.internalComment || '')}</textarea>
                <div class="gd-comment-footer">
                  <span class="gd-comment-feedback" aria-live="polite"></span>
                  <button type="button" class="gd-button gd-button-secondary" data-save-comment="1">Interne Notiz speichern</button>
                </div>
              </div>
            </div>
          `}

          ${preferences}
          ${message}

          <div class="gd-actions">
            ${booking.status === 'trash' ? `
              <button class="gd-button gd-button-primary" data-restore="1">${icon('restore')}<span>Wiederherstellen</span></button>
              <button class="gd-button gd-button-danger-soft" data-delete-permanently="1">${icon('trash')}<span>Endgültig löschen</span></button>
            ` : `
              <button class="gd-button gd-button-secondary gd-button-edit-booking" data-edit-booking="1">${icon('edit')}<span>Reservierung bearbeiten</span></button>
              ${whatsappButton(booking, false)}
              ${booking.status !== 'confirmed' ? `<button class="gd-button gd-button-primary" data-status="confirmed">Bestätigen</button>` : ''}
              ${booking.status !== 'pending' ? `<button class="gd-button gd-button-secondary" data-status="pending">Auf offen setzen</button>` : ''}
              ${booking.status !== 'closed' ? `<button class="gd-button gd-button-danger-soft" data-status="closed">Ablehnen</button>` : ''}
            `}
          </div>
        </div>
      </article>`;
  }

  async function loadBookings({ quiet = false, preserveExpanded = false, notify = quiet } = {}) {
    const requestSequence = ++bookingRequestSequence;
    const expandedIds = preserveExpanded
      ? [...list.querySelectorAll('.gd-booking-card.is-expanded')].map(card => card.dataset.bookingId)
      : [];

    if (!quiet) {
      setLoadingState(true);
      empty.hidden = true;
      list.innerHTML = '';
    }

    refreshButtons.forEach(button => button.disabled = true);
    try {
      const data = await request('gd_get_bookings', {
        view: currentView,
        search: search.value.trim()
      });

      // Eine ältere, langsamere Anfrage darf die neuere Ansicht nicht mehr überschreiben.
      if (requestSequence !== bookingRequestSequence) return;

      updateCounts(data.counts);
      bookingsById = new Map((data.bookings || []).map(booking => [String(booking.id), booking]));
      list.innerHTML = data.bookings.map(bookingCard).join('');
      expandedIds.forEach(id => {
        const card = list.querySelector(`.gd-booking-card[data-booking-id="${CSS.escape(String(id))}"]`);
        if (card) toggleCard(card, true);
      });
      empty.hidden = data.bookings.length > 0;
      setLoadingState(false);
      lastLoadedAt = Date.now();
      if (notify) showToast(GDReservations.updated || 'Aktualisiert.');
    } catch (error) {
      if (requestSequence !== bookingRequestSequence) return;
      if (!quiet) list.innerHTML = `<div class="gd-notice gd-notice-error">${escapeHtml(error.message)}</div>`;
      showToast(error.message, true);
    } finally {
      if (requestSequence === bookingRequestSequence) {
        setLoadingState(false);
        window.requestAnimationFrame(() => setLoadingState(false));
        refreshButtons.forEach(button => button.disabled = false);
      }
    }
  }

  function toggleCard(card, force) {
    if (!card) return;
    const expanded = typeof force === 'boolean' ? force : !card.classList.contains('is-expanded');
    card.classList.toggle('is-expanded', expanded);
    const toggle = card.querySelector('.gd-card-summary');
    if (toggle) toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    if (expanded) setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 80);
  }

  async function updateStatus(card, status, button) {
    button.disabled = true;
    card.classList.add('is-working');
    try {
      const data = await request('gd_update_booking_status', {
        bookingId: card.dataset.bookingId,
        status
      });
      updateCounts(data.counts);
      haptic(status === 'confirmed' ? [12, 40, 12] : 12);
      showToast(`${data.label || 'Status'} gespeichert.`);
      card.classList.add('is-success');
      setTimeout(() => loadBookings(), 280);
    } catch (error) {
      showToast(error.message, true);
      button.disabled = false;
      card.classList.remove('is-working');
    }
  }

  async function updateTableNumber(card, button) {
    const input = card.querySelector('.gd-table-assignment input');
    const feedback = card.querySelector('.gd-table-feedback');
    if (!input || !feedback) return;

    button.disabled = true;
    input.disabled = true;
    feedback.textContent = 'Wird gespeichert …';
    feedback.classList.remove('is-error', 'is-success');

    try {
      const data = await request('gd_update_table_number', {
        bookingId: card.dataset.bookingId,
        tableNumber: input.value.trim()
      });
      input.value = data.tableNumber || '';
      feedback.textContent = data.message || 'Gespeichert.';
      feedback.classList.add('is-success');
      haptic();
      showToast(data.message || 'Tischnummer gespeichert.');
      await loadBookings({ quiet: true, preserveExpanded: true, notify: false });
      setTimeout(() => {
        feedback.textContent = '';
        feedback.classList.remove('is-success');
      }, 2200);
    } catch (error) {
      feedback.textContent = error.message;
      feedback.classList.add('is-error');
      showToast(error.message, true);
    } finally {
      button.disabled = false;
      input.disabled = false;
    }
  }

  async function updateInternalComment(card, button) {
    const textarea = card.querySelector('.gd-comment-assignment textarea');
    const feedback = card.querySelector('.gd-comment-feedback');
    if (!textarea || !feedback) return;

    button.disabled = true;
    textarea.disabled = true;
    feedback.textContent = 'Wird gespeichert …';
    feedback.classList.remove('is-error', 'is-success');

    try {
      const data = await request('gd_update_internal_comment', {
        bookingId: card.dataset.bookingId,
        internalComment: textarea.value.trim()
      });
      textarea.value = data.internalComment || '';
      feedback.textContent = data.message || 'Gespeichert.';
      feedback.classList.add('is-success');
      haptic();
      showToast(data.message || 'Kommentar gespeichert.');
      await loadBookings({ quiet: true, preserveExpanded: true, notify: false });
      setTimeout(() => {
        feedback.textContent = '';
        feedback.classList.remove('is-success');
      }, 2200);
    } catch (error) {
      feedback.textContent = error.message;
      feedback.classList.add('is-error');
      showToast(error.message, true);
    } finally {
      button.disabled = false;
      textarea.disabled = false;
    }
  }

  async function updateAutoConfirm(changedInput) {
    const enabled = changedInput.checked;
    autoConfirmInputs.forEach(input => input.disabled = true);
    autoConfirmFeedbacks.forEach(el => {
      el.textContent = 'Wird gespeichert …';
      el.classList.remove('is-error', 'is-success');
    });

    try {
      const data = await request('gd_update_auto_confirm', { enabled: enabled ? '1' : '0' });
      autoConfirmInputs.forEach(input => input.checked = !!data.enabled);
      autoConfirmFeedbacks.forEach(el => {
        el.textContent = data.message || (data.enabled ? GDReservations.autoConfirmOn : GDReservations.autoConfirmOff);
        el.classList.add('is-success');
      });
      haptic();
      showToast(data.message || 'Einstellung gespeichert.');
      setTimeout(() => autoConfirmFeedbacks.forEach(el => {
        el.textContent = '';
        el.classList.remove('is-success');
      }), 2600);
    } catch (error) {
      autoConfirmInputs.forEach(input => input.checked = !enabled);
      autoConfirmFeedbacks.forEach(el => {
        el.textContent = error.message;
        el.classList.add('is-error');
      });
      showToast(error.message, true);
    } finally {
      autoConfirmInputs.forEach(input => input.disabled = false);
    }
  }

  function scheduleAutoRefresh() {
    if (autoRefreshTimer) {
      window.clearInterval(autoRefreshTimer);
      autoRefreshTimer = null;
    }
    if (!Number.isFinite(refreshIntervalMinutes) || refreshIntervalMinutes <= 0) return;

    autoRefreshTimer = window.setInterval(() => {
      if (document.visibilityState !== 'visible' || !navigator.onLine) return;
      if (document.activeElement?.matches('input, textarea, select')) return;
      loadBookings({ quiet: true, preserveExpanded: true, notify: false });
    }, refreshIntervalMinutes * 60 * 1000);
  }

  async function updateRefreshInterval(changedInput) {
    const value = Number(changedInput.value);
    refreshIntervalInputs.forEach(input => input.disabled = true);
    refreshIntervalFeedbacks.forEach(el => {
      el.textContent = 'Wird gespeichert …';
      el.classList.remove('is-error', 'is-success');
    });

    try {
      const data = await request('gd_update_refresh_interval', { interval: String(value) });
      refreshIntervalMinutes = Number(data.interval || 0);
      refreshIntervalInputs.forEach(input => { input.value = String(refreshIntervalMinutes); });
      refreshIntervalFeedbacks.forEach(el => {
        el.textContent = data.message || 'Einstellung gespeichert.';
        el.classList.add('is-success');
      });
      scheduleAutoRefresh();
      haptic();
      showToast(data.message || 'Aktualisierungsintervall gespeichert.');
      setTimeout(() => refreshIntervalFeedbacks.forEach(el => {
        el.textContent = '';
        el.classList.remove('is-success');
      }), 2600);
    } catch (error) {
      refreshIntervalInputs.forEach(input => { input.value = String(refreshIntervalMinutes); });
      refreshIntervalFeedbacks.forEach(el => {
        el.textContent = error.message;
        el.classList.add('is-error');
      });
      showToast(error.message, true);
    } finally {
      refreshIntervalInputs.forEach(input => input.disabled = false);
    }
  }

  async function updateWhatsAppTemplate() {
    const source = whatsappTemplateInputs[0];
    const template = source?.value.trim() || '';
    whatsappTemplateInputs.forEach(input => { input.disabled = true; });
    whatsappTemplateSaveButtons.forEach(button => { button.disabled = true; });
    whatsappTemplateFeedbacks.forEach(el => {
      el.textContent = 'Wird gespeichert …';
      el.classList.remove('is-error', 'is-success');
    });

    try {
      const data = await request('gd_update_whatsapp_template', { template });
      whatsappMessageTemplate = String(data.template || template);
      whatsappTemplateInputs.forEach(input => { input.value = whatsappMessageTemplate; });
      whatsappTemplateFeedbacks.forEach(el => {
        el.textContent = data.message || 'WhatsApp-Standardtext gespeichert.';
        el.classList.add('is-success');
      });
      haptic();
      showToast(data.message || 'WhatsApp-Standardtext gespeichert.');
      setTimeout(() => whatsappTemplateFeedbacks.forEach(el => {
        el.textContent = '';
        el.classList.remove('is-success');
      }), 2600);
    } catch (error) {
      whatsappTemplateFeedbacks.forEach(el => {
        el.textContent = error.message;
        el.classList.add('is-error');
      });
      showToast(error.message, true);
    } finally {
      whatsappTemplateInputs.forEach(input => { input.disabled = false; });
      whatsappTemplateSaveButtons.forEach(button => { button.disabled = false; });
    }
  }

  async function updateTableCount() {
    const source = tableCountInputs[0];
    const requested = Math.max(1, Math.min(300, Number(source?.value || 30)));
    tableCountInputs.forEach(input => { input.disabled = true; });
    tableCountSaveButtons.forEach(button => { button.disabled = true; });
    tableCountFeedbacks.forEach(el => {
      el.textContent = 'Wird gespeichert …';
      el.classList.remove('is-error', 'is-success');
    });

    try {
      const data = await request('gd_update_table_count', { tableCount: requested });
      tableCount = Math.max(1, Math.min(300, Number(data.tableCount || requested)));
      tableCountInputs.forEach(input => { input.value = tableCount; });
      tableCountFeedbacks.forEach(el => {
        el.textContent = data.message || 'Anzahl der Tische gespeichert.';
        el.classList.add('is-success');
      });
      haptic();
      showToast(data.message || 'Anzahl der Tische gespeichert.');
      setTimeout(() => tableCountFeedbacks.forEach(el => {
        el.textContent = '';
        el.classList.remove('is-success');
      }), 2600);
    } catch (error) {
      tableCountFeedbacks.forEach(el => {
        el.textContent = error.message;
        el.classList.add('is-error');
      });
      showToast(error.message, true);
    } finally {
      tableCountInputs.forEach(input => { input.disabled = false; });
      tableCountSaveButtons.forEach(button => { button.disabled = false; });
    }
  }

  async function updateTableCapacitySettings() {
    const defaultCapacity = Math.max(1, Math.min(50, Number(tableDefaultCapacityInputs[0]?.value || 5)));
    const overrides = String(tableCapacityOverrideInputs[0]?.value || '').trim();

    [...tableDefaultCapacityInputs, ...tableCapacityOverrideInputs, ...tableCapacitySaveButtons].forEach(el => { if (el) el.disabled = true; });
    tableCapacityFeedbacks.forEach(el => {
      el.textContent = 'Wird gespeichert …';
      el.classList.remove('is-error', 'is-success');
    });

    try {
      const data = await request('gd_update_table_capacity_settings', { defaultCapacity, overrides });
      tableDefaultCapacity = Math.max(1, Math.min(50, Number(data.defaultCapacity || defaultCapacity)));
      tableCapacityOverrides = data.overrides || {};
      tableDefaultCapacityInputs.forEach(input => { input.value = tableDefaultCapacity; });
      tableCapacityOverrideInputs.forEach(input => { input.value = data.formatted || ''; });
      tableCapacityFeedbacks.forEach(el => {
        el.textContent = data.message || 'Tischkapazitäten gespeichert.';
        el.classList.add('is-success');
      });
      haptic();
      showToast(data.message || 'Tischkapazitäten gespeichert.');
      setTimeout(() => tableCapacityFeedbacks.forEach(el => {
        el.textContent = '';
        el.classList.remove('is-success');
      }), 2600);
    } catch (error) {
      tableCapacityFeedbacks.forEach(el => {
        el.textContent = error.message;
        el.classList.add('is-error');
      });
      showToast(error.message, true);
    } finally {
      [...tableDefaultCapacityInputs, ...tableCapacityOverrideInputs, ...tableCapacitySaveButtons].forEach(el => { if (el) el.disabled = false; });
    }
  }

  function tableStateLabel(table) {
    if (!table || table.state === 'free') return `${table?.capacity || tableDefaultCapacity} Plätze`;
    if (table.state === 'partial') return `${table.remainingSeats} frei`;
    return 'Belegt';
  }

  function buildTablePicker(tables = [], currentValue = '') {
    if (!tablePickerGrid) return;
    const current = String(currentValue || '').trim();
    const buttons = [];
    const normalized = new Map((tables || []).map(table => [String(table.number), table]));

    for (let number = 1; number <= tableCount; number += 1) {
      const value = String(number);
      const info = normalized.get(value) || {
        number,
        capacity: Number(tableCapacityOverrides?.[value] || tableDefaultCapacity),
        occupiedSeats: 0,
        remainingSeats: Number(tableCapacityOverrides?.[value] || tableDefaultCapacity),
        requestedSeats: 0,
        state: 'free',
        bookings: []
      };
      const selected = current === value || Boolean(info.selected);
      const state = ['free', 'partial', 'full'].includes(info.state) ? info.state : 'free';
      buttons.push(`
        <button type="button" class="gd-table-number-button is-${state}${selected ? ' is-selected' : ''}" data-table-value="${value}" data-table-state="${state}" role="option" aria-selected="${selected ? 'true' : 'false'}" title="Tisch ${value}: ${escapeHtml(tableStateLabel(info))}">
          <strong>${value}</strong>
          <small>${escapeHtml(tableStateLabel(info))}</small>
        </button>`);
    }
    tablePickerGrid.innerHTML = buttons.join('');
  }

  async function openTablePicker(card, triggerButton) {
    if (!tablePickerDialog || !card) return;
    tablePickerContext = { card, triggerButton, tables: [] };
    const bookingName = card.dataset.bookingName || card.querySelector('.gd-summary-main h2')?.textContent?.trim() || 'Reservierung';
    const currentValue = card.dataset.tableNumber || '';
    if (tablePickerBookingName) tablePickerBookingName.textContent = bookingName;
    tablePickerGrid.innerHTML = '<div class="gd-table-picker-loading">Tischbelegung wird geprüft …</div>';
    tablePickerDialog.classList.add('is-open');
    tablePickerDialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');

    try {
      const data = await request('gd_get_table_availability', { bookingId: card.dataset.bookingId });
      if (!tablePickerContext || tablePickerContext.card !== card) return;
      tablePickerContext.tables = Array.isArray(data.tables) ? data.tables : [];
      buildTablePicker(tablePickerContext.tables, currentValue);
      const selected = tablePickerGrid?.querySelector('.is-selected');
      setTimeout(() => (selected || tablePickerGrid?.querySelector('button'))?.focus(), 70);
    } catch (error) {
      tablePickerGrid.innerHTML = `<div class="gd-table-picker-error">${escapeHtml(error.message || 'Tischbelegung konnte nicht geladen werden.')}</div>`;
    }
  }

  function closeTablePicker({ restoreFocus = true } = {}) {
    if (!tablePickerDialog) return;
    const previous = tablePickerContext?.triggerButton;
    tablePickerDialog.classList.remove('is-open');
    tablePickerDialog.setAttribute('aria-hidden', 'true');
    if (!tableConflictDialog?.classList.contains('is-open') && !manualDialog?.classList.contains('is-open')) document.body.classList.remove('gd-dialog-open');
    tablePickerContext = null;
    if (restoreFocus) setTimeout(() => previous?.focus(), 60);
  }

  function openTableConflict(info, button) {
    if (!tableConflictDialog || !tablePickerContext || !info) return;
    tableConflictContext = { info, button, pickerContext: tablePickerContext };
    if (tableConflictNumber) tableConflictNumber.textContent = String(info.number || '');

    const totalAfter = Number(info.occupiedSeats || 0) + Number(info.requestedSeats || 0);
    const capacity = Number(info.capacity || tableDefaultCapacity);
    const remaining = Math.max(0, capacity - Number(info.occupiedSeats || 0));
    const canShare = Number(info.requestedSeats || 0) <= remaining;
    if (tableConflictSummary) {
      tableConflictSummary.innerHTML = `
        <div><span>Kapazität</span><strong>${capacity} Plätze</strong></div>
        <div><span>Bereits belegt</span><strong>${Number(info.occupiedSeats || 0)} Plätze</strong></div>
        <div><span>Danach belegt</span><strong class="${totalAfter > capacity ? 'is-over' : ''}">${totalAfter} von ${capacity}</strong></div>`;
    }
    if (tableConflictBookings) {
      const rows = (info.bookings || []).map(booking => `
        <div class="gd-table-conflict-booking">
          <span><strong>${escapeHtml(booking.name || 'Reservierung')}</strong><small>${escapeHtml(booking.time || '')}</small></span>
          <b>${Number(booking.party || 0)} ${Number(booking.party || 0) === 1 ? 'Person' : 'Personen'}</b>
        </div>`).join('');
      tableConflictBookings.innerHTML = rows || '<p>Der Tisch ist bereits belegt.</p>';
    }
    if (tableConflictConfirm) {
      tableConflictConfirm.textContent = canShare ? 'Tisch gemeinsam nutzen' : 'Trotzdem überbelegen';
      tableConflictConfirm.classList.toggle('gd-button-warning', !canShare);
    }

    tablePickerDialog.classList.remove('is-open');
    tablePickerDialog.setAttribute('aria-hidden', 'true');
    tableConflictDialog.classList.add('is-open');
    tableConflictDialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');
    setTimeout(() => tableConflictConfirm?.focus(), 70);
  }

  function closeTableConflict({ returnToPicker = true, restoreFocus = true } = {}) {
    if (!tableConflictDialog) return;
    const context = tableConflictContext;
    tableConflictDialog.classList.remove('is-open');
    tableConflictDialog.setAttribute('aria-hidden', 'true');
    tableConflictContext = null;

    if (returnToPicker && context?.pickerContext) {
      tablePickerContext = context.pickerContext;
      tablePickerDialog.classList.add('is-open');
      tablePickerDialog.setAttribute('aria-hidden', 'false');
      document.body.classList.add('gd-dialog-open');
      if (restoreFocus) setTimeout(() => context.button?.focus(), 60);
    } else if (!manualDialog?.classList.contains('is-open')) {
      document.body.classList.remove('gd-dialog-open');
    }
  }

  async function selectQuickTable(value, button, allowOccupied = false) {
    const context = tablePickerContext || tableConflictContext?.pickerContext;
    if (!context) return;

    if (context.manual) {
      const info = context.tables?.find(table => String(table.number) === String(value)) || null;
      setManualTableSelection(value, info, allowOccupied);
      if (tableConflictDialog?.classList.contains('is-open')) {
        closeTableConflict({ returnToPicker: false, restoreFocus: false });
        tablePickerContext = null;
        document.body.classList.add('gd-dialog-open');
      } else {
        closeTablePicker({ restoreFocus: false });
        document.body.classList.add('gd-dialog-open');
      }
      haptic();
      return;
    }

    const { card } = context;
    const bookingId = card.dataset.bookingId;
    tablePickerGrid?.querySelectorAll('button').forEach(item => { item.disabled = true; });
    if (tableConflictConfirm) tableConflictConfirm.disabled = true;
    const clearButton = tablePickerDialog?.querySelector('.gd-table-picker-clear');
    if (clearButton) clearButton.disabled = true;
    try {
      const data = await request('gd_update_table_number', { bookingId, tableNumber: value, allowOccupied: allowOccupied ? '1' : '0' });
      card.dataset.tableNumber = String(data.tableNumber || '');
      if (tableConflictDialog?.classList.contains('is-open')) {
        closeTableConflict({ returnToPicker: false, restoreFocus: false });
        tablePickerContext = null;
      } else {
        closeTablePicker({ restoreFocus: false });
      }
      haptic();
      showToast(data.message || 'Tischnummer gespeichert.');
      await loadBookings({ quiet: true, preserveExpanded: true, notify: false });
    } catch (error) {
      const conflict = error.data?.conflict;
      if (conflict && !allowOccupied) {
        openTableConflict(conflict, button);
      } else {
        showToast(error.message, true);
      }
      tablePickerGrid?.querySelectorAll('button').forEach(item => { item.disabled = false; });
      if (tableConflictConfirm) tableConflictConfirm.disabled = false;
      if (clearButton) clearButton.disabled = false;
    }
  }

  function exportBookings(baseUrl) {
    if (!baseUrl) return;
    const url = new URL(baseUrl, window.location.href);
    url.searchParams.set('view', currentView);
    const query = search.value.trim();
    if (query) url.searchParams.set('search', query);
    window.location.href = url.toString();
  }

  function openRemovalDialog(card, triggerButton) {
    if (!removalDialog || !card) return;
    const bookingName = card.querySelector('.gd-summary-main h2')?.textContent?.trim() || 'diese Reservierung';
    if (removalBookingName) removalBookingName.textContent = bookingName === 'diese Reservierung' ? bookingName : `„${bookingName}“`;
    removalContext = { card, triggerButton };
    removalDialog.classList.add('is-open');
    removalDialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');
    setTimeout(() => removalCancelBookingButton?.focus(), 70);
  }

  function closeRemovalDialog({ restoreFocus = true } = {}) {
    if (!removalDialog) return;
    const previous = removalContext?.triggerButton;
    removalDialog.classList.remove('is-open');
    removalDialog.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('gd-dialog-open');
    removalCancelBookingButton && (removalCancelBookingButton.disabled = false);
    removalTrashButton && (removalTrashButton.disabled = false);
    removalContext = null;
    if (restoreFocus) setTimeout(() => previous?.focus(), 60);
  }

  async function cancelBookingFromDialog() {
    if (!removalContext) return;
    const { card } = removalContext;
    const actionButton = removalCancelBookingButton;
    if (actionButton) actionButton.disabled = true;
    if (removalTrashButton) removalTrashButton.disabled = true;
    closeRemovalDialog({ restoreFocus: false });
    await updateStatus(card, 'cancelled', actionButton || document.createElement('button'));
  }

  async function moveBookingToTrashFromDialog() {
    if (!removalContext) return;
    const { card } = removalContext;
    const actionButton = removalTrashButton;
    if (actionButton) actionButton.disabled = true;
    if (removalCancelBookingButton) removalCancelBookingButton.disabled = true;
    closeRemovalDialog({ restoreFocus: false });
    await deleteBooking(card, actionButton || document.createElement('button'), true);
  }

  async function deleteBooking(card, button, skipConfirm = false) {
    if (!skipConfirm && !confirm(GDReservations.confirmDelete)) return;
    button.disabled = true;
    card.classList.add('is-working');
    try {
      const data = await request('gd_delete_booking', { bookingId: card.dataset.bookingId });
      updateCounts(data.counts);
      haptic();
      showToast('Reservierung in den Papierkorb verschoben.');
      card.classList.add('is-removed');
      setTimeout(() => {
        card.remove();
        if (!list.children.length) empty.hidden = false;
      }, 240);
    } catch (error) {
      showToast(error.message, true);
      button.disabled = false;
      card.classList.remove('is-working');
    }
  }

  async function restoreBooking(card, button) {
    button.disabled = true;
    card.classList.add('is-working');
    try {
      const data = await request('gd_restore_booking', { bookingId: card.dataset.bookingId });
      updateCounts(data.counts);
      haptic();
      showToast(data.message || 'Reservierung wiederhergestellt.');
      card.classList.add('is-removed');
      setTimeout(() => {
        card.remove();
        if (!list.children.length) empty.hidden = false;
      }, 240);
    } catch (error) {
      showToast(error.message, true);
      button.disabled = false;
      card.classList.remove('is-working');
    }
  }

  async function deleteBookingPermanently(card, button) {
    if (!confirm('Diese Reservierung wirklich endgültig löschen? Dieser Vorgang kann nicht rückgängig gemacht werden.')) return;
    button.disabled = true;
    card.classList.add('is-working');
    try {
      const data = await request('gd_delete_booking_permanently', { bookingId: card.dataset.bookingId });
      updateCounts(data.counts);
      haptic([20, 35, 20]);
      showToast(data.message || 'Reservierung endgültig gelöscht.');
      card.classList.add('is-removed');
      setTimeout(() => {
        card.remove();
        if (!list.children.length) empty.hidden = false;
      }, 240);
    } catch (error) {
      showToast(error.message, true);
      button.disabled = false;
      card.classList.remove('is-working');
    }
  }

  function openMore() {
    if (!moreLayer) return;
    moreLayer.classList.add('is-open');
    moreLayer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-sheet-open');
    setTimeout(() => moreLayer.querySelector('.gd-more-sheet button:not([hidden])')?.focus(), 180);
  }

  function closeMore() {
    if (!moreLayer) return;
    moreLayer.classList.remove('is-open');
    moreLayer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('gd-sheet-open');
  }


  const manualMonthNames = ['Jänner','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

  function manualPad(value) { return String(value).padStart(2, '0'); }
  function manualDateKey(date) { return `${date.getFullYear()}-${manualPad(date.getMonth() + 1)}-${manualPad(date.getDate())}`; }
  function manualParseDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
  }
  function manualDisplayDate(date) { return `${manualPad(date.getDate())}.${manualPad(date.getMonth() + 1)}.${date.getFullYear()}`; }
  function manualAddDays(date, days) { const next = new Date(date.getFullYear(), date.getMonth(), date.getDate()); next.setDate(next.getDate() + days); return next; }
  function manualToday() { const date = manualParseDate(String(GDReservations.today || '')); return date || new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate()); }
  function manualSameDay(a, b) { return Boolean(a && b && manualDateKey(a) === manualDateKey(b)); }

  function manualExceptionMode(date) {
    const exceptions = Array.isArray(GDReservations.dateExceptions) ? GDReservations.dateExceptions : [];
    const key = manualDateKey(date);
    for (const item of exceptions) {
      if (!item) continue;
      if (item.type === 'date') {
        const single = manualParseDate(String(item.date || '').replace(/\//g, '-'));
        if (single && manualDateKey(single) === key) return item.mode || 'closed';
      } else if (item.type === 'range') {
        const start = manualParseDate(String(item.start || '1970-01-01').replace(/\//g, '-')) || new Date(1970,0,1);
        const end = manualParseDate(String(item.end || '2999-12-31').replace(/\//g, '-')) || new Date(2999,11,31);
        if (date >= start && date <= end) return item.mode || 'closed';
      }
    }
    return '';
  }

  function isManualRegularDate(date) {
    if (!date || date < manualToday()) return false;
    const exception = manualExceptionMode(date);
    if (exception) return exception === 'open';
    const weekdays = GDReservations.openWeekdays;
    return Array.isArray(weekdays) && weekdays.length === 7 ? Boolean(weekdays[date.getDay()]) : true;
  }

  function nextManualRegularDate(from) {
    let date = new Date(from.getFullYear(), from.getMonth(), from.getDate());
    for (let i = 0; i < 370; i += 1) {
      if (isManualRegularDate(date)) return date;
      date = manualAddDays(date, 1);
    }
    return from;
  }

  function ensureManualWarningDialog() {
    let dialog = document.getElementById('gd-manual-warning-dialog');
    if (dialog) return dialog;
    dialog = document.createElement('div');
    dialog.id = 'gd-manual-warning-dialog';
    dialog.className = 'gd-manual-warning-dialog';
    dialog.setAttribute('aria-hidden', 'true');
    dialog.innerHTML = `
      <button type="button" class="gd-manual-warning-backdrop" data-manual-warning-cancel aria-label="Hinweis schließen"></button>
      <section class="gd-manual-warning-panel" role="dialog" aria-modal="true" aria-labelledby="gd-manual-warning-title">
        <div class="gd-manual-warning-icon">!</div>
        <h2 id="gd-manual-warning-title">Bitte prüfen</h2>
        <p data-manual-warning-message></p>
        <ul data-manual-warning-list></ul>
        <div class="gd-manual-warning-actions">
          <button type="button" class="gd-button gd-button-secondary" data-manual-warning-cancel>Zurück</button>
          <button type="button" class="gd-button gd-button-primary" data-manual-warning-confirm>Trotzdem fortfahren</button>
        </div>
      </section>`;
    document.body.appendChild(dialog);
    dialog.querySelectorAll('[data-manual-warning-cancel]').forEach(button => button.addEventListener('click', () => closeManualWarning(false)));
    dialog.querySelector('[data-manual-warning-confirm]')?.addEventListener('click', () => closeManualWarning(true));
    return dialog;
  }

  function closeManualWarning(result) {
    const dialog = document.getElementById('gd-manual-warning-dialog');
    if (!dialog) return;
    dialog.classList.remove('is-open');
    dialog.setAttribute('aria-hidden', 'true');
    const resolver = manualWarningResolver;
    manualWarningResolver = null;
    if (!manualDialog?.classList.contains('is-open') && !tablePickerDialog?.classList.contains('is-open')) document.body.classList.remove('gd-dialog-open');
    if (resolver) resolver(Boolean(result));
  }

  function manualWarningConfirm({ title = 'Bitte prüfen', message = '', items = [], confirmLabel = 'Trotzdem fortfahren' } = {}) {
    const dialog = ensureManualWarningDialog();
    dialog.querySelector('#gd-manual-warning-title').textContent = title;
    dialog.querySelector('[data-manual-warning-message]').textContent = message;
    const list = dialog.querySelector('[data-manual-warning-list]');
    list.innerHTML = items.map(item => `<li>${escapeHtml(item)}</li>`).join('');
    list.hidden = !items.length;
    dialog.querySelector('[data-manual-warning-confirm]').textContent = confirmLabel;
    dialog.classList.add('is-open');
    dialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');
    setTimeout(() => dialog.querySelector('[data-manual-warning-confirm]')?.focus(), 50);
    return new Promise(resolve => { manualWarningResolver = resolve; });
  }

  function ensureManualCalendarDialog() {
    let dialog = document.getElementById('gd-manual-calendar-dialog');
    if (dialog) return dialog;
    dialog = document.createElement('div');
    dialog.id = 'gd-manual-calendar-dialog';
    dialog.className = 'gd-manual-calendar-dialog';
    dialog.setAttribute('aria-hidden', 'true');
    dialog.innerHTML = `
      <button type="button" class="gd-manual-calendar-backdrop" data-close-manual-calendar aria-label="Kalender schließen"></button>
      <section class="gd-manual-calendar-panel" role="dialog" aria-modal="true" aria-labelledby="gd-manual-calendar-title">
        <header><div><span class="gd-eyebrow">Manuelle Reservierung</span><h2 id="gd-manual-calendar-title">Datum wählen</h2></div><button type="button" class="gd-table-picker-close" data-close-manual-calendar aria-label="Schließen">×</button></header>
        <div class="gd-manual-calendar-nav"><button type="button" data-manual-calendar-prev aria-label="Vorheriger Monat">‹</button><strong data-manual-calendar-month></strong><button type="button" data-manual-calendar-next aria-label="Nächster Monat">›</button></div>
        <div class="gd-manual-calendar-weekdays"><span>MO</span><span>DI</span><span>MI</span><span>DO</span><span>FR</span><span>SA</span><span>SO</span></div>
        <div class="gd-manual-calendar-grid" data-manual-calendar-grid></div>
        <p class="gd-manual-calendar-help"><span></span> Ausgegraute Tage liegen außerhalb der regulären Öffnungszeiten, können nach Bestätigung aber trotzdem gewählt werden.</p>
      </section>`;
    document.body.appendChild(dialog);
    dialog.querySelectorAll('[data-close-manual-calendar]').forEach(button => button.addEventListener('click', closeManualCalendar));
    dialog.querySelector('[data-manual-calendar-prev]')?.addEventListener('click', () => { manualCalendarMonth = new Date(manualCalendarMonth.getFullYear(), manualCalendarMonth.getMonth() - 1, 1); renderManualCalendar(); });
    dialog.querySelector('[data-manual-calendar-next]')?.addEventListener('click', () => { manualCalendarMonth = new Date(manualCalendarMonth.getFullYear(), manualCalendarMonth.getMonth() + 1, 1); renderManualCalendar(); });
    return dialog;
  }

  function renderManualCalendar() {
    const dialog = ensureManualCalendarDialog();
    const grid = dialog.querySelector('[data-manual-calendar-grid]');
    const label = dialog.querySelector('[data-manual-calendar-month]');
    const selected = manualParseDate(manualDate?.value || '');
    const today = manualToday();
    if (!manualCalendarMonth) manualCalendarMonth = new Date((selected || today).getFullYear(), (selected || today).getMonth(), 1);
    label.textContent = `${manualMonthNames[manualCalendarMonth.getMonth()]} ${manualCalendarMonth.getFullYear()}`;
    const first = new Date(manualCalendarMonth.getFullYear(), manualCalendarMonth.getMonth(), 1);
    const mondayIndex = (first.getDay() + 6) % 7;
    const gridStart = manualAddDays(first, -mondayIndex);
    const buttons = [];
    for (let i = 0; i < 42; i += 1) {
      const day = manualAddDays(gridStart, i);
      const past = day < today;
      const regular = isManualRegularDate(day);
      const other = day.getMonth() !== manualCalendarMonth.getMonth();
      const current = manualSameDay(day, selected);
      buttons.push(`<button type="button" class="gd-manual-calendar-day${other ? ' is-other-month' : ''}${!regular ? ' is-unavailable' : ''}${current ? ' is-selected' : ''}${manualSameDay(day, today) ? ' is-today' : ''}" data-manual-calendar-date="${manualDateKey(day)}" ${past ? 'disabled' : ''} aria-label="${manualDisplayDate(day)}${regular ? '' : ' – außerhalb Öffnungszeiten'}">${day.getDate()}</button>`);
    }
    grid.innerHTML = buttons.join('');
    grid.querySelectorAll('[data-manual-calendar-date]').forEach(button => button.addEventListener('click', async () => {
      const date = manualParseDate(button.dataset.manualCalendarDate);
      if (!date) return;
      const regular = isManualRegularDate(date);
      if (!regular) {
        const proceed = await manualWarningConfirm({
          title: 'Tag außerhalb der Öffnungszeiten',
          message: `${manualDisplayDate(date)} ist laut Reservierungseinstellungen nicht regulär verfügbar.`,
          items: ['Die Reservierung kann trotzdem manuell angelegt werden.', 'Bitte Öffnung und Personalbesetzung vorher prüfen.'],
          confirmLabel: 'Tag trotzdem wählen'
        });
        if (!proceed) return;
      }
      setManualDate(date, !regular);
      closeManualCalendar();
    }));
  }

  function openManualCalendar() {
    const selected = manualParseDate(manualDate?.value || '') || manualToday();
    manualCalendarMonth = new Date(selected.getFullYear(), selected.getMonth(), 1);
    const dialog = ensureManualCalendarDialog();
    renderManualCalendar();
    dialog.classList.add('is-open');
    dialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');
  }

  function closeManualCalendar() {
    const dialog = document.getElementById('gd-manual-calendar-dialog');
    if (!dialog) return;
    dialog.classList.remove('is-open');
    dialog.setAttribute('aria-hidden', 'true');
    if (!manualDialog?.classList.contains('is-open')) document.body.classList.remove('gd-dialog-open');
  }

  function setManualDate(date, outsideSchedule = false) {
    if (!manualDate || !date) return;
    manualDate.value = manualDateKey(date);
    manualDateOverride = Boolean(outsideSchedule);
    if (manualDateLabel) manualDateLabel.textContent = manualDisplayDate(date);
    loadManualSlots();
  }

  function invalidateManualTableSelection() {
    if (!manualEditingId || !manualTableValue?.value) {
      resetManualTableSelection();
      return;
    }
    manualSelectedTableInfo = null;
    if (manualAllowOccupied) manualAllowOccupied.value = '0';
    if (manualTableLabel) manualTableLabel.textContent = `Tisch ${manualTableValue.value}`;
    manualTableButton?.classList.remove('is-occupied');
  }

  function updateManualCustomTimeVisibility() {
    if (!manualCustomTime || !manualTime) return;
    const custom = manualTime.value === '__custom__';
    manualCustomTime.hidden = !custom;
    if (!custom) manualCustomTime.value = '';
    if (custom) setTimeout(() => manualCustomTime.focus(), 30);
    invalidateManualTableSelection();
  }

  function getManualEffectiveTime() {
    return manualTime?.value === '__custom__' ? (manualCustomTime?.value || '') : (manualTime?.value || '');
  }

  function resetManualTableSelection() {
    manualSelectedTableInfo = null;
    if (manualTableValue) manualTableValue.value = '';
    if (manualAllowOccupied) manualAllowOccupied.value = '0';
    if (manualTableLabel) manualTableLabel.textContent = 'Kein Tisch ausgewählt';
    manualTableButton?.classList.remove('has-selection', 'is-occupied');
  }

  function setManualTableSelection(value, info = null, allowOccupied = false) {
    const table = String(value || '').trim();
    manualSelectedTableInfo = info || null;
    if (manualTableValue) manualTableValue.value = table;
    if (manualAllowOccupied) manualAllowOccupied.value = allowOccupied ? '1' : '0';
    if (manualTableLabel) {
      manualTableLabel.textContent = table
        ? `Tisch ${table}${info?.occupiedSeats > 0 ? ' – gemeinsam belegt' : ''}`
        : 'Kein Tisch ausgewählt';
    }
    manualTableButton?.classList.toggle('has-selection', Boolean(table));
    manualTableButton?.classList.toggle('is-occupied', Boolean(table && info?.occupiedSeats > 0));
  }

  async function loadManualSlots() {
    if (!manualDate || !manualParty || !manualTime) return;
    const date = manualDate.value;
    const party = Math.max(1, Number(manualParty.value || 1));
    const sequence = ++manualSlotsRequest;
    invalidateManualTableSelection();
    manualAvailableSlots = [];
    if (manualCustomTime) { manualCustomTime.hidden = true; manualCustomTime.value = ''; }

    manualTime.disabled = true;
    manualTime.innerHTML = '<option value="">Zeiten werden geladen …</option>';
    if (manualSlotsStatus) manualSlotsStatus.textContent = '';
    if (!date) {
      manualTime.innerHTML = '<option value="">Datum wählen</option>';
      return;
    }

    try {
      const data = await request('gd_get_manual_booking_slots', { date, party, bookingId: manualEditingId || '' });
      if (sequence !== manualSlotsRequest) return;
      manualAvailableSlots = Array.isArray(data.slots) ? data.slots : [];
      const regularOptions = manualAvailableSlots.map(slot => `<option value="${escapeHtml(slot)}">${escapeHtml(slot)} Uhr</option>`).join('');
      manualTime.innerHTML = '<option value="">Uhrzeit wählen</option>' + regularOptions + '<option value="__custom__">Andere Uhrzeit manuell eingeben …</option>';
      manualTime.disabled = false;
      if (manualDesiredTime) {
        if (manualAvailableSlots.includes(manualDesiredTime)) {
          manualTime.value = manualDesiredTime;
          if (manualCustomTime) { manualCustomTime.hidden = true; manualCustomTime.value = ''; }
        } else {
          manualTime.value = '__custom__';
          if (manualCustomTime) { manualCustomTime.hidden = false; manualCustomTime.value = manualDesiredTime; }
        }
        manualDesiredTime = '';
      }
      if (manualSlotsStatus) {
        manualSlotsStatus.textContent = manualAvailableSlots.length
          ? `${manualAvailableSlots.length} reguläre ${manualAvailableSlots.length === 1 ? 'Uhrzeit' : 'Uhrzeiten'} – andere Zeiten sind mit Hinweis möglich.`
          : 'Keine reguläre Uhrzeit verfügbar – eine manuelle Ausnahme ist trotzdem möglich.';
      }
    } catch (error) {
      if (sequence !== manualSlotsRequest) return;
      manualTime.innerHTML = '<option value="">Uhrzeit wählen</option><option value="__custom__">Andere Uhrzeit manuell eingeben …</option>';
      manualTime.disabled = false;
      if (manualDesiredTime) {
        manualTime.value = '__custom__';
        if (manualCustomTime) { manualCustomTime.hidden = false; manualCustomTime.value = manualDesiredTime; }
        manualDesiredTime = '';
      }
      if (manualSlotsStatus) manualSlotsStatus.textContent = 'Reguläre Uhrzeiten konnten nicht geladen werden. Eine manuelle Zeit ist mit Hinweis möglich.';
    }
  }

  function setManualDialogMode(booking = null) {
    const editing = Boolean(booking?.id);
    manualEditingId = editing ? Number(booking.id) : 0;
    if (manualBookingId) manualBookingId.value = editing ? String(booking.id) : '';
    if (manualEyebrow) manualEyebrow.textContent = editing ? 'Bestehende Reservierung' : 'Telefonisch / persönlich';
    if (manualTitle) manualTitle.textContent = editing ? 'Reservierung bearbeiten' : 'Reservierung hinzufügen';
    if (manualDescription) manualDescription.textContent = editing
      ? 'Alle vorhandenen Daten sind bereits eingetragen. Ändern Sie nur die gewünschten Angaben.'
      : 'Der Eintrag wird direkt mit Gelsensystem und WordPress synchronisiert.';
    if (manualSubmit) manualSubmit.textContent = editing ? 'Änderungen speichern' : 'Reservierung speichern';
  }

  function ensureManualStatusOption(booking) {
    if (!manualStatus || !booking?.status) return;
    if (![...manualStatus.options].some(option => option.value === booking.status)) {
      const option = document.createElement('option');
      option.value = booking.status;
      option.textContent = booking.statusLabel || booking.status;
      option.dataset.dynamicStatus = '1';
      manualStatus.append(option);
    }
  }

  function openManualDialog(booking = null) {
    if (!manualDialog || !manualForm) return;
    closeMore();
    manualForm.reset();
    manualStatus?.querySelectorAll('[data-dynamic-status]').forEach(option => option.remove());
    setManualDialogMode(booking);

    if (booking?.id) {
      ensureManualStatusOption(booking);
      const date = manualParseDate(booking.dateValue || '');
      if (date && manualDate) {
        manualDate.value = manualDateKey(date);
        if (manualDateLabel) manualDateLabel.textContent = manualDisplayDate(date);
        manualCalendarMonth = new Date(date.getFullYear(), date.getMonth(), 1);
        manualDateOverride = Boolean(booking.outsideHours) || !isManualRegularDate(date);
      }
      if (manualParty) manualParty.value = String(Math.max(1, Number(booking.party || 1)));
      if (manualName) manualName.value = booking.name || '';
      if (manualPhone) manualPhone.value = booking.phone || '';
      if (manualEmail) manualEmail.value = booking.email || '';
      if (manualStatus) manualStatus.value = booking.status || 'confirmed';
      if (manualGuestMessage) manualGuestMessage.value = booking.message || '';
      if (manualInternalComment) manualInternalComment.value = booking.internalComment || '';
      setManualTableSelection(booking.tableNumber || '', null, false);
      manualDesiredTime = booking.time || '';
    } else {
      const initialDate = nextManualRegularDate(manualToday());
      if (manualDate) manualDate.value = manualDateKey(initialDate);
      if (manualDateLabel) manualDateLabel.textContent = manualDisplayDate(initialDate);
      manualDateOverride = !isManualRegularDate(initialDate);
      manualCalendarMonth = new Date(initialDate.getFullYear(), initialDate.getMonth(), 1);
      if (manualParty) manualParty.value = '2';
      if (manualStatus) manualStatus.value = 'confirmed';
      manualDesiredTime = '';
      resetManualTableSelection();
    }

    if (manualFeedback) {
      manualFeedback.textContent = '';
      manualFeedback.classList.remove('is-error', 'is-success');
    }
    manualDialog.classList.add('is-open');
    manualDialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');
    loadManualSlots();
    setTimeout(() => (booking?.id ? manualTime : manualDateButton)?.focus(), 100);
  }

  function closeManualDialog() {
    if (!manualDialog) return;
    manualDialog.classList.remove('is-open');
    manualDialog.setAttribute('aria-hidden', 'true');
    manualEditingId = 0;
    manualDesiredTime = '';
    if (!tablePickerDialog?.classList.contains('is-open') && !tableConflictDialog?.classList.contains('is-open')) {
      document.body.classList.remove('gd-dialog-open');
    }
  }

  async function openManualTablePicker() {
    if (!manualDialog || !tablePickerDialog || !manualDate || !manualTime || !manualParty) return;
    const date = manualDate.value;
    const time = getManualEffectiveTime();
    const party = Math.max(1, Number(manualParty.value || 1));
    if (!date || !time) {
      if (manualFeedback) {
        manualFeedback.textContent = 'Bitte zuerst Datum und Uhrzeit auswählen.';
        manualFeedback.classList.add('is-error');
      }
      return;
    }

    tablePickerContext = {
      manual: true,
      triggerButton: manualTableButton,
      tables: [],
      selectedValue: manualTableValue?.value || ''
    };
    if (tablePickerBookingName) tablePickerBookingName.textContent = manualName?.value.trim() || 'Neue Reservierung';
    tablePickerGrid.innerHTML = '<div class="gd-table-picker-loading">Tischbelegung wird geprüft …</div>';
    tablePickerDialog.classList.add('is-open');
    tablePickerDialog.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gd-dialog-open');

    try {
      const data = await request('gd_get_manual_table_availability', {
        date,
        time,
        party,
        bookingId: manualEditingId || '',
        selectedTable: manualTableValue?.value || ''
      });
      if (!tablePickerContext?.manual) return;
      tablePickerContext.tables = Array.isArray(data.tables) ? data.tables : [];
      buildTablePicker(tablePickerContext.tables, tablePickerContext.selectedValue);
      const selected = tablePickerGrid?.querySelector('.is-selected');
      setTimeout(() => (selected || tablePickerGrid?.querySelector('button'))?.focus(), 70);
    } catch (error) {
      tablePickerGrid.innerHTML = `<div class="gd-table-picker-error">${escapeHtml(error.message || 'Tischbelegung konnte nicht geladen werden.')}</div>`;
    }
  }

  async function submitManualBooking(event) {
    event.preventDefault();
    if (!manualForm || !manualSubmit) return;
    if (!manualForm.reportValidity()) return;

    const date = manualDate?.value || '';
    const time = getManualEffectiveTime();
    const phone = manualPhone?.value.trim() || '';
    const email = manualEmail?.value.trim() || '';
    if (!date) {
      manualFeedback.textContent = 'Bitte ein Datum auswählen.';
      manualFeedback.classList.add('is-error');
      manualDateButton?.focus();
      return;
    }
    if (!time) {
      manualFeedback.textContent = 'Bitte eine Uhrzeit auswählen oder manuell eingeben.';
      manualFeedback.classList.add('is-error');
      (manualTime?.value === '__custom__' ? manualCustomTime : manualTime)?.focus();
      return;
    }

    const missingContactFields = [];
    if (!phone) missingContactFields.push('Telefonnummer');
    if (!email) missingContactFields.push('E-Mail-Adresse');
    const hasMissingContact = missingContactFields.length > 0;
    const outsideHours = manualDateOverride || !manualAvailableSlots.includes(time);
    const warnings = [];
    if (outsideHours) warnings.push(`Datum/Uhrzeit (${manualDateLabel?.textContent || date}, ${time} Uhr) liegt außerhalb der regulären Verfügbarkeit.`);
    if (hasMissingContact) warnings.push(`${missingContactFields.join(' und ')} ${missingContactFields.length === 1 ? 'wurde' : 'wurden'} nicht eingetragen.`);

    if (warnings.length) {
      const proceed = await manualWarningConfirm({
        title: warnings.length > 1 ? 'Ausnahmen bestätigen' : 'Hinweis bestätigen',
        message: 'Die Reservierung kann gespeichert werden, obwohl folgende Angaben von den normalen Regeln abweichen:',
        items: warnings,
        confirmLabel: 'Reservierung trotzdem speichern'
      });
      if (!proceed) return;
    }

    manualSubmit.disabled = true;
    manualSubmit.textContent = 'Wird gespeichert …';
    if (manualFeedback) {
      manualFeedback.textContent = '';
      manualFeedback.classList.remove('is-error', 'is-success');
    }

    try {
      const action = manualEditingId ? 'gd_update_manual_booking' : 'gd_create_manual_booking';
      const data = await request(action, {
        bookingId: manualEditingId || '',
        date,
        time,
        party: manualParty?.value || '1',
        name: manualName?.value.trim() || '',
        phone,
        email,
        status: manualStatus?.value || 'confirmed',
        tableNumber: manualTableValue?.value || '',
        allowOccupied: manualAllowOccupied?.value || '0',
        allowOutsideHours: outsideHours ? '1' : '0',
        allowNoContact: hasMissingContact ? '1' : '0',
        guestMessage: manualGuestMessage?.value.trim() || '',
        internalComment: manualInternalComment?.value.trim() || ''
      });
      haptic();
      const wasEditing = Boolean(manualEditingId);
      closeManualDialog();
      showToast(data.message || (wasEditing ? 'Reservierung aktualisiert.' : 'Reservierung gespeichert.'));
      if (wasEditing) {
        loadBookings({ quiet: true, preserveExpanded: true, notify: false });
      } else {
        const targetView = (manualStatus?.value || 'confirmed') === 'pending'
          ? 'pending'
          : (date === String(GDReservations.today || '') ? 'today' : 'upcoming');
        setView(targetView);
      }
    } catch (error) {
      if (manualFeedback) {
        manualFeedback.textContent = error.message || 'Reservierung konnte nicht gespeichert werden.';
        manualFeedback.classList.add('is-error');
      }
      if (error.data?.conflict) {
        manualAllowOccupied.value = '0';
        manualSelectedTableInfo = error.data.conflict;
      }
    } finally {
      manualSubmit.disabled = false;
      manualSubmit.textContent = manualEditingId ? 'Änderungen speichern' : 'Reservierung speichern';
    }
  }

  function setView(view, { closeSheet = false } = {}) {
    currentView = view;
    syncViewUI();
    if (closeSheet) closeMore();
    loadBookings();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function updateNetworkStatus(event) {
    const online = navigator.onLine;
    if (networkBanner) {
      networkBanner.hidden = online;
      networkBanner.textContent = GDReservations.offline || 'Keine Internetverbindung.';
    }
    if (event && online) showToast(GDReservations.online || 'Verbindung wiederhergestellt.');
    if (event && !online) showToast(GDReservations.offline || 'Keine Internetverbindung.', true);
  }

  list.addEventListener('click', event => {
    const card = event.target.closest('.gd-booking-card');
    if (!card) return;
    const button = event.target.closest('button');

    if (button?.matches('[data-edit-booking]')) {
      const booking = bookingsById.get(String(card.dataset.bookingId));
      if (booking) openManualDialog(booking);
      return;
    }
    if (button?.matches('[data-open-table-picker]')) {
      openTablePicker(card, button);
      return;
    }
    if (button?.matches('[data-toggle-card]')) {
      toggleCard(card);
      return;
    }
    if (!button) return;
    if (button.dataset.status) updateStatus(card, button.dataset.status, button);
    else if (button.dataset.saveTable) updateTableNumber(card, button);
    else if (button.dataset.saveComment) updateInternalComment(card, button);
    else if (button.dataset.restore) restoreBooking(card, button);
    else if (button.dataset.deletePermanently) deleteBookingPermanently(card, button);
    else if (button.dataset.delete) openRemovalDialog(card, button);
  });

  list.addEventListener('keydown', event => {
    if (event.key === 'Enter' && event.target.matches('.gd-table-assignment input')) {
      event.preventDefault();
      const card = event.target.closest('.gd-booking-card');
      const button = card?.querySelector('[data-save-table]');
      if (card && button) updateTableNumber(card, button);
    }
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && event.target.matches('.gd-comment-assignment textarea')) {
      event.preventDefault();
      const card = event.target.closest('.gd-booking-card');
      const button = card?.querySelector('[data-save-comment]');
      if (card && button) updateInternalComment(card, button);
    }
  });

  viewButtons.forEach(button => button.addEventListener('click', () => {
    setView(button.dataset.view, { closeSheet: button.hasAttribute('data-close-on-select') });
  }));

  search.addEventListener('input', () => {
    searchClear.hidden = !search.value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadBookings(), 280);
  });
  searchClear?.addEventListener('click', () => {
    search.value = '';
    searchClear.hidden = true;
    search.focus();
    loadBookings();
  });

  refreshButtons.forEach(button => button.addEventListener('click', () => loadBookings({ quiet: true })));
  autoConfirmInputs.forEach(input => input.addEventListener('change', () => updateAutoConfirm(input)));
  refreshIntervalInputs.forEach(input => input.addEventListener('change', () => updateRefreshInterval(input)));
  whatsappTemplateSaveButtons.forEach(button => button.addEventListener('click', updateWhatsAppTemplate));
  tableCountSaveButtons.forEach(button => button.addEventListener('click', updateTableCount));
  tableCapacitySaveButtons.forEach(button => button.addEventListener('click', updateTableCapacitySettings));
  exportCsvButtons.forEach(button => button.addEventListener('click', () => exportBookings(GDReservations.csvExportUrl)));
  exportXlsxButtons.forEach(button => button.addEventListener('click', () => exportBookings(GDReservations.xlsxExportUrl)));
  moreOpeners.forEach(button => button.addEventListener('click', openMore));
  moreClosers.forEach(button => button.addEventListener('click', closeMore));
  manualOpeners.forEach(button => button.addEventListener('click', () => openManualDialog()));
  manualClosers.forEach(button => button.addEventListener('click', closeManualDialog));
  manualForm?.addEventListener('submit', submitManualBooking);
  manualDateButton?.addEventListener('click', openManualCalendar);
  manualParty?.addEventListener('change', loadManualSlots);
  manualParty?.addEventListener('input', () => {
    clearTimeout(manualParty._gdTimer);
    manualParty._gdTimer = setTimeout(loadManualSlots, 250);
  });
  manualTime?.addEventListener('change', updateManualCustomTimeVisibility);
  manualCustomTime?.addEventListener('change', invalidateManualTableSelection);
  manualCustomTime?.addEventListener('input', invalidateManualTableSelection);
  manualTableButton?.addEventListener('click', openManualTablePicker);
  tablePickerCloseButtons.forEach(button => button.addEventListener('click', () => closeTablePicker()));
  tablePickerDialog?.addEventListener('click', event => {
    const button = event.target.closest('[data-table-value]');
    if (!button) return;
    const value = button.dataset.tableValue || '';
    if (!value) {
      selectQuickTable('', button);
      return;
    }
    const info = tablePickerContext?.tables?.find(table => String(table.number) === String(value));
    if (info && Number(info.occupiedSeats || 0) > 0) {
      openTableConflict(info, button);
    } else {
      selectQuickTable(value, button);
    }
  });
  tableConflictCloseButtons.forEach(button => button.addEventListener('click', () => closeTableConflict()));
  tableConflictConfirm?.addEventListener('click', () => {
    const context = tableConflictContext;
    if (!context) return;
    selectQuickTable(String(context.info.number || ''), context.button, true);
  });
  removalCloseButtons.forEach(button => button.addEventListener('click', () => closeRemovalDialog()));
  removalCancelBookingButton?.addEventListener('click', cancelBookingFromDialog);
  removalTrashButton?.addEventListener('click', moveBookingToTrashFromDialog);
  document.addEventListener('click', event => {
    if (event.target.closest('[data-close-more]')) {
      event.preventDefault();
      closeMore();
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (document.getElementById('gd-manual-warning-dialog')?.classList.contains('is-open')) closeManualWarning(false);
    else if (document.getElementById('gd-manual-calendar-dialog')?.classList.contains('is-open')) closeManualCalendar();
    else if (tableConflictDialog?.classList.contains('is-open')) closeTableConflict();
    else if (tablePickerDialog?.classList.contains('is-open')) closeTablePicker();
    else if (manualDialog?.classList.contains('is-open')) closeManualDialog();
    closeRemovalDialog();
    closeMore();
  });

  let sheetTouchStartY = 0;
  let sheetTouchDeltaY = 0;
  const moreSheet = moreLayer?.querySelector('.gd-more-sheet');
  moreSheet?.addEventListener('touchstart', event => {
    sheetTouchStartY = event.touches[0]?.clientY || 0;
    sheetTouchDeltaY = 0;
  }, { passive: true });
  moreSheet?.addEventListener('touchmove', event => {
    sheetTouchDeltaY = (event.touches[0]?.clientY || 0) - sheetTouchStartY;
  }, { passive: true });
  moreSheet?.addEventListener('touchend', () => {
    if (sheetTouchDeltaY > 90 && moreSheet.scrollTop <= 0) closeMore();
    sheetTouchStartY = 0;
    sheetTouchDeltaY = 0;
  }, { passive: true });

  themeButtons.forEach(button => button.addEventListener('click', () => {
    applyTheme(selectedTheme === 'dark' ? 'light' : 'dark', true);
    haptic();
  }));
  themeSwitches.forEach(input => input.addEventListener('change', () => {
    applyTheme(input.checked ? 'dark' : 'light', true);
    haptic();
  }));
  systemDarkMode.addEventListener?.('change', event => {
    if (!storedTheme()) applyTheme(event.matches ? 'dark' : 'light');
  });

  document.addEventListener('gesturestart', event => event.preventDefault(), { passive: false });
  document.addEventListener('gesturechange', event => event.preventDefault(), { passive: false });

  window.addEventListener('resize', updateAppViewportMetrics, { passive: true });
  window.visualViewport?.addEventListener('resize', updateAppViewportMetrics, { passive: true });
  window.visualViewport?.addEventListener('scroll', updateAppViewportMetrics, { passive: true });

  window.addEventListener('online', updateNetworkStatus);
  window.addEventListener('offline', updateNetworkStatus);
  updateNetworkStatus();

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (installButton) installButton.hidden = false;
  });

  installButton?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installButton.hidden = true;
  });

  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  if (isIos && !isStandalone && iosInstallHint) iosInstallHint.hidden = false;

  if ('serviceWorker' in navigator && GDReservations.pwaServiceWorker) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(GDReservations.pwaServiceWorker, {
        scope: GDReservations.pwaScope || '/gelsensystem/'
      }).catch(() => {});
    });
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible' || !navigator.onLine) return;
    const editing = document.activeElement?.matches('input,textarea');
    if (!editing && Date.now() - lastLoadedAt > 45000) loadBookings({ quiet: true });
  });

  initializeTheme();
  updateAppViewportMetrics();
  initializePullToRefresh();
  scheduleAutoRefresh();
  syncViewUI();
  setLoadingState(false);
  loadBookings();
})();
