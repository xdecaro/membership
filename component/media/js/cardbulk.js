(() => {
  'use strict';

  const root = document.querySelector('[data-cardbulk-root]');
  if (!root) return;

  const form = document.querySelector('#cardbulkForm');
  const search = root.querySelector('[data-cardbulk-search]');
  const limit = root.querySelector('[data-cardbulk-limit]');
  const searchButton = root.querySelector('[data-cardbulk-search-button]');
  const results = root.querySelector('[data-cardbulk-results]');
  const selectedBox = root.querySelector('[data-cardbulk-selected]');
  const empty = root.querySelector('[data-cardbulk-empty]');
  const count = root.querySelector('[data-cardbulk-count]');
  const clear = root.querySelector('[data-cardbulk-clear]');
  const addAll = root.querySelector('[data-cardbulk-add-all]');
  const preview = root.querySelector('[data-cardbulk-preview]');
  if (!form || !search || !results || !selectedBox) return;

  const selected = new Map();
  const initialSelection = root.querySelector('[data-cardbulk-initial-selection]');
  let currentRows = [];
  let request = null;

  const tokenName = () => form.querySelector('input[type="hidden"][name][value="1"]')?.name || '';
  const label = (person) => {
    const name = String(person?.display_name || person?.uuid || '').trim();
    const meta = [person?.birth_date, person?.birth_place, person?.email].filter(Boolean).join(' · ');
    return meta ? `${name} — ${meta}` : name;
  };

  const syncSelected = () => {
    selectedBox.querySelectorAll('[data-cardbulk-selected-row]').forEach((node) => node.remove());
    if (empty) empty.hidden = selected.size > 0;
    selected.forEach((person, uuid) => {
      const row = document.createElement('div');
      row.className = 'd-flex justify-content-between align-items-center gap-2 border-bottom py-2';
      row.dataset.cardbulkSelectedRow = '1';
      const text = document.createElement('span');
      text.textContent = label(person);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-sm btn-outline-danger';
      remove.textContent = '×';
      remove.setAttribute('aria-label', 'Rimuovi');
      remove.addEventListener('click', () => { selected.delete(uuid); syncSelected(); renderResults(); });
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'person_uuids[]';
      hidden.value = uuid;
      row.append(text, remove, hidden);
      selectedBox.appendChild(row);
    });
    if (count) count.textContent = String(selected.size);
    if (clear) clear.disabled = selected.size === 0;
    if (preview) preview.disabled = selected.size === 0;
  };

  const addPerson = (person) => {
    const uuid = String(person?.uuid || '').trim().toLowerCase();
    if (!uuid) return;
    selected.set(uuid, person);
    syncSelected();
    renderResults();
  };

  const renderResults = () => {
    results.replaceChildren();
    if (!Array.isArray(currentRows) || currentRows.length === 0) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 2;
      td.className = 'text-muted text-center py-3';
      td.textContent = 'Nessuna persona trovata.';
      tr.appendChild(td);
      results.appendChild(tr);
      if (addAll) addAll.disabled = true;
      return;
    }
    currentRows.forEach((person) => {
      const uuid = String(person?.uuid || '').trim().toLowerCase();
      if (!uuid) return;
      const tr = document.createElement('tr');
      const tdName = document.createElement('td');
      tdName.textContent = label(person);
      const tdAction = document.createElement('td');
      tdAction.className = 'text-end';
      const button = document.createElement('button');
      button.type = 'button';
      button.className = selected.has(uuid) ? 'btn btn-sm btn-outline-secondary' : 'btn btn-sm btn-outline-primary';
      button.disabled = selected.has(uuid);
      button.textContent = selected.has(uuid) ? 'Selezionata' : 'Aggiungi';
      button.addEventListener('click', () => addPerson(person));
      tdAction.appendChild(button);
      tr.append(tdName, tdAction);
      results.appendChild(tr);
    });
    if (addAll) addAll.disabled = currentRows.every((person) => selected.has(String(person?.uuid || '').toLowerCase()));
  };

  const runSearch = async () => {
    if (request) request.abort();
    request = new AbortController();
    const url = new URL('index.php?option=com_decaromembership&task=people.search&format=json', window.location.href);
    url.searchParams.set('q', search.value.trim());
    url.searchParams.set('limit', String(limit?.value || 50));
    const token = tokenName();
    if (token) url.searchParams.set(token, '1');
    try {
      const response = await fetch(url.toString(), { headers: { Accept: 'application/json' }, signal: request.signal });
      const payload = await response.json();
      if (!response.ok || payload?.success === false) throw new Error(payload?.message || `HTTP ${response.status}`);
      currentRows = Array.isArray(payload?.data) ? payload.data : [];
      renderResults();
    } catch (error) {
      if (error?.name === 'AbortError') return;
      currentRows = [];
      renderResults();
      if (window.Joomla?.renderMessages) window.Joomla.renderMessages({ error: [String(error?.message || 'Errore ricerca People')] });
    }
  };

  let timer = 0;
  search.addEventListener('input', () => { window.clearTimeout(timer); timer = window.setTimeout(runSearch, 250); });
  searchButton?.addEventListener('click', runSearch);
  limit?.addEventListener('change', runSearch);
  addAll?.addEventListener('click', () => {
    currentRows.forEach((person) => {
      const uuid = String(person?.uuid || '').trim().toLowerCase();
      if (uuid) selected.set(uuid, person);
    });
    syncSelected();
    renderResults();
  });
  clear?.addEventListener('click', () => { selected.clear(); syncSelected(); renderResults(); });

  if (initialSelection) {
    try {
      const people = JSON.parse(initialSelection.textContent || '[]');
      if (Array.isArray(people)) {
        people.forEach((person) => {
          const uuid = String(person?.uuid || '').trim().toLowerCase();
          if (uuid) selected.set(uuid, person);
        });
      }
    } catch (_) {
      // Invalid state must not block the bulk-card screen.
    }
  }

  syncSelected();
  runSearch();
})();
