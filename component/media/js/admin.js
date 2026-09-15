(() => {
  'use strict';

  const JoomlaApi = window.Joomla || {};

  const tokenName = () => document.querySelector('#adminForm input[type="hidden"][name][value="1"]')?.name || '';
  const endpoint = (task) => `index.php?option=com_decaromembership&task=${task}&format=json`;

  const showError = (message) => {
    const text = String(message || 'Request failed.');
    if (typeof JoomlaApi.renderMessages === 'function') {
      JoomlaApi.renderMessages({ error: [text] });
    } else {
      window.alert(text);
    }
  };

  const personLabel = (person) => {
    const meta = [person.email, person.phone].filter(Boolean).join(' · ');
    return meta ? `${person.display_name || person.uuid} — ${meta}` : (person.display_name || person.uuid);
  };

  const membershipPeopleSearch = (picker) => {
    const input = picker.querySelector('[data-membership-people-search]');
    const results = picker.querySelector('[data-membership-people-results]');
    const target = picker.querySelector('[data-membership-person-target]');
    const summary = picker.querySelector('[data-membership-person-summary]');
    const relinkSubmit = picker.querySelector('[data-membership-relink-submit]');
    if (!input || !results || !target) return;

    let timer = 0;
    let request = null;

    const clearResults = () => results.replaceChildren();

    const selectPerson = (person) => {
      target.value = String(person.uuid || '').toLowerCase();
      target.dispatchEvent(new Event('change', { bubbles: true }));
      if (summary) {
        summary.textContent = personLabel(person);
        summary.hidden = false;
      }
      if (relinkSubmit) relinkSubmit.disabled = target.value === '';
      clearResults();
      input.value = person.display_name || '';
    };

    const render = (rows) => {
      clearResults();
      if (!Array.isArray(rows) || rows.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'dm-people-empty';
        empty.textContent = input.dataset.emptyLabel || 'No People records found.';
        results.appendChild(empty);
        return;
      }

      rows.forEach((person) => {
        if (!person?.uuid) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dm-people-result';
        button.textContent = personLabel(person);
        button.addEventListener('click', () => selectPerson(person));
        results.appendChild(button);
      });
    };

    const search = async () => {
      const q = input.value.trim();
      if (q.length < 2) {
        clearResults();
        return;
      }

      if (request) request.abort();
      request = new AbortController();
      const url = new URL(endpoint('people.search'), window.location.href);
      url.searchParams.set('q', q);
      const token = tokenName();
      if (token) url.searchParams.set(token, '1');

      try {
        const response = await fetch(url.toString(), {
          headers: { Accept: 'application/json' },
          signal: request.signal,
        });
        const payload = await response.json();
        if (!response.ok || payload?.success === false) {
          throw new Error(payload?.message || `HTTP ${response.status}`);
        }
        render(payload?.data || []);
      } catch (error) {
        if (error?.name !== 'AbortError') showError(error?.message);
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 250);
    });
  };

  const initPeopleRelink = () => {
    document.querySelectorAll('[data-membership-person-relink]').forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const panel = document.querySelector('[data-membership-relink-panel]');
        if (!panel) return;
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
        if (!panel.hidden) panel.querySelector('[data-membership-people-search]')?.focus();
      });
    });

    document.querySelectorAll('[data-membership-relink-submit]').forEach((button) => {
      button.addEventListener('click', async () => {
        const picker = button.closest('[data-membership-people-picker]');
        const uuid = picker?.querySelector('[data-membership-person-target]')?.value?.trim() || '';
        const memberId = button.dataset.memberId || '';
        if (!uuid || !memberId) return;
        if (!window.confirm(button.dataset.confirm || 'Confirm People relink?')) return;

        const body = new URLSearchParams();
        body.set('member_id', memberId);
        body.set('person_uuid', uuid);
        const token = tokenName();
        if (token) body.set(token, '1');

        button.disabled = true;
        try {
          const response = await fetch(endpoint('people.relink'), {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: body.toString(),
          });
          const payload = await response.json();
          if (!response.ok || payload?.success === false) {
            throw new Error(payload?.message || `HTTP ${response.status}`);
          }
          window.location.reload();
        } catch (error) {
          button.disabled = false;
          showError(error?.message);
        }
      });
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.dm-table-wrap tbody tr').forEach((row) => {
      row.addEventListener('dblclick', () => {
        const link = row.querySelector('a[href*="view=record"]');
        if (link) window.location.href = link.href;
      });
    });

    document.querySelectorAll('[data-membership-people-picker]').forEach(membershipPeopleSearch);
    initPeopleRelink();
  });
})();
