(() => {
  'use strict';

  const config = window.GDG_CONFIG || {};
  const app = document.querySelector('.gdg-app');
  if (!app) return;

  const screen = app.querySelector('.gdg-screen');
  const loading = app.querySelector('.gdg-loading');
  const connection = app.querySelector('.gdg-connection');
  const state = {
    bootstrap: null,
    selectedTableId: Number(config.prefill?.tableId || 0),
    selectedCategoryId: 0,
    menuSearch: '',
    selectedOrderId: 0,
    queue: [],
    paySelection: {},
    busy: false,
  };

  const esc = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const money = (value) => new Intl.NumberFormat('de-AT', {
    style: 'currency', currency: 'EUR'
  }).format(Number(value || 0));

  const time = (value) => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('de-AT', { hour: '2-digit', minute: '2-digit' }).format(date);
  };

  const statusLabel = (status) => ({
    new: 'Neu', preparing: 'In Zubereitung', ready: 'Fertig', served: 'Serviert',
    cancelled: 'Storniert', open: 'Offen', ready_for_payment: 'Zahlung offen', closed: 'Bezahlt'
  }[status] || status);

  const stationLabel = (station) => station === 'bar' ? 'Schank' : 'Küche';

  function setConnection(ok) {
    if (!connection) return;
    connection.classList.toggle('is-offline', !ok);
    const label = connection.querySelector('span:last-child');
    if (label) label.textContent = ok ? 'Online' : 'Offline';
  }

  async function api(path, options = {}) {
    const init = {
      method: options.method || 'GET',
      headers: {
        'X-WP-Nonce': config.nonce || '',
        'Content-Type': 'application/json',
        ...(options.headers || {})
      },
      credentials: 'same-origin'
    };
    if (options.body !== undefined) init.body = JSON.stringify(options.body);

    try {
      const response = await fetch(`${config.restUrl}${path}`, init);
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.message || config.labels?.error || 'Fehler');
      }
      setConnection(true);
      return payload;
    } catch (error) {
      setConnection(false);
      throw error;
    }
  }

  function toast(message, type = 'success') {
    const region = app.querySelector('.gdg-toast-region');
    if (!region) return;
    const el = document.createElement('div');
    el.className = `gdg-toast is-${type}`;
    el.textContent = message;
    region.appendChild(el);
    requestAnimationFrame(() => el.classList.add('is-visible'));
    window.setTimeout(() => {
      el.classList.remove('is-visible');
      window.setTimeout(() => el.remove(), 250);
    }, 3200);
  }

  function setBusy(value) {
    state.busy = value;
    app.classList.toggle('is-busy', value);
  }

  function setupTheme() {
    const stored = localStorage.getItem('gdg-theme') || 'auto';
    applyTheme(stored);
    app.querySelector('[data-gdg-theme-toggle]')?.addEventListener('click', () => {
      const current = app.dataset.theme || 'auto';
      const next = current === 'dark' ? 'light' : 'dark';
      localStorage.setItem('gdg-theme', next);
      applyTheme(next);
    });
  }

  function applyTheme(theme) {
    app.dataset.theme = theme;
  }

  function getOrders() {
    return state.bootstrap?.orders || [];
  }

  function getOrderByTable(tableId) {
    return getOrders().find((order) => Number(order.table_id) === Number(tableId)) || null;
  }

  function getOrder(orderId) {
    return getOrders().find((order) => Number(order.id) === Number(orderId)) || null;
  }

  async function refreshBootstrap(preserve = true) {
    const selectedTable = state.selectedTableId;
    const selectedOrder = state.selectedOrderId;
    state.bootstrap = await api('bootstrap');
    if (preserve) {
      state.selectedTableId = selectedTable;
      state.selectedOrderId = selectedOrder;
    }
  }

  function showScreen() {
    loading.hidden = true;
    screen.hidden = false;
  }

  async function init() {
    setupTheme();
    try {
      await refreshBootstrap(false);
      showScreen();
      switch (config.view) {
        case 'kitchen':
          await loadQueue('kitchen');
          startQueuePolling('kitchen');
          break;
        case 'bar':
          await loadQueue('bar');
          startQueuePolling('bar');
          break;
        case 'checkout':
          initCheckoutSelection();
          renderCheckout();
          startOrdersPolling();
          break;
        case 'service':
        default:
          if (!state.selectedTableId && state.bootstrap.tables?.length) {
            state.selectedTableId = Number(state.bootstrap.tables[0].id);
          }
          renderService();
          startOrdersPolling();
          break;
      }
    } catch (error) {
      loading.innerHTML = `<div class="gdg-error-state"><strong>Daten konnten nicht geladen werden.</strong><p>${esc(error.message)}</p><button class="gdg-button" data-retry>Erneut versuchen</button></div>`;
      loading.querySelector('[data-retry]')?.addEventListener('click', () => window.location.reload());
    }
  }

  function startOrdersPolling() {
    window.setInterval(async () => {
      if (state.busy || document.hidden) return;
      try {
        await refreshBootstrap(true);
        if (config.view === 'checkout') {
          if (state.selectedOrderId && !getOrder(state.selectedOrderId)) {
            state.selectedOrderId = Number(getOrders()[0]?.id || 0);
            initCheckoutSelection();
          }
          renderCheckout();
        } else {
          renderService();
        }
      } catch (_) {}
    }, Number(config.pollInterval || 5000));
  }

  function renderService() {
    const tables = state.bootstrap?.tables || [];
    const order = getOrderByTable(state.selectedTableId);
    const categories = state.bootstrap?.categories || [];
    const menuItems = state.bootstrap?.menu_items || [];
    if (!state.selectedCategoryId && categories.length) {
      state.selectedCategoryId = Number(categories[0].id);
    }

    const reservationBanner = config.prefill?.reservationId ? `
      <div class="gdg-banner">
        <div><strong>Reservierung #${esc(config.prefill.reservationId)}</strong><span>${esc(config.prefill.guestName || 'Gast')} ${config.prefill.guestCount ? `· ${esc(config.prefill.guestCount)} Personen` : ''}</span></div>
        ${config.prefill.tableId ? '<span class="gdg-badge is-success">Tisch vorausgewählt</span>' : '<span class="gdg-badge is-warning">Bitte Tisch auswählen</span>'}
      </div>` : '';

    screen.innerHTML = `
      ${reservationBanner}
      <div class="gdg-service-layout">
        <section class="gdg-panel gdg-table-panel">
          <div class="gdg-panel-head"><div><span class="gdg-kicker">Tischplan</span><h2>Service</h2></div><span class="gdg-count">${tables.length} Tische</span></div>
          <div class="gdg-table-grid">
            ${tables.map((table) => {
              const tableOrder = getOrderByTable(table.id);
              const selected = Number(table.id) === Number(state.selectedTableId);
              return `<button type="button" class="gdg-table-card ${tableOrder ? 'is-occupied' : 'is-free'} ${selected ? 'is-selected' : ''}" data-table-id="${esc(table.id)}">
                <span class="gdg-table-status">${tableOrder ? 'Belegt' : 'Frei'}</span>
                <strong>${esc(table.name)}</strong>
                <small>${esc(table.area || '')} · ${esc(table.seats)} Plätze</small>
                ${tableOrder?.guest_name ? `<em>${esc(tableOrder.guest_name)}</em>` : ''}
              </button>`;
            }).join('')}
          </div>
        </section>

        <section class="gdg-panel gdg-menu-panel">
          <div class="gdg-panel-head"><div><span class="gdg-kicker">Bestellung</span><h2>Speisekarte</h2></div></div>
          ${renderMenu(categories, menuItems, !!order)}
        </section>

        <section class="gdg-panel gdg-order-panel">
          ${renderOrderPanel(order)}
        </section>
      </div>`;

    bindServiceEvents();
  }

  function renderMenu(categories, menuItems, enabled) {
    const filtered = menuItems.filter((item) => {
      const categoryMatch = Number(item.category_id) === Number(state.selectedCategoryId);
      const search = state.menuSearch.trim().toLowerCase();
      const searchMatch = !search || String(item.name).toLowerCase().includes(search) || String(item.description || '').toLowerCase().includes(search);
      return categoryMatch && searchMatch;
    });

    return `
      <div class="gdg-search-row"><input type="search" value="${esc(state.menuSearch)}" data-menu-search placeholder="Gericht oder Getränk suchen …"></div>
      <div class="gdg-tabs">
        ${categories.map((cat) => `<button type="button" class="${Number(cat.id) === Number(state.selectedCategoryId) ? 'is-active' : ''}" data-category-id="${esc(cat.id)}">${esc(cat.name)}</button>`).join('')}
      </div>
      <div class="gdg-menu-grid ${enabled ? '' : 'is-disabled'}">
        ${filtered.length ? filtered.map((item) => `<article class="gdg-menu-item">
          <button type="button" class="gdg-menu-main" data-add-item="${esc(item.id)}" ${enabled ? '' : 'disabled'}>
            <span class="gdg-station-dot is-${esc(item.station)}"></span>
            <div><strong>${esc(item.name)}</strong>${item.description ? `<small>${esc(item.description)}</small>` : ''}</div>
            <b>${money(item.price)}</b>
          </button>
          <button type="button" class="gdg-menu-note" data-add-note="${esc(item.id)}" ${enabled ? '' : 'disabled'}>+ Sonderwunsch</button>
        </article>`).join('') : '<div class="gdg-empty-small">Keine passenden Einträge.</div>'}
      </div>
      ${enabled ? '' : '<div class="gdg-inline-hint">Zuerst einen freien Tisch öffnen oder einen belegten Tisch auswählen.</div>'}`;
  }

  function renderOrderPanel(order) {
    const table = (state.bootstrap?.tables || []).find((row) => Number(row.id) === Number(state.selectedTableId));
    if (!table) {
      return '<div class="gdg-empty-state"><strong>Kein Tisch ausgewählt</strong><p>Bitte links einen Tisch auswählen.</p></div>';
    }
    if (!order) {
      return `<div class="gdg-panel-head"><div><span class="gdg-kicker">${esc(table.area || '')}</span><h2>${esc(table.name)}</h2></div><span class="gdg-badge is-free">Frei</span></div>
        <div class="gdg-open-table-form">
          <div class="gdg-empty-icon">🍽️</div>
          <h3>Tisch öffnen</h3>
          <label>Gastname<input type="text" data-open-guest value="${esc(config.prefill?.guestName || '')}" placeholder="optional"></label>
          <label>Personen<input type="number" min="0" max="99" data-open-count value="${esc(config.prefill?.guestCount || '')}" placeholder="0"></label>
          <button type="button" class="gdg-button is-primary is-large" data-open-table>Tisch belegen</button>
        </div>`;
    }

    const items = order.items || [];
    return `<div class="gdg-panel-head"><div><span class="gdg-kicker">${esc(order.table_area || '')}</span><h2>${esc(order.table_name || table.name)}</h2>${order.guest_name ? `<p>${esc(order.guest_name)}${Number(order.guest_count) ? ` · ${esc(order.guest_count)} Pers.` : ''}</p>` : ''}</div><span class="gdg-badge is-occupied">Belegt</span></div>
      <div class="gdg-order-items">
        ${items.length ? items.map((item) => renderServiceItem(item)).join('') : '<div class="gdg-empty-small">Noch keine Bestellung aufgenommen.</div>'}
      </div>
      <div class="gdg-order-summary">
        <div><span>Gesamt</span><strong>${money(order.total)}</strong></div>
        <div><span>Bereits bezahlt</span><strong>${money(order.paid)}</strong></div>
        <div class="is-open"><span>Offen</span><strong>${money(order.open_amount)}</strong></div>
      </div>
      <a class="gdg-button is-primary is-large is-full" href="${esc(state.bootstrap?.pages?.checkout || '#')}?order_id=${esc(order.id)}">Zur Abrechnung</a>`;
  }

  function renderServiceItem(item) {
    const cancelled = item.status === 'cancelled';
    const editable = Number(item.paid_quantity || 0) === 0 && !cancelled;
    return `<div class="gdg-order-item ${cancelled ? 'is-cancelled' : ''}">
      <div class="gdg-item-top"><div><strong>${esc(item.item_name)}</strong><span>${stationLabel(item.station)} · <i class="gdg-status is-${esc(item.status)}">${esc(statusLabel(item.status))}</i></span></div><b>${money(Number(item.unit_price) * Number(item.quantity))}</b></div>
      ${item.note ? `<p class="gdg-item-note">${esc(item.note)}</p>` : ''}
      <div class="gdg-item-actions">
        <div class="gdg-qty"><button type="button" data-item-qty="${esc(item.id)}" data-delta="-1" ${editable && Number(item.quantity) > 1 ? '' : 'disabled'}>−</button><span>${esc(item.quantity)}</span><button type="button" data-item-qty="${esc(item.id)}" data-delta="1" ${editable ? '' : 'disabled'}>+</button></div>
        ${Number(item.paid_quantity) ? `<span class="gdg-paid-mark">${esc(item.paid_quantity)} bezahlt</span>` : ''}
        ${item.status === 'ready' ? `<button type="button" class="gdg-link-success" data-serve-item="${esc(item.id)}">Serviert</button>` : ''}
        ${editable ? `<button type="button" class="gdg-link-danger" data-cancel-item="${esc(item.id)}">Stornieren</button>` : ''}
      </div>
    </div>`;
  }

  function bindServiceEvents() {
    screen.querySelectorAll('[data-table-id]').forEach((button) => {
      button.addEventListener('click', () => {
        state.selectedTableId = Number(button.dataset.tableId);
        renderService();
      });
    });

    screen.querySelectorAll('[data-category-id]').forEach((button) => {
      button.addEventListener('click', () => {
        state.selectedCategoryId = Number(button.dataset.categoryId);
        renderService();
      });
    });

    const search = screen.querySelector('[data-menu-search]');
    search?.addEventListener('input', (event) => {
      state.menuSearch = event.target.value;
      const position = event.target.selectionStart;
      renderService();
      const next = screen.querySelector('[data-menu-search]');
      next?.focus();
      next?.setSelectionRange(position, position);
    });

    screen.querySelector('[data-open-table]')?.addEventListener('click', openSelectedTable);

    screen.querySelectorAll('[data-add-item]').forEach((button) => {
      button.addEventListener('click', () => addMenuItem(Number(button.dataset.addItem), ''));
    });

    screen.querySelectorAll('[data-add-note]').forEach((button) => {
      button.addEventListener('click', () => {
        const note = window.prompt('Sonderwunsch oder Hinweis für Küche/Schank:');
        if (note !== null) addMenuItem(Number(button.dataset.addNote), note);
      });
    });

    screen.querySelectorAll('[data-item-qty]').forEach((button) => {
      button.addEventListener('click', async () => {
        const order = getOrderByTable(state.selectedTableId);
        const item = order?.items?.find((row) => Number(row.id) === Number(button.dataset.itemQty));
        if (!item) return;
        const quantity = Number(item.quantity) + Number(button.dataset.delta);
        await updateItem(item.id, { quantity });
      });
    });

    screen.querySelectorAll('[data-serve-item]').forEach((button) => {
      button.addEventListener('click', async () => {
        const ok = await updateItem(Number(button.dataset.serveItem), { status: 'served' });
        if (ok) toast('Position wurde als serviert markiert.');
      });
    });

    screen.querySelectorAll('[data-cancel-item]').forEach((button) => {
      button.addEventListener('click', async () => {
        if (!window.confirm(config.labels?.confirmCancel || 'Wirklich stornieren?')) return;
        await updateItem(Number(button.dataset.cancelItem), { status: 'cancelled' });
      });
    });
  }

  async function openSelectedTable() {
    if (!state.selectedTableId || state.busy) return;
    const guest = screen.querySelector('[data-open-guest]')?.value || '';
    const guestCount = Number(screen.querySelector('[data-open-count]')?.value || 0);
    setBusy(true);
    try {
      await api('orders', {
        method: 'POST',
        body: {
          table_id: state.selectedTableId,
          reservation_id: Number(config.prefill?.reservationId || 0),
          guest_name: guest,
          guest_count: guestCount
        }
      });
      await refreshBootstrap(true);
      renderService();
      toast('Tisch wurde geöffnet.');
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setBusy(false);
    }
  }

  async function addMenuItem(menuItemId, note) {
    const order = getOrderByTable(state.selectedTableId);
    if (!order || state.busy) return;
    setBusy(true);
    try {
      await api(`orders/${order.id}/items`, {
        method: 'POST', body: { menu_item_id: menuItemId, quantity: 1, note }
      });
      await refreshBootstrap(true);
      renderService();
      toast('Position wurde gesendet.');
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setBusy(false);
    }
  }

  async function updateItem(itemId, changes, rerender = true) {
    if (state.busy) return;
    setBusy(true);
    try {
      await api(`items/${itemId}`, { method: 'PATCH', body: changes });
      if (config.view === 'service' || config.view === 'checkout') {
        await refreshBootstrap(true);
        if (rerender) config.view === 'checkout' ? renderCheckout() : renderService();
      }
      return true;
    } catch (error) {
      toast(error.message, 'error');
      return false;
    } finally {
      setBusy(false);
    }
  }

  async function loadQueue(station) {
    state.queue = await api(`queue/${station}`);
    renderQueue(station);
  }

  function startQueuePolling(station) {
    window.setInterval(async () => {
      if (state.busy || document.hidden) return;
      try { await loadQueue(station); } catch (_) {}
    }, Number(config.pollInterval || 5000));
  }

  function renderQueue(station) {
    const label = station === 'bar' ? 'Schank' : 'Küche';
    const itemCount = state.queue.reduce((sum, order) => sum + order.items.length, 0);
    screen.innerHTML = `<div class="gdg-queue-head"><div><span class="gdg-kicker">Live-Monitor</span><h1>${label}</h1></div><div class="gdg-queue-stats"><span><b>${state.queue.length}</b> Tische</span><span><b>${itemCount}</b> Positionen</span></div></div>
      <div class="gdg-queue-grid">
        ${state.queue.length ? state.queue.map((order) => renderQueueCard(order)).join('') : `<div class="gdg-empty-state is-wide"><div class="gdg-empty-icon">✓</div><strong>Alles erledigt</strong><p>Zurzeit sind keine offenen ${label === 'Küche' ? 'Küchen-' : 'Schank-'}Bestellungen vorhanden.</p></div>`}
      </div>`;
    bindQueueEvents(station);
  }

  function renderQueueCard(order) {
    const oldest = order.items[0]?.created_at || order.opened_at;
    const hasNew = order.items.some((item) => item.status === 'new');
    const hasPreparing = order.items.some((item) => item.status === 'preparing');
    return `<article class="gdg-ticket ${hasNew ? 'has-new' : hasPreparing ? 'has-preparing' : 'has-ready'}">
      <header><div><span>${esc(order.table_name || `Tisch ${order.table_id}`)}</span>${order.guest_name ? `<small>${esc(order.guest_name)}</small>` : ''}</div><time>${esc(time(oldest))}</time></header>
      <div class="gdg-ticket-items">
        ${order.items.map((item) => `<div class="gdg-ticket-item is-${esc(item.status)}">
          <div><b>${esc(item.quantity)}×</b><strong>${esc(item.item_name)}</strong></div>
          ${item.note ? `<p>⚠ ${esc(item.note)}</p>` : ''}
          <div class="gdg-ticket-actions">
            ${item.status === 'new' ? `<button type="button" data-queue-status="${esc(item.id)}" data-status="preparing">Starten</button>` : ''}
            ${item.status === 'preparing' ? `<button type="button" class="is-primary" data-queue-status="${esc(item.id)}" data-status="ready">Fertig</button>` : ''}
            ${item.status === 'ready' ? `<span class="gdg-ready-label">Bereit zur Ausgabe</span>` : ''}
          </div>
        </div>`).join('')}
      </div>
    </article>`;
  }

  function bindQueueEvents(station) {
    screen.querySelectorAll('[data-queue-status]').forEach((button) => {
      button.addEventListener('click', async () => {
        const ok = await updateItem(Number(button.dataset.queueStatus), { status: button.dataset.status }, false);
        if (ok) {
          await loadQueue(station);
          toast(button.dataset.status === 'ready' ? 'Als fertig markiert.' : 'Zubereitung gestartet.');
        }
      });
    });
  }

  function initCheckoutSelection() {
    const urlOrder = Number(new URLSearchParams(window.location.search).get('order_id') || 0);
    if (urlOrder && getOrder(urlOrder)) state.selectedOrderId = urlOrder;
    if (!state.selectedOrderId) state.selectedOrderId = Number(getOrders()[0]?.id || 0);
    const order = getOrder(state.selectedOrderId);
    state.paySelection = {};
    order?.items?.forEach((item) => {
      const remaining = Math.max(0, Number(item.quantity) - Number(item.paid_quantity));
      if (remaining > 0 && item.status !== 'cancelled') state.paySelection[item.id] = remaining;
    });
  }

  function renderCheckout() {
    const orders = getOrders();
    const order = getOrder(state.selectedOrderId);
    screen.innerHTML = `<div class="gdg-checkout-layout">
      <section class="gdg-panel gdg-checkout-list">
        <div class="gdg-panel-head"><div><span class="gdg-kicker">Offene Rechnungen</span><h2>Kasse</h2></div><span class="gdg-count">${orders.length}</span></div>
        <div class="gdg-checkout-orders">
          ${orders.length ? orders.map((row) => `<button type="button" class="${Number(row.id) === Number(state.selectedOrderId) ? 'is-active' : ''}" data-checkout-order="${esc(row.id)}"><span><strong>${esc(row.table_name || `Tisch ${row.table_id}`)}</strong><small>${esc(row.guest_name || 'Ohne Name')}</small></span><b>${money(row.open_amount)}</b></button>`).join('') : '<div class="gdg-empty-small">Keine offenen Rechnungen.</div>'}
        </div>
      </section>
      <section class="gdg-panel gdg-checkout-main">
        ${renderCheckoutMain(order)}
      </section>
    </div>`;
    bindCheckoutEvents();
  }

  function renderCheckoutMain(order) {
    if (!order) return '<div class="gdg-empty-state"><div class="gdg-empty-icon">✓</div><strong>Keine offene Rechnung</strong><p>Nach vollständiger Bezahlung wird der Tisch automatisch freigegeben.</p></div>';
    const payable = (order.items || []).filter((item) => item.status !== 'cancelled' && Number(item.quantity) > Number(item.paid_quantity));
    const selectedAmount = payable.reduce((sum, item) => sum + Number(item.unit_price) * Number(state.paySelection[item.id] || 0), 0);
    return `<div class="gdg-panel-head"><div><span class="gdg-kicker">${esc(order.guest_name || 'Abrechnung')}</span><h2>${esc(order.table_name || `Tisch ${order.table_id}`)}</h2></div><span class="gdg-badge is-warning">${money(order.open_amount)} offen</span></div>
      <div class="gdg-checkout-tools"><button type="button" class="gdg-button is-small" data-select-all>Alles auswählen</button><button type="button" class="gdg-button is-small is-ghost" data-select-none>Auswahl leeren</button></div>
      <div class="gdg-payment-items">
        ${payable.length ? payable.map((item) => renderPaymentItem(item)).join('') : '<div class="gdg-empty-small">Keine offenen Positionen.</div>'}
      </div>
      <div class="gdg-payment-total"><span>Ausgewählter Betrag</span><strong>${money(selectedAmount)}</strong></div>
      <div class="gdg-payment-methods">
        <button type="button" class="gdg-payment-button is-cash" data-pay-method="cash" ${selectedAmount > 0 ? '' : 'disabled'}><span>💶</span><strong>Bar bezahlen</strong><small>${money(selectedAmount)}</small></button>
        <button type="button" class="gdg-payment-button is-card" data-pay-method="card" ${selectedAmount > 0 ? '' : 'disabled'}><span>💳</span><strong>Karte</strong><small>manuell am Terminal</small></button>
      </div>
      <p class="gdg-legal-note">Interne Zahlungsübersicht – noch kein Registrierkassen- oder RKSV-Beleg.</p>`;
  }

  function renderPaymentItem(item) {
    const remaining = Number(item.quantity) - Number(item.paid_quantity);
    const selected = Number(state.paySelection[item.id] || 0);
    return `<div class="gdg-payment-item ${selected ? 'is-selected' : ''}">
      <label><input type="checkbox" data-pay-check="${esc(item.id)}" ${selected ? 'checked' : ''}><span><strong>${esc(item.item_name)}</strong><small>${money(item.unit_price)} je Stück · ${remaining} offen${Number(item.paid_quantity) ? ` · ${esc(item.paid_quantity)} bezahlt` : ''}</small></span></label>
      <div class="gdg-qty"><button type="button" data-pay-qty="${esc(item.id)}" data-delta="-1" ${selected > 0 ? '' : 'disabled'}>−</button><span>${selected}</span><button type="button" data-pay-qty="${esc(item.id)}" data-delta="1" ${selected < remaining ? '' : 'disabled'}>+</button></div>
      <b>${money(Number(item.unit_price) * selected)}</b>
    </div>`;
  }

  function bindCheckoutEvents() {
    screen.querySelectorAll('[data-checkout-order]').forEach((button) => {
      button.addEventListener('click', () => {
        state.selectedOrderId = Number(button.dataset.checkoutOrder);
        initCheckoutSelection();
        renderCheckout();
      });
    });

    screen.querySelector('[data-select-all]')?.addEventListener('click', () => {
      const order = getOrder(state.selectedOrderId);
      state.paySelection = {};
      order?.items?.forEach((item) => {
        const remaining = Number(item.quantity) - Number(item.paid_quantity);
        if (remaining > 0 && item.status !== 'cancelled') state.paySelection[item.id] = remaining;
      });
      renderCheckout();
    });

    screen.querySelector('[data-select-none]')?.addEventListener('click', () => {
      state.paySelection = {};
      renderCheckout();
    });

    screen.querySelectorAll('[data-pay-check]').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        const itemId = Number(checkbox.dataset.payCheck);
        const order = getOrder(state.selectedOrderId);
        const item = order?.items?.find((row) => Number(row.id) === itemId);
        const remaining = item ? Number(item.quantity) - Number(item.paid_quantity) : 0;
        state.paySelection[itemId] = checkbox.checked ? remaining : 0;
        renderCheckout();
      });
    });

    screen.querySelectorAll('[data-pay-qty]').forEach((button) => {
      button.addEventListener('click', () => {
        const itemId = Number(button.dataset.payQty);
        const order = getOrder(state.selectedOrderId);
        const item = order?.items?.find((row) => Number(row.id) === itemId);
        if (!item) return;
        const remaining = Number(item.quantity) - Number(item.paid_quantity);
        const current = Number(state.paySelection[itemId] || 0);
        state.paySelection[itemId] = Math.max(0, Math.min(remaining, current + Number(button.dataset.delta)));
        renderCheckout();
      });
    });

    screen.querySelectorAll('[data-pay-method]').forEach((button) => {
      button.addEventListener('click', () => paySelected(button.dataset.payMethod));
    });
  }

  async function paySelected(method) {
    if (state.busy) return;
    const items = Object.entries(state.paySelection)
      .filter(([, quantity]) => Number(quantity) > 0)
      .map(([itemId, quantity]) => ({ item_id: Number(itemId), quantity: Number(quantity) }));
    if (!items.length) return;
    const order = getOrder(state.selectedOrderId);
    const amount = (order?.items || []).reduce((sum, item) => sum + Number(item.unit_price) * Number(state.paySelection[item.id] || 0), 0);
    const message = method === 'card'
      ? `${config.labels?.cardManual || 'Am Terminal kassieren.'}\n\n${money(amount)} als Kartenzahlung bestätigen?`
      : `${money(amount)} als Barzahlung bestätigen?`;
    if (!window.confirm(message)) return;

    setBusy(true);
    try {
      const result = await api(`orders/${state.selectedOrderId}/checkout`, {
        method: 'POST', body: { items, method, terminal_reference: '' }
      });
      toast(result.order_closed ? 'Rechnung bezahlt – Tisch ist wieder frei.' : `${money(result.amount)} wurden bezahlt.`);
      await refreshBootstrap(false);
      state.selectedOrderId = Number(getOrders()[0]?.id || 0);
      initCheckoutSelection();
      renderCheckout();
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setBusy(false);
    }
  }

  init();
})();
