(() => {
  'use strict';

  const root = document.querySelector('[data-admin-users]');
  const MG = window.Microgifter;
  if (!root || !MG) return;

  const state = { userId: null, loading: false, confirmAction: null };

  function node(tag, className = '', text = '') {
    const item = document.createElement(tag);
    if (className) item.className = className;
    if (text !== '') item.textContent = String(text);
    return item;
  }

  function label(value) {
    return String(value || '—').replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function date(value) {
    if (!value) return '—';
    const raw = String(value);
    const parsed = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T') + 'Z');
    return Number.isNaN(parsed.getTime()) ? raw : parsed.toLocaleString();
  }

  function pill(value, tone = '') {
    return node('span', `mg-admin-user-detail-badge${tone ? ` is-${tone}` : ''}`, label(value));
  }

  function button(text, tone, handler) {
    const item = node('button', `mg-admin-user-control-button${tone ? ` ${tone}` : ''}`, text);
    item.type = 'button';
    item.addEventListener('click', handler);
    return item;
  }

  function section(title, description) {
    const item = node('section', 'mg-admin-user-detail-section mg-admin-user-control-section');
    item.dataset.userControls = '';
    const header = node('header');
    const copy = node('div');
    copy.append(node('h3', '', title), node('p', '', description));
    header.appendChild(copy);
    const body = node('div', 'mg-admin-user-control-grid');
    item.append(header, body);
    return { item, body };
  }

  function row(title, description) {
    const item = node('div', 'mg-admin-user-control-row');
    const copy = node('div');
    copy.append(node('strong', '', title), node('p', '', description));
    const actions = node('div', 'mg-admin-user-control-actions');
    item.append(copy, actions);
    return { item, actions };
  }

  function drawerUserId(drawer) {
    const subtitle = drawer.querySelector('.mg-admin-user-drawer-head p');
    const match = subtitle?.textContent?.match(/User #([1-9][0-9]*)/);
    return match ? match[1] : null;
  }

  function confirmLayer(drawer) {
    let layer = drawer.querySelector('[data-admin-user-confirm]');
    if (layer) return layer;

    layer = node('div', 'mg-admin-user-confirm-layer mg-hidden');
    layer.dataset.adminUserConfirm = '';
    const panel = node('div', 'mg-admin-user-confirm');
    const title = node('h3', '', 'Confirm account action');
    title.dataset.confirmTitle = '';
    const description = node('p', '', 'Review the action and provide an administrative reason.');
    description.dataset.confirmDescription = '';
    const field = node('label', '', 'Reason');
    const reason = document.createElement('textarea');
    reason.maxLength = 255;
    reason.placeholder = 'Required. Minimum 8 characters.';
    reason.dataset.confirmReason = '';
    field.appendChild(reason);
    const status = node('div', 'mg-admin-user-control-status');
    status.dataset.confirmStatus = '';
    status.setAttribute('role', 'status');
    const actions = node('div', 'mg-admin-user-confirm-actions');
    const cancel = button('Cancel', '', () => closeConfirm(drawer));
    const confirm = button('Confirm action', 'is-primary', async () => {
      const value = reason.value.trim();
      if (value.length < 8) {
        status.textContent = 'Provide a reason of at least 8 characters.';
        reason.focus();
        return;
      }
      if (!state.confirmAction) return;
      confirm.disabled = true;
      cancel.disabled = true;
      status.textContent = 'Applying action…';
      try {
        await state.confirmAction(value);
        closeConfirm(drawer);
      } catch (error) {
        status.textContent = error.message || 'Unable to complete the action.';
      } finally {
        confirm.disabled = false;
        cancel.disabled = false;
      }
    });
    actions.append(cancel, confirm);
    panel.append(title, description, field, status, actions);
    layer.appendChild(panel);
    drawer.appendChild(layer);
    return layer;
  }

  function openConfirm(drawer, title, description, action) {
    const layer = confirmLayer(drawer);
    layer.querySelector('[data-confirm-title]').textContent = title;
    layer.querySelector('[data-confirm-description]').textContent = description;
    layer.querySelector('[data-confirm-reason]').value = '';
    layer.querySelector('[data-confirm-status]').textContent = '';
    state.confirmAction = action;
    layer.classList.remove('mg-hidden');
    layer.querySelector('[data-confirm-reason]').focus();
  }

  function closeConfirm(drawer) {
    const layer = drawer.querySelector('[data-admin-user-confirm]');
    if (layer) layer.classList.add('mg-hidden');
    state.confirmAction = null;
  }

  function refreshDrawer(userId) {
    state.userId = null;
    const close = document.querySelector('.mg-admin-user-drawer [data-user-drawer-close]');
    if (close) close.click();
    window.setTimeout(() => {
      const trigger = document.querySelector(`[data-user-detail-id="${CSS.escape(String(userId))}"]`);
      if (trigger) trigger.click();
      const refresh = root.querySelector('[data-users-refresh]');
      if (refresh && !refresh.disabled) refresh.click();
    }, 80);
  }

  async function mutate(drawer, payload) {
    await MG.post('/api/admin/user-management.php', payload);
    if (MG.toast) MG.toast('Account action completed.', 'success');
    refreshDrawer(payload.user_id);
  }

  function renderAccount(drawer, host, user, management) {
    const block = section('Account controls', 'Suspend or reactivate this identity. A reason is required and suspending revokes active sessions.');
    const current = row('Account status', `Current state: ${label(user.status)}.`);
    current.actions.appendChild(pill(user.status, String(user.status || '').toLowerCase()));

    if (management.capabilities?.status) {
      const suspend = user.status === 'active';
      current.actions.appendChild(button(suspend ? 'Suspend account' : 'Reactivate account', suspend ? 'is-alert' : 'is-primary', () => {
        const action = suspend ? 'suspend_user' : 'reactivate_user';
        openConfirm(
          drawer,
          suspend ? 'Suspend account' : 'Reactivate account',
          suspend ? 'This blocks protected access and revokes active sessions.' : 'This restores protected account access.',
          (reason) => mutate(drawer, { user_id: user.id, action, reason })
        );
      }));
    } else {
      current.actions.appendChild(node('span', 'mg-admin-user-control-note', 'Protected'));
    }
    block.body.appendChild(current.item);
    host.appendChild(block.item);
  }

  function renderRoles(drawer, host, user, management) {
    const block = section('Role controls', 'Assign or remove standard platform roles. Privileged roles remain manual-only.');
    (management.roles || []).forEach((role) => {
      const item = row(role.name || label(role.slug), role.privileged ? 'Privileged role · manual owner workflow.' : `Role key: ${role.slug}`);
      item.actions.appendChild(pill(role.assigned ? 'assigned' : 'not assigned', role.assigned ? 'active' : ''));
      if (management.capabilities?.roles && !role.privileged) {
        const action = role.assigned ? 'remove_role' : 'assign_role';
        item.actions.appendChild(button(role.assigned ? 'Remove' : 'Assign', role.assigned ? 'is-alert' : 'is-primary', () => {
          openConfirm(drawer, `${role.assigned ? 'Remove' : 'Assign'} ${role.name}`, `Change the ${role.name} role for ${user.display_name || user.email}.`, (reason) => mutate(drawer, {
            user_id: user.id,
            action,
            role: role.slug,
            reason,
          }));
        }));
      }
      block.body.appendChild(item.item);
    });
    host.appendChild(block.item);
  }

  function modelActions(status) {
    if (status === 'pending') return [['Approve', 'active', 'is-primary'], ['Reject', 'rejected', 'is-alert']];
    if (status === 'active') return [['Disable', 'disabled', 'is-alert'], ['Suspend', 'suspended', 'is-alert']];
    if (['disabled', 'suspended', 'rejected'].includes(status)) return [['Activate', 'active', 'is-primary']];
    return [];
  }

  function renderModels(drawer, host, user, management) {
    const block = section('User-model controls', 'Review and transition existing assignable model requests. System models remain manual-only.');
    const models = (management.models || []).filter((model) => model.status);
    if (!models.length) block.body.appendChild(node('div', 'mg-admin-user-control-note', 'No user-model assignments are available.'));
    models.forEach((model) => {
      const description = `${model.description || 'No description.'} Current state: ${label(model.status)}.`;
      const item = row(model.name || label(model.code), description);
      item.actions.appendChild(pill(model.status, String(model.status || '').toLowerCase()));
      if (management.capabilities?.models && !model.is_system && model.is_assignable) {
        modelActions(model.status).forEach(([text, status, tone]) => {
          item.actions.appendChild(button(text, tone, () => {
            openConfirm(drawer, `${text} ${model.name}`, `Move the ${model.name} model from ${label(model.status)} to ${label(status)}.`, (reason) => mutate(drawer, {
              user_id: user.id,
              action: 'set_model_status',
              model: model.code,
              status,
              reason,
            }));
          }));
        });
      }
      block.body.appendChild(item.item);
    });
    host.appendChild(block.item);
  }

  async function renderSessions(drawer, host, user, management) {
    const block = section('Session controls', 'Review recent sessions and revoke individual non-current sessions.');
    host.appendChild(block.item);
    if (!management.capabilities?.sessions_view) {
      block.body.appendChild(node('div', 'mg-admin-user-control-note', 'Session visibility is not available to this administrator.'));
      return;
    }

    try {
      const response = await MG.get(`/api/admin/sessions.php?user_id=${encodeURIComponent(user.id)}&limit=25`);
      const sessions = response.data?.sessions || response.sessions || [];
      if (!sessions.length) block.body.appendChild(node('div', 'mg-admin-user-control-note', 'No sessions were found.'));
      sessions.forEach((session) => {
        const item = row(session.user_agent || 'Unknown device', `${session.ip_address || 'Unknown IP'} · Last seen ${date(session.last_seen_at)}`);
        const active = !session.revoked_at && (!session.expires_at || new Date(String(session.expires_at).replace(' ', 'T') + 'Z') > new Date());
        item.actions.appendChild(pill(session.is_current ? 'current' : (active ? 'active' : 'inactive'), session.is_current || active ? 'active' : ''));
        if (management.capabilities?.sessions_revoke && active && !session.is_current) {
          item.actions.appendChild(button('Revoke', 'is-alert', () => {
            openConfirm(drawer, 'Revoke session', 'This signs the selected device out of the account.', async (reason) => {
              await MG.delete('/api/admin/sessions.php', { session_id: session.id, reason });
              if (MG.toast) MG.toast('Session revoked.', 'success');
              await loadControls(drawer, user.id, true);
            });
          }));
        }
        block.body.appendChild(item.item);
      });
    } catch (error) {
      block.body.appendChild(node('div', 'mg-admin-user-control-note', error.message || 'Unable to load sessions.'));
    }
  }

  async function loadControls(drawer, userId, force = false) {
    if (state.loading || (!force && state.userId === String(userId))) return;
    state.loading = true;
    state.userId = String(userId);
    const host = drawer.querySelector('.mg-admin-user-detail-content');
    if (!host) { state.loading = false; return; }
    host.querySelectorAll('[data-user-controls]').forEach((item) => item.remove());

    try {
      const [detailResponse, optionsResponse] = await Promise.all([
        MG.get(`/api/admin/user-detail.php?user_id=${encodeURIComponent(userId)}`),
        MG.get(`/api/admin/user-options.php?user_id=${encodeURIComponent(userId)}`),
      ]);
      const user = detailResponse.data?.user || detailResponse.user;
      const management = optionsResponse.data?.management || optionsResponse.management;
      if (!user || !management) throw new Error('Account controls are unavailable.');
      renderAccount(drawer, host, user, management);
      renderRoles(drawer, host, user, management);
      renderModels(drawer, host, user, management);
      await renderSessions(drawer, host, user, management);
    } catch (error) {
      const block = section('Account controls', 'Administrative controls could not be loaded.');
      block.body.appendChild(node('div', 'mg-admin-user-control-note', error.message || 'Unable to load account controls.'));
      host.appendChild(block.item);
    } finally {
      state.loading = false;
    }
  }

  function inspectDrawer() {
    const drawer = document.querySelector('.mg-admin-user-drawer');
    const layer = document.querySelector('.mg-admin-user-drawer-layer');
    if (!drawer || !layer || layer.classList.contains('mg-hidden')) return;
    const content = drawer.querySelector('.mg-admin-user-detail-content');
    if (!content || content.classList.contains('mg-hidden')) return;
    const userId = drawerUserId(drawer);
    if (userId) loadControls(drawer, userId);
  }

  const observer = new MutationObserver(inspectDrawer);
  observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const drawer = document.querySelector('.mg-admin-user-drawer');
    if (drawer?.querySelector('[data-admin-user-confirm]:not(.mg-hidden)')) {
      event.stopImmediatePropagation();
      closeConfirm(drawer);
    }
  }, true);
})();
