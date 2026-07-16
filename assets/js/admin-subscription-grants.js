(() => {
  'use strict';

  const state = {
    user: null,
    drawer: null,
    section: null,
    snapshot: null,
    busy: false,
  };

  function el(tag, className = '', text = '') {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function readable(value) {
    return String(value ?? '').replace(/[_-]+/g, ' ').trim().replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function formatDate(value) {
    if (!value) return 'No expiration';
    const raw = String(value);
    const date = new Date(raw.replace(' ', 'T') + (raw.includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(date);
  }

  function notice(message, type = '') {
    const node = state.section?.querySelector('[data-subscription-grant-notice]');
    if (!node) return;
    node.textContent = message || '';
    if (type) node.dataset.type = type;
    else delete node.dataset.type;
  }

  function setBusy(busy) {
    state.busy = busy;
    state.section?.querySelectorAll('button,select,textarea,input').forEach((control) => {
      control.disabled = busy;
    });
  }

  async function loadSnapshot() {
    if (!state.user?.id) return;
    const payload = await window.Microgifter.get(`/api/admin/subscription-grants.php?user_id=${encodeURIComponent(state.user.id)}`);
    state.snapshot = payload.data || payload;
  }

  function packageOptions(select) {
    (state.snapshot?.packages || []).forEach((plan) => {
      const option = el('option', '', `${plan.name} · complimentary`);
      option.value = plan.package_id;
      select.appendChild(option);
    });
  }

  function currentCard(container) {
    const current = state.snapshot?.current_subscription || null;
    const block = el('div', 'mg-admin-management-block');
    block.append(el('h4', '', 'Current subscription authority'));

    if (!current) {
      block.append(el('p', '', 'Free Wallet. No canonical paid or complimentary subscription is active.'));
      container.appendChild(block);
      return;
    }

    const rows = el('div', 'mg-admin-management-items');
    const row = el('div', 'mg-admin-management-row');
    const copy = el('div', 'mg-admin-management-copy');
    copy.append(
      el('strong', '', `${current.package_name || readable(current.package_id)} · ${readable(current.status)}`),
      el('span', '', current.is_complimentary
        ? `Complimentary grant · expires ${formatDate(current.current_period_end)}`
        : `${readable(current.provider_key || 'provider')} subscription · ${formatDate(current.current_period_end)}`)
    );
    const controls = el('div', 'mg-admin-management-controls');
    controls.append(el('span', 'mg-admin-management-protected', current.is_complimentary ? 'Admin grant' : 'Provider backed'));
    row.append(copy, controls);
    rows.appendChild(row);
    block.appendChild(rows);
    container.appendChild(block);
  }

  function grantForm(container) {
    const block = el('div', 'mg-admin-management-block');
    block.append(
      el('h4', '', 'Complimentary subscription'),
      el('p', '', 'Grant a real canonical package without charging the user. Permission roles remain independently assignable.')
    );

    const grid = el('div', 'mg-admin-user-create-grid');

    const packageLabel = el('label', '', 'Package');
    const packageSelect = el('select');
    packageSelect.name = 'package_id';
    packageOptions(packageSelect);
    packageLabel.appendChild(packageSelect);

    const termLabel = el('label', '', 'Grant term');
    const termSelect = el('select');
    termSelect.name = 'term';
    [
      ['30_days', '30 days'],
      ['90_days', '90 days'],
      ['1_year', '1 year'],
      ['permanent', 'No expiration'],
      ['custom', 'Custom expiration'],
    ].forEach(([value, label]) => {
      const option = el('option', '', label);
      option.value = value;
      termSelect.appendChild(option);
    });
    termLabel.appendChild(termSelect);

    const customLabel = el('label', '', 'Custom expiration');
    const customInput = el('input');
    customInput.type = 'date';
    customInput.name = 'custom_end';
    customInput.disabled = true;
    customLabel.appendChild(customInput);

    termSelect.addEventListener('change', () => {
      customInput.disabled = termSelect.value !== 'custom';
      if (customInput.disabled) customInput.value = '';
    });

    grid.append(packageLabel, termLabel, customLabel);

    const reasonLabel = el('label', 'mg-admin-management-reason');
    reasonLabel.append(el('span', '', 'Required grant reason'));
    const reason = el('textarea');
    reason.name = 'reason';
    reason.rows = 3;
    reason.maxLength = 240;
    reason.placeholder = 'Explain why this complimentary subscription is being granted.';
    reasonLabel.appendChild(reason);

    const actions = el('div', 'mg-admin-management-controls');
    const grant = el('button', 'mg-btn mg-btn-primary', 'Grant complimentary subscription');
    grant.type = 'button';
    grant.addEventListener('click', async () => {
      if (state.busy) return;
      const reasonText = String(reason.value || '').trim();
      if (reasonText.length < 8) {
        notice('Provide a grant reason of at least 8 characters.', 'error');
        reason.focus();
        return;
      }
      if (termSelect.value === 'custom' && !customInput.value) {
        notice('Choose a custom expiration date.', 'error');
        customInput.focus();
        return;
      }
      if (!window.confirm(`Grant ${readable(packageSelect.value)} to this user without charge?`)) return;

      setBusy(true);
      notice('Creating complimentary subscription…');
      try {
        const payload = await window.Microgifter.post('/api/admin/subscription-grants.php', {
          action: 'grant',
          user_id: state.user.id,
          package_id: packageSelect.value,
          term: termSelect.value,
          custom_end: customInput.value || null,
          reason: reasonText,
        });
        state.snapshot = (payload.data || payload).snapshot;
        notice(payload.message || 'Complimentary subscription granted.', 'success');
        renderControls();
        document.dispatchEvent(new CustomEvent('mg:admin-users-refresh'));
      } catch (error) {
        notice(error?.message || 'Unable to grant complimentary subscription.', 'error');
      } finally {
        setBusy(false);
      }
    });
    actions.appendChild(grant);

    const current = state.snapshot?.current_subscription || null;
    if (current?.is_complimentary && ['active', 'trialing', 'cancel_pending', 'past_due'].includes(current.status)) {
      const revoke = el('button', 'mg-btn mg-btn-danger', 'Revoke complimentary subscription');
      revoke.type = 'button';
      revoke.addEventListener('click', async () => {
        const reasonText = String(reason.value || '').trim();
        if (reasonText.length < 8) {
          notice('Provide a revocation reason of at least 8 characters.', 'error');
          reason.focus();
          return;
        }
        if (!window.confirm('Revoke this complimentary subscription? Assigned permission roles will remain unchanged.')) return;

        setBusy(true);
        notice('Revoking complimentary subscription…');
        try {
          const payload = await window.Microgifter.post('/api/admin/subscription-grants.php', {
            action: 'revoke',
            user_id: state.user.id,
            reason: reasonText,
          });
          state.snapshot = (payload.data || payload).snapshot;
          notice(payload.message || 'Complimentary subscription revoked.', 'success');
          renderControls();
          document.dispatchEvent(new CustomEvent('mg:admin-users-refresh'));
        } catch (error) {
          notice(error?.message || 'Unable to revoke complimentary subscription.', 'error');
        } finally {
          setBusy(false);
        }
      });
      actions.appendChild(revoke);
    }

    block.append(grid, reasonLabel, actions);
    container.appendChild(block);
  }

  function history(container) {
    const entries = state.snapshot?.grant_history || [];
    const block = el('div', 'mg-admin-management-block');
    block.append(el('h4', '', 'Complimentary grant history'));
    if (!entries.length) {
      block.append(el('div', 'mg-admin-management-empty', 'No complimentary subscription grants are recorded.'));
      container.appendChild(block);
      return;
    }

    const list = el('div', 'mg-admin-management-items');
    entries.forEach((entry) => {
      const row = el('div', 'mg-admin-management-row');
      const copy = el('div', 'mg-admin-management-copy');
      copy.append(
        el('strong', '', `${entry.package_name || readable(entry.package_id)} · ${readable(entry.status)}`),
        el('span', '', `${formatDate(entry.starts_at)} → ${formatDate(entry.ends_at)} · ${entry.reason}`)
      );
      row.append(copy, el('span', 'mg-admin-management-protected', `Admin #${entry.granted_by_user_id}`));
      list.appendChild(row);
    });
    block.appendChild(list);
    container.appendChild(block);
  }

  function renderControls() {
    if (!state.section) return;
    const container = state.section.querySelector('[data-subscription-grant-controls]');
    if (!container) return;
    container.innerHTML = '';
    currentCard(container);
    grantForm(container);
    history(container);
  }

  async function render(detail) {
    state.user = detail.user;
    state.drawer = detail.drawer;
    state.section?.remove();

    const section = el('section', 'mg-admin-user-detail-section mg-admin-user-management-section');
    state.section = section;
    const header = el('header');
    const copy = el('div');
    copy.append(
      el('h3', '', 'Subscription grants'),
      el('p', '', 'Roles control permissions. Subscription grants control paid package access and limits.')
    );
    header.append(copy, el('span', 'mg-admin-users-readonly', 'Admin audited'));
    section.appendChild(header);

    const status = el('div', 'mg-admin-management-notice');
    status.dataset.subscriptionGrantNotice = '';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    section.appendChild(status);

    const controls = el('div', 'mg-admin-management-stack');
    controls.dataset.subscriptionGrantControls = '';
    controls.append(el('div', 'mg-admin-management-empty', 'Loading subscription authority…'));
    section.appendChild(controls);
    detail.drawer.querySelector('.mg-admin-user-detail-content')?.appendChild(section);

    try {
      await loadSnapshot();
      renderControls();
    } catch (error) {
      notice(error?.message || 'Unable to load subscription grant authority.', 'error');
      controls.innerHTML = '';
      controls.append(el('div', 'mg-admin-management-empty', 'Subscription grant controls are unavailable for this session.'));
    }
  }

  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    if (!event.detail?.user || !event.detail?.drawer || !window.Microgifter?.get || !window.Microgifter?.post) return;
    render(event.detail);
  });

  document.addEventListener('mg:admin-user-detail-closed', () => {
    state.user = null;
    state.drawer = null;
    state.section = null;
    state.snapshot = null;
    state.busy = false;
  });
})();
