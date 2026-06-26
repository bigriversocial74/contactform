(() => {
  'use strict';

  const root = document.querySelector('[data-admin-roles]');
  if (!root) return;

  const list = root.querySelector('[data-roles-list]');
  const summary = root.querySelector('[data-roles-summary]');
  const refresh = root.querySelector('[data-roles-refresh]');
  const loading = root.querySelector('[data-roles-loading]');
  const error = root.querySelector('[data-roles-error]');
  const errorMessage = root.querySelector('[data-roles-error-message]');
  const groups = root.querySelector('[data-permission-groups]');
  const title = root.querySelector('[data-role-title]');
  const description = root.querySelector('[data-role-description]');
  const score = root.querySelector('[data-role-score]');
  const status = root.querySelector('[data-roles-status]');
  const reason = root.querySelector('[data-role-reason]');

  const state = { roles: [], permissions: [], selected: null, canManageElevated: false, busy: false };

  function show(node, visible) { node?.classList.toggle('mg-hidden', !visible); }
  function clear(node) { while (node?.firstChild) node.removeChild(node.firstChild); }
  function text(value, fallback = '—') { const out = String(value ?? '').trim(); return out || fallback; }
  function readable(value) { return text(value).replace(/[_-]+/g, ' '); }
  function setStatus(message, type = 'info') { status.textContent = message || ''; status.dataset.type = type; }
  function selectedRole() { return state.roles.find((role) => role.slug === state.selected) || null; }
  function rolePermissions(role) { return new Set(Array.isArray(role?.permissions) ? role.permissions : []); }

  function scoreRole(role) {
    const assigned = rolePermissions(role).size;
    const total = state.permissions.length || 1;
    const percent = Math.round((assigned / total) * 10);
    const value = Math.max(assigned ? 1 : 0, Math.min(10, percent));
    return value;
  }

  function reasonValue() {
    const value = String(reason?.value || '').trim();
    if (value.length < 8 || value.length > 240) throw new Error('Enter a reason between 8 and 240 characters.');
    return value;
  }

  function renderRoles() {
    clear(list);
    state.roles.forEach((role) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'mg-admin-role-item' + (role.slug === state.selected ? ' is-active' : '');
      button.dataset.role = role.slug;
      const name = document.createElement('strong');
      name.textContent = role.name || readable(role.slug);
      const detail = document.createElement('span');
      detail.textContent = `${role.permissions.length} permission${role.permissions.length === 1 ? '' : 's'} assigned`;
      const badge = document.createElement('em');
      badge.textContent = role.is_protected ? 'protected' : 'assignable';
      button.append(name, detail, badge);
      button.addEventListener('click', () => {
        state.selected = role.slug;
        render();
      });
      list.appendChild(button);
    });
    summary.textContent = `${state.roles.length} role${state.roles.length === 1 ? '' : 's'} · ${state.permissions.length} permission${state.permissions.length === 1 ? '' : 's'}`;
  }

  function groupPermissions() {
    return state.permissions.reduce((map, permission) => {
      const group = permission.group || 'system';
      if (!map.has(group)) map.set(group, []);
      map.get(group).push(permission);
      return map;
    }, new Map());
  }

  function canEdit(role) {
    if (!role) return false;
    if (role.slug === 'super_admin') return false;
    if (['admin', 'super_admin'].includes(role.slug) && !state.canManageElevated) return false;
    return true;
  }

  async function updatePermission(role, permission, checked, checkbox) {
    if (state.busy) return;
    let actionReason;
    try {
      actionReason = reasonValue();
    } catch (err) {
      setStatus(err.message, 'error');
      checkbox.checked = !checked;
      reason?.focus();
      return;
    }
    if (!window.confirm(`${checked ? 'Add' : 'Remove'} ${permission.slug} ${checked ? 'to' : 'from'} ${role.name || role.slug}?`)) {
      checkbox.checked = !checked;
      return;
    }
    state.busy = true;
    checkbox.disabled = true;
    setStatus('Updating role permission…');
    try {
      const response = await Microgifter.post('/api/admin/role-permissions.php', {
        role: role.slug,
        permission: permission.slug,
        operation: checked ? 'add' : 'remove',
        reason: actionReason,
      });
      if (!response?.ok) throw new Error(response?.message || 'Unable to update role permission.');
      const current = rolePermissions(role);
      if (checked) current.add(permission.slug);
      else current.delete(permission.slug);
      role.permissions = Array.from(current).sort();
      setStatus('Role permission updated.', 'success');
      render();
    } catch (err) {
      checkbox.checked = !checked;
      setStatus(err.message || 'Unable to update role permission.', 'error');
    } finally {
      state.busy = false;
      checkbox.disabled = false;
    }
  }

  function renderPermissions() {
    clear(groups);
    const role = selectedRole();
    if (!role) {
      show(groups, false);
      return;
    }
    title.textContent = role.name || readable(role.slug);
    description.textContent = `${role.slug} · ${role.permissions.length} assigned permission${role.permissions.length === 1 ? '' : 's'}`;
    const value = scoreRole(role);
    score.textContent = `Score ${value}/10`;
    score.classList.toggle('is-empty', value === 0);
    score.classList.toggle('is-warning', value > 0 && value < 7);

    const assigned = rolePermissions(role);
    const editable = canEdit(role);
    groupPermissions().forEach((items, groupName) => {
      const section = document.createElement('section');
      section.className = 'mg-admin-permission-group';
      const head = document.createElement('header');
      const h3 = document.createElement('h3');
      h3.textContent = groupName;
      const count = document.createElement('span');
      const selected = items.filter((item) => assigned.has(item.slug)).length;
      count.textContent = `${selected}/${items.length} active`;
      head.append(h3, count);
      const body = document.createElement('div');
      body.className = 'mg-admin-permission-list';
      items.forEach((permission) => {
        const label = document.createElement('label');
        label.className = 'mg-admin-permission-item' + (!editable ? ' is-protected' : '');
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = assigned.has(permission.slug);
        input.disabled = !editable;
        input.addEventListener('change', () => updatePermission(role, permission, input.checked, input));
        const copy = document.createElement('span');
        const strong = document.createElement('strong');
        strong.textContent = permission.name || readable(permission.slug);
        const slug = document.createElement('span');
        slug.textContent = permission.slug;
        copy.append(strong, slug);
        label.append(input, copy);
        body.appendChild(label);
      });
      section.append(head, body);
      groups.appendChild(section);
    });
    show(groups, true);
  }

  function render() {
    renderRoles();
    renderPermissions();
  }

  async function load() {
    refresh.disabled = true;
    setStatus('');
    show(loading, true);
    show(error, false);
    show(groups, false);
    try {
      const response = await fetch('/api/admin/roles.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'Unable to load roles.');
      const data = payload.data || {};
      state.roles = Array.isArray(data.roles) ? data.roles : [];
      state.permissions = Array.isArray(data.permissions) ? data.permissions : [];
      state.canManageElevated = Boolean(data.can_manage_elevated);
      if (!state.selected && state.roles.length) state.selected = state.roles[0].slug;
      show(loading, false);
      render();
    } catch (err) {
      show(loading, false);
      show(error, true);
      errorMessage.textContent = err.message || 'Unable to load roles.';
    } finally {
      refresh.disabled = false;
    }
  }

  refresh?.addEventListener('click', load);
  load();
})();
