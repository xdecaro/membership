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
    const identity = [person.birth_date, person.birth_place].filter(Boolean).join(' · ');
    const contact = [person.email, person.phone].filter(Boolean).join(' · ');
    const meta = [identity, contact].filter(Boolean).join(' — ');
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
    let selectedLabel = input.value.trim();

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
      selectedLabel = input.value.trim();
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
      if (target.value && input.value.trim() !== selectedLabel) {
        target.value = '';
        target.dispatchEvent(new Event('change', { bubbles: true }));
        if (summary) summary.hidden = true;
      }
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 250);
    });
  };

  const initIssuerOrganizationPicker = (picker) => {
    const input = picker.querySelector('[data-membership-issuer-search]');
    const results = picker.querySelector('[data-membership-issuer-results]');
    const target = picker.querySelector('[data-membership-issuer-target]');
    const summary = picker.querySelector('[data-membership-issuer-summary]');
    if (!input || !results || !target) return;

    let timer = 0;
    let request = null;
    let selectedLabel = String(input.dataset.selectedLabel || input.value || '').trim();

    const clearResults = () => results.replaceChildren();
    const rowLabel = (row) => {
      const name = String(row?.name || row?.short_name || row?.uuid || '').trim();
      const meta = [row?.short_name && row.short_name !== name ? row.short_name : '', row?.type, row?.country]
        .filter(Boolean)
        .join(' · ');
      return meta ? `${name} — ${meta}` : name;
    };

    const selectOrganization = (row) => {
      const uuid = String(row?.uuid || '').trim().toLowerCase();
      if (!uuid) return;
      const label = rowLabel(row);
      target.value = uuid;
      target.dispatchEvent(new Event('change', { bubbles: true }));
      input.value = label;
      selectedLabel = label;
      input.dataset.selectedLabel = label;
      if (summary) {
        summary.textContent = label;
        summary.hidden = false;
      }
      clearResults();
    };

    const render = (rows) => {
      clearResults();
      if (!Array.isArray(rows) || rows.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'dm-people-empty';
        empty.textContent = input.dataset.emptyLabel || 'Nessuna organizzazione trovata.';
        results.appendChild(empty);
        return;
      }

      rows.forEach((row) => {
        if (!row?.uuid) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dm-people-result';
        button.textContent = rowLabel(row);
        button.addEventListener('click', () => selectOrganization(row));
        results.appendChild(button);
      });
    };

    const search = async () => {
      const q = input.value.trim();
      if (q.length < 2 || q === selectedLabel) {
        clearResults();
        return;
      }

      if (request) request.abort();
      request = new AbortController();
      const url = new URL(endpoint('organizations.search'), window.location.href);
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
      if (input.value.trim() !== selectedLabel) {
        target.value = '';
        if (summary) summary.hidden = true;
      }
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 250);
    });
  };

  const initOrganizationPicker = (picker) => {
    const search = picker.querySelector('[data-membership-organization-search]');
    const select = picker.querySelector('[data-membership-organization-select]');
    const empty = picker.querySelector('[data-membership-organization-empty]');
    const path = picker.querySelector('[data-membership-organization-path]');

    if (!search || !select) return;

    const normalize = (value) => String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLocaleLowerCase()
      .trim();

    const updatePath = () => {
      if (!path) return;
      const option = select.options[select.selectedIndex];
      path.textContent = option?.dataset?.path || '';
      path.hidden = path.textContent === '';
    };

    const filter = () => {
      const term = normalize(search.value);
      let matches = 0;

      [...select.options].forEach((option, index) => {
        if (index === 0) {
          option.hidden = false;
          option.disabled = false;
          return;
        }

        const haystack = normalize(option.dataset.search || option.textContent);
        const matched = term === '' || haystack.includes(term);
        const keepVisible = matched || option.selected;

        option.hidden = !keepVisible;
        option.disabled = !keepVisible;

        if (matched) matches += 1;
      });

      if (empty) {
        empty.hidden = term === '' || matches > 0;
      }
    };

    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        select.focus();
      }
    });

    select.addEventListener('change', () => {
      search.value = '';
      filter();
      updatePath();
    });

    filter();
    updatePath();
  };

  const initTransferSourceOrganization = () => {
    const form = document.getElementById('adminForm');
    const member = document.getElementById('jform_member_id');
    const source = document.getElementById('jform_from_organization_uuid');

    if (!form || !member || !source || form.querySelector('input[name="entity"]')?.value !== 'transfers') return;

    const applyMemberOrganization = () => {
      const option = member.options[member.selectedIndex];
      const organizationUuid = String(option?.dataset?.organizationUuid || '').trim().toLowerCase();

      if (organizationUuid === '') {
        source.value = '';
      } else {
        source.value = organizationUuid;
      }

      source.dispatchEvent(new Event('change', { bubbles: true }));
    };

    member.addEventListener('change', applyMemberOrganization);

    if (member.value !== '' && source.value === '') {
      applyMemberOrganization();
    }
  };

  const initDclCardForm = () => {
    const form = document.getElementById('adminForm');
    if (!form || form.querySelector('input[name="entity"]')?.value !== 'cards') return;

    const scope = form.querySelector('[data-membership-card-scope]');
    const seasonSelect = form.querySelector('[data-membership-dcl-season]');
    const seasonValue = form.querySelector('[data-membership-dcl-season-value]');
    const validFrom = document.getElementById('jform_valid_from');
    const expiry = document.getElementById('jform_expires_at');
    const issuerSearch = form.querySelector('[data-membership-issuer-search]');
    const issuerTarget = form.querySelector('[data-membership-issuer-target]');
    const issuerAssociation = form.querySelector('[data-membership-issuer-association]');
    const issuerCompetition = form.querySelector('[data-membership-issuer-competition]');
    const issuerCompetitionLabel = form.querySelector('[data-membership-issuer-competition-label]');
    const numberField = form.querySelector('[data-membership-card-number-field]');
    const manualNumber = form.querySelector('[data-membership-card-number-manual]');
    const automaticNumber = form.querySelector('[data-membership-card-number-auto]');
    const automaticNumberHelp = form.querySelector('[data-membership-card-number-auto-help]');
    let numberingRequest = null;

    const selectedSeason = () => seasonSelect?.options?.[seasonSelect.selectedIndex] || null;

    const applyNumberingPolicy = (policy = {}) => {
      if (!numberField) return;
      const mode = String(policy.numbering_mode || 'manual').trim().toLowerCase();
      const manualEdit = Number(policy.manual_edit || 0) === 1;
      const source = String(policy.source || '').trim();
      const editable = mode === 'manual' || (mode === 'external' && manualEdit);

      numberField.dataset.numberingMode = mode;
      numberField.dataset.numberingManualEdit = manualEdit ? '1' : '0';
      numberField.dataset.numberingSource = source;

      if (manualNumber) {
        manualNumber.hidden = !editable;
        manualNumber.disabled = !editable;
      }
      if (automaticNumber) {
        automaticNumber.hidden = editable;
        automaticNumber.placeholder = mode === 'external'
          ? (numberField.dataset.externalPlaceholder || '')
          : (numberField.dataset.automaticPlaceholder || '');
      }
      if (automaticNumberHelp) {
        automaticNumberHelp.hidden = editable;
        if (!editable) {
          if (mode === 'external') {
            const template = String(numberField.dataset.externalHelpTemplate || '%s');
            const generic = String(numberField.dataset.externalSourceGeneric || '').trim();
            automaticNumberHelp.textContent = template.replace('%s', source || generic);
          } else {
            automaticNumberHelp.textContent = numberField.dataset.automaticHelp || '';
          }
        }
      }
    };

    const syncNumberingPolicy = async () => {
      const currentScope = scope?.value === 'competition' ? 'competition' : 'association';
      const issuerUuid = String(issuerTarget?.value || '').trim().toLowerCase();

      if (!issuerUuid) {
        applyNumberingPolicy({
          numbering_mode: currentScope === 'competition' ? 'automatic' : 'manual',
          manual_edit: currentScope === 'association' ? 1 : 0,
          source: '',
        });
        return;
      }

      if (numberingRequest) numberingRequest.abort();
      numberingRequest = new AbortController();
      const url = new URL(endpoint('numbering.policy'), window.location.href);
      url.searchParams.set('issuer_uuid', issuerUuid);
      url.searchParams.set('scope', currentScope);
      const token = tokenName();
      if (token) url.searchParams.set(token, '1');

      try {
        const response = await fetch(url.toString(), {
          headers: { Accept: 'application/json' },
          signal: numberingRequest.signal,
        });
        const payload = await response.json();
        if (!response.ok || payload?.success === false) {
          throw new Error(payload?.message || `HTTP ${response.status}`);
        }
        applyNumberingPolicy(payload?.data || {});
      } catch (error) {
        if (error?.name !== 'AbortError') showError(error?.message);
      }
    };

    const syncIssuerFromSeason = () => {
      if (scope?.value !== 'competition') return;
      const option = selectedSeason();
      const uuid = String(option?.dataset?.issuerUuid || '').trim().toLowerCase();
      const label = String(option?.dataset?.issuerLabel || '').trim();
      if (!issuerTarget) return;

      issuerTarget.value = uuid;
      issuerTarget.dispatchEvent(new Event('change', { bubbles: true }));
      if (issuerSearch) {
        issuerSearch.value = label;
        issuerSearch.dataset.selectedLabel = label;
      }
      if (issuerCompetitionLabel) {
        const emptyLabel = String(issuerCompetitionLabel.dataset.emptyLabel || '').trim();
        issuerCompetitionLabel.textContent = label || emptyLabel;
      }
    };

    const updateVisibility = () => {
      const isCompetition = scope?.value === 'competition';
      form.querySelectorAll('[data-membership-dcl-only]').forEach((field) => {
        field.hidden = !isCompetition;
      });
      if (seasonSelect) seasonSelect.required = isCompetition;

      if (issuerAssociation) issuerAssociation.hidden = isCompetition;
      if (issuerCompetition) issuerCompetition.hidden = !isCompetition;

      if (isCompetition) syncIssuerFromSeason();
    };

    const syncSeason = () => {
      if (!seasonSelect || !seasonValue) return;
      const option = selectedSeason();
      seasonValue.value = option?.dataset?.season || '';
      if (validFrom && validFrom.value === '' && option?.dataset?.start) {
        validFrom.value = option.dataset.start;
      }
      if (expiry && expiry.value === '' && option?.dataset?.end) {
        expiry.value = option.dataset.end;
      }
      syncIssuerFromSeason();
      updateVisibility();
    };

    scope?.addEventListener('change', () => {
      updateVisibility();
      syncNumberingPolicy();
    });
    issuerTarget?.addEventListener('change', syncNumberingPolicy);
    seasonSelect?.addEventListener('change', syncSeason);
    updateVisibility();
    if (seasonSelect?.value) {
      syncSeason();
    } else {
      syncNumberingPolicy();
    }
  };


  const initNumberingRuleForm = () => {
    const form = document.getElementById('adminForm');
    if (!form || form.querySelector('input[name="entity"]')?.value !== 'card_numbering_rules') return;

    const mode = document.getElementById('jform_numbering_mode');
    const source = document.getElementById('jform_source');
    const manualEdit = document.getElementById('jform_manual_edit');
    const padding = document.getElementById('jform_sequence_padding');
    if (!mode) return;

    const field = (element) => element?.closest('.dm-field') || null;
    const sourceField = field(source);
    const manualEditField = field(manualEdit);
    const paddingField = field(padding);

    const update = () => {
      const value = String(mode.value || 'manual');
      const automatic = value === 'automatic';
      const external = value === 'external';

      if (sourceField) sourceField.hidden = !external;
      if (source) source.required = external;
      if (manualEditField) manualEditField.hidden = !external;
      if (paddingField) paddingField.hidden = !automatic;
    };

    mode.addEventListener('change', update);
    update();
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
    document.querySelectorAll('[data-membership-issuer-picker]').forEach(initIssuerOrganizationPicker);
    document.querySelectorAll('[data-membership-organization-picker]').forEach(initOrganizationPicker);
    initTransferSourceOrganization();
    initDclCardForm();
    initNumberingRuleForm();
    initPeopleRelink();
  });
})();
