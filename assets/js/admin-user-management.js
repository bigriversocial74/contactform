(() => {
  'use strict';

  const state = {
    user: null,
    drawer: null,
    section: null,
    reason: null,
    notice: null,
    busy: false,
  };

  function element(tag, className = '', text = '') {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  }

  function readable(value) {
    return String(value ?? '').replace(/[_-]+/g, ' ').trim();
  }

  function formatDate(value) {
    if (!value) return '—';
    const raw = String(value);
    const date = new Date(raw.replace(' ', 'T') + (raw.includes('Z') ? '' : 'Z'));
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(date);
  }

  function management() {
    return state.user?.management || {};
  }

  function capabilities() {
    return management().capabilities || {};
  }

  function reasonValue() {
    const value = String(state.reason?.value || '').trim();
    if (value.length < 8 || value.length > 240) {
      throw new Error('Enter an action reason between 8 and 240 characters.');
    }
    return value;
  }

  function setNotice(message, type = 'info') {
    if (!state.notice) return;
    state.notice.textContent = message;
    state.notice.dataset.type = type;
  }

  function setBusy(busy) {
    state.busy = busy;
    state.section?.querySelectorAll('button,select,textarea').forEach((control) => {
      control.disabled = busy;
    });
  }

  async function perform(action, data, confirmation) {
    if (state.busy || !state.user) return;
    let reason;
    try {
      reason = reasonValue();
    } catch (error) {
      setNotice(error.message, 'error');
      state.reason?.focus();
      return;
    }
    if (!window.confirm(confirmation)) return;

    setBusy(true);
    setNotice('Applying protected account action…');
    try {
      const payload = await Microgifter.post('/api/admin/user-management.php', {
        action,
        user_id: state.user.id,
        reason,
        ...data,
      });
      if (!payload?.ok) throw new Error(payload?.message || 'Account action failed.');
      setNotice(payload.message || 'Account action completed.', 'success');
      document.dispatchEvent(new CustomEvent('mg:admin-users-refresh'));
      document.dispatchEvent(new CustomEvent('mg:admin-user-detail-refresh', {
        detail: { userId: state.user.id },
      }));
    } catch (error) {
      setNotice(error.message || 'Account action failed.', 'error');
      setBusy(false);
    }
  }

  function actionRow(label, description) {
    const row = element('div', 'mg-admin-management-row');
    const copy = element('div', 'mg-admin-management-copy');
    copy.append(element('strong', '', label), element('span', '', description));
    const controls = element('div', 'mg-admin-management-controls');
    row.append(copy, controls);
    return { row, controls };
  }

  function option(value, label, selected = false) {
    const node = element('option', '', label);
    node.value = value;
    node.selected = selected;
    return node;
  }

  function renderStatus(container) {
    if (!capabilities().manage_status) return;
    const item = actionRow('Account status', 'Changing to pending or disabled revokes active sessions.');
    const select = element('select');
    ['active', 'pending', 'disabled'].forEach((status) => {
      select.appendChild(option(status, readable(status), status === state.user.status));
    });
    const button = element('button', 'mg-btn mg-btn-soft', 'Update status');
    button.type = 'button';
    button.addEventListener('click', () => perform(
      'set_status',
      { status: select.value },
      `Change this account status to ${readable(select.value)}?`
    ));
    item.controls.append(select, button);
    container.appendChild(item.row);
  }

  function roleSlugs() {
    return new Set((state.user.roles || []).map((role) => role.slug));
  }

  function renderRoles(container) {
    if (!capabilities().manage_roles) return;
    const assigned = roleSlugs();
    const block = element('div', 'mg-admin-management-block');
    block.append(element('h4', '', 'Role management'), element('p', '', 'Add or remove permitted platform roles. Elevated roles remain super-admin protected.'));

    const current = element('div', 'mg-admin-management-items');
    (state.user.roles || []).forEach((role) => {
      const row = actionRow(role.name || readable(role.slug), role.slug);
      const allowed = (management().available_roles || []).some((available) => available.slug === role.slug);
      if (allowed) {
        const remove = element('button', 'mg-btn mg-btn-ghost', 'Remove');
        remove.type = 'button';
        remove.addEventListener('click', () => perform(
          'remove_role',
          { role: role.slug },
          `Remove the ${readable(role.slug)} role from this account?`
        ));
        row.controls.appendChild(remove);
      } else {
        row.controls.appendChild(element('span', 'mg-admin-management-protected', 'Protected'));
      }
      current.appendChild(row.row);
    });
    block.appendChild(current);

    const available = (management().available_roles || []).filter((role) => !assigned.has(role.slug));
    if (available.length) {
      const addRow = actionRow('Assign role', 'Choose an additional role for this account.');
      const select = element('select');
      available.forEach((role) => select.appendChild(option(role.slug, role.name || readable(role.slug))));
      const add = element('button', 'mg-btn mg-btn-soft', 'Add role');
      add.type = 'button';
      add.addEventListener('click', () => perform(
        'add_role',
        { role: select.value },
        `Assign the ${readable(select.value)} role to this account?`
      ));
      addRow.controls.append(select, add);
      block.appendChild(addRow.row);
    }
    container.appendChild(block);
  }

  function modelMap() {
    return new Map((state.user.models || []).map((model) => [model.code, model]));
  }

  function renderModels(container) {
    if (!capabilities().manage_models) return;
    const block = element('div', 'mg-admin-management-block');
    block.append(element('h4', '', 'User model management'), element('p', '', 'Approve, activate, suspend, disable, revoke, or reject operating models.'));
    const assigned = modelMap();
    const statuses = management().model_statuses || [];

    (management().available_models || []).forEach((model) => {
      const current = assigned.get(model.code);
      const row = actionRow(model.name || readable(model.code), current ? `Current: ${readable(current.status)}` : 'Not assigned');
      const select = element('select');
      statuses.forEach((status) => select.appendChild(option(
        status,
        readable(status),
        current ? status === current.status : status === 'pending'
      )));
      const apply = element('button', 'mg-btn mg-btn-soft', current ? 'Update model' : 'Assign model');
      apply.type = 'button';
      apply.addEventListener('click', () => perform(
        'set_model_status',
        { model: model.code, status: select.value },
        `${current ? 'Change' : 'Assign'} ${model.name || readable(model.code)} to ${readable(select.value)}?`
      ));
      row.controls.append(select, apply);
      block.appendChild(row.row);
    });
    container.appendChild(block);
  }

  function sessionLabel(session) {
    const agent = String(session.user_agent || 'Unknown device').slice(0, 90);
    return `${agent} · ${session.ip_address || 'Unknown IP'}`;
  }

  function renderSessions(container) {
    if (!capabilities().view_sessions) return;
    const block = element('div', 'mg-admin-management-block');
    const head = element('div', 'mg-admin-management-block-head');
    const copy = element('div');
    copy.append(element('h4', '', 'Sessions'), element('p', '', 'Recent DB-backed sessions for this identity.'));
    head.appendChild(copy);

    if (capabilities().revoke_sessions) {
      const revokeAll = element('button', 'mg-btn mg-btn-danger', 'Revoke active sessions');
      revokeAll.type = 'button';
      revokeAll.addEventListener('click', () => perform(
        'revoke_sessions',
        {},
        'Revoke every active session for this account?'
      ));
      head.appendChild(revokeAll);
    }
    block.appendChild(head);

    const sessions = management().sessions || [];
    if (!sessions.length) {
      block.appendChild(element('div', 'mg-admin-management-empty', 'No sessions are recorded.'));
    } else {
      sessions.forEach((session) => {
        const revoked = Boolean(session.revoked_at);
        const row = actionRow(
          sessionLabel(session),
          `${revoked ? `Revoked ${formatDate(session.revoked_at)}` : `Last seen ${formatDate(session.last_seen_at)}`} · Expires ${formatDate(session.expires_at)}`
        );
        if (!revoked && capabilities().revoke_sessions) {
          const revoke = element('button', 'mg-btn mg-btn-ghost', 'Revoke');
          revoke.type = 'button';
          revoke.addEventListener('click', () => perform(
            'revoke_session',
            { session_id: session.id },
            'Revoke this session?'
          ));
          row.controls.appendChild(revoke);
        } else {
          row.controls.appendChild(element('span', 'mg-admin-management-protected', revoked ? 'Revoked' : 'View only'));
        }
        block.appendChild(row.row);
      });
    }
    container.appendChild(block);
  }

  function render(detail) {
    state.user = detail.user;
    state.drawer = detail.drawer;
    state.section?.remove();

    const caps = capabilities();
    const hasControls = caps.manage_status || caps.manage_roles || caps.manage_models || caps.view_sessions;
    const section = element('section', 'mg-admin-user-detail-section mg-admin-user-management-section');
    state.section = section;
    const header = element('header');
    const copy = element('div');
    copy.append(element('h3', '', 'Account management'), element('p', '', 'Protected account, access, model, and session operations.'));
    header.append(copy, element('span', 'mg-admin-users-readonly', hasControls ? 'Permission gated' : 'View only'));
    section.appendChild(header);

    if (!hasControls) {
      section.appendChild(element('div', 'mg-admin-management-empty', caps.is_self
        ? 'Self-management is intentionally disabled in the admin console.'
        : 'This session does not have account-management permissions for the selected user.'));
      detail.drawer.querySelector('.mg-admin-user-detail-content')?.appendChild(section);
      return;
    }

    const reasonLabel = element('label', 'mg-admin-management-reason');
    reasonLabel.append(element('span', '', 'Required action reason'));
    const reason = element('textarea');
    reason.rows = 3;
    reason.maxLength = 240;
    reason.placeholder = 'Explain why this administrative action is required.';
    reasonLabel.appendChild(reason);
    state.reason = reason;

    const notice = element('div', 'mg-admin-management-notice');
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    state.notice = notice;
    section.append(reasonLabel, notice);

    const controls = element('div', 'mg-admin-management-stack');
    renderStatus(controls);
    renderRoles(controls);
    renderModels(controls);
    renderSessions(controls);
    section.appendChild(controls);
    detail.drawer.querySelector('.mg-admin-user-detail-content')?.appendChild(section);
  }

  document.addEventListener('mg:admin-user-detail-loaded', (event) => {
    if (!event.detail?.user || !event.detail?.drawer) return;
    state.busy = false;
    render(event.detail);
  });

  document.addEventListener('mg:admin-user-detail-closed', () => {
    state.user = null;
    state.drawer = null;
    state.section = null;
    state.reason = null;
    state.notice = null;
    state.busy = false;
  });
})();
