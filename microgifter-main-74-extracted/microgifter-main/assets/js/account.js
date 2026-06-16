window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var profileCache = null;

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDate(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T') + 'Z');
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
  }

  function sessionStatus(session) {
    if (session.revoked_at) return 'Revoked';
    if (session.expires_at && new Date(String(session.expires_at).replace(' ', 'T') + 'Z').getTime() < Date.now()) return 'Expired';
    if (Number(session.is_current) === 1) return 'Current';
    return 'Active';
  }

  function renderSessions(container, sessions) {
    if (!container) return;
    if (!sessions.length) {
      container.innerHTML = '<p class="mg-muted">No session records were found for this account.</p>';
      return;
    }

    var rows = sessions.map(function (session) {
      return [
        '<tr>',
        '<td><strong>' + escapeHtml(sessionStatus(session)) + '</strong></td>',
        '<td>' + escapeHtml(session.ip_address || '—') + '</td>',
        '<td>' + escapeHtml(session.user_agent || '—') + '</td>',
        '<td>' + escapeHtml(formatDate(session.last_seen_at)) + '</td>',
        '<td>' + escapeHtml(formatDate(session.expires_at)) + '</td>',
        '</tr>'
      ].join('');
    }).join('');

    container.innerHTML = [
      '<div class="mg-table-wrap">',
      '<table class="mg-table">',
      '<thead><tr><th>Status</th><th>IP</th><th>Device</th><th>Last seen</th><th>Expires</th></tr></thead>',
      '<tbody>' + rows + '</tbody>',
      '</table>',
      '</div>'
    ].join('');
  }

  async function loadSessions() {
    var container = MG.qs('[data-account-sessions]');
    if (!container) return;
    container.innerHTML = '<p class="mg-muted">Loading sessions…</p>';
    try {
      var response = await MG.get('/api/me/sessions.php');
      renderSessions(container, Array.isArray(response.data && response.data.sessions) ? response.data.sessions : (Array.isArray(response.sessions) ? response.sessions : []));
    } catch (error) {
      container.innerHTML = '<p class="mg-muted">Unable to load sessions.</p>';
      MG.toast(error.message || 'Unable to load sessions.', 'error');
    }
  }

  async function revoke(mode, button) {
    MG.setBusy(button, true, 'Revoking…');
    try {
      var response = await MG.delete('/api/me/sessions.php', { mode: mode });
      var data = response.data || response;
      MG.toast(response.message || 'Session update complete.', 'success');
      if (data.redirect || response.redirect) {
        window.location.href = data.redirect || response.redirect;
        return;
      }
      await loadSessions();
    } catch (error) {
      MG.toast(error.message || 'Unable to revoke sessions.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  function switchTab(name) {
    MG.qsa('[data-account-tab]').forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-account-tab') === name);
    });
    MG.qsa('[data-account-pane]').forEach(function (pane) {
      pane.classList.toggle('is-active', pane.getAttribute('data-account-pane') === name);
    });
  }

  function fillProfileForm(profile) {
    var form = MG.qs('[data-profile-form]');
    if (!form || !profile) return;
    ['display_name', 'slug', 'headline', 'bio', 'avatar_url', 'cover_url', 'location_label', 'website_url', 'profile_type', 'visibility', 'status'].forEach(function (field) {
      if (form.elements[field]) form.elements[field].value = profile[field] || '';
    });
    var score = MG.qs('[data-profile-score]');
    if (score) score.textContent = 'Score ' + (profile.completion_score || 0) + '%';
    var link = MG.qs('[data-profile-public-link]');
    if (link) {
      link.href = '/api/public/profile.php?slug=' + encodeURIComponent(profile.slug || '');
      link.setAttribute('aria-disabled', profile.status !== 'active');
    }
  }

  function renderLinkFields(links) {
    var container = MG.qs('[data-profile-link-fields]');
    if (!container) return;
    var safeLinks = Array.isArray(links) ? links.slice(0, 3) : [];
    while (safeLinks.length < 3) safeLinks.push({ label: '', url: '', link_type: 'custom' });
    container.innerHTML = safeLinks.map(function (link, index) {
      return [
        '<div class="mg-link-row">',
        '<label>Label ',
        '<input name="label_' + index + '" value="' + escapeHtml(link.label || '') + '" placeholder="Website">',
        '</label>',
        '<label>URL ',
        '<input name="url_' + index + '" value="' + escapeHtml(link.url || '') + '" placeholder="https://...">',
        '</label>',
        '</div>'
      ].join('');
    }).join('');
  }

  async function loadProfile() {
    var form = MG.qs('[data-profile-form]');
    if (!form) return;
    try {
      var response = await MG.get('/api/profiles/me.php');
      profileCache = response.data && response.data.profile ? response.data.profile : response.profile;
      fillProfileForm(profileCache);
      renderLinkFields(profileCache && profileCache.links ? profileCache.links : []);
    } catch (error) {
      MG.setStatus('[data-profile-status]', error.message || 'Unable to load profile.', 'error');
    }
  }

  async function saveProfile(form) {
    var button = form.querySelector('[type="submit"]');
    MG.setBusy(button, true, 'Saving…');
    MG.setStatus('[data-profile-status]', '', '');
    try {
      var response = await MG.post('/api/profiles/update.php', MG.readForm(form));
      profileCache = response.data && response.data.profile ? response.data.profile : response.profile;
      fillProfileForm(profileCache);
      MG.setStatus('[data-profile-status]', 'Profile saved.', 'success');
      MG.toast('Profile saved.', 'success');
    } catch (error) {
      MG.setStatus('[data-profile-status]', error.message || 'Unable to save profile.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  async function saveLinks(form) {
    var button = form.querySelector('[type="submit"]');
    var links = [];
    for (var i = 0; i < 3; i++) {
      var label = form.elements['label_' + i] ? form.elements['label_' + i].value.trim() : '';
      var url = form.elements['url_' + i] ? form.elements['url_' + i].value.trim() : '';
      if (label || url) links.push({ label: label, url: url, link_type: 'custom', sort_order: (i + 1) * 10, is_active: 1 });
    }
    MG.setBusy(button, true, 'Saving…');
    try {
      var response = await MG.post('/api/profiles/links.php', { links: links });
      var data = response.data || response;
      renderLinkFields(data.links || []);
      MG.setStatus('[data-links-status]', 'Links saved.', 'success');
      await loadProfile();
    } catch (error) {
      MG.setStatus('[data-links-status]', error.message || 'Unable to save links.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  function renderActiveModel(data) {
    var node = MG.qs('[data-active-model-box]');
    if (!node) return;
    node.innerHTML = '<strong>Active context</strong><span class="mg-muted">' + escapeHtml(data.active_model || 'customer') + '</span>';
  }

  function renderModelChips(models) {
    var node = MG.qs('[data-model-chip-list]');
    if (!node) return;
    if (!models || !models.length) {
      node.innerHTML = '<span class="mg-chip">customer</span>';
      return;
    }
    node.innerHTML = models.map(function (model) { return '<span class="mg-chip">' + escapeHtml(model) + '</span>'; }).join('');
  }

  function renderModels(payload) {
    var container = MG.qs('[data-user-model-list]');
    if (!container) return;
    var models = payload.models || [];
    var activeModels = payload.active_models || [];
    var activeContext = payload.active_model || 'customer';
    var requestable = ['creator', 'merchant', 'marketing_affiliate'];

    renderModelChips(activeModels);
    renderActiveModel({ active_model: activeContext });

    container.innerHTML = models.map(function (model) {
      var status = model.status || 'not enabled';
      var isActive = status === 'active';
      var canRequest = requestable.indexOf(model.code) !== -1 && !model.status;
      var canSwitch = isActive && activeContext !== model.code;
      return [
        '<article class="mg-model-card">',
        '<div>',
        '<h3>' + escapeHtml(model.name || model.code) + '</h3>',
        '<p class="mg-muted">' + escapeHtml(model.description || '') + '</p>',
        '<span class="mg-chip">' + escapeHtml(status) + '</span>',
        '</div>',
        '<div class="mg-model-actions">',
        canRequest ? '<button class="mg-btn mg-btn-soft" type="button" data-request-model="' + escapeHtml(model.code) + '">Request</button>' : '',
        canSwitch ? '<button class="mg-btn mg-btn-ghost" type="button" data-switch-model="' + escapeHtml(model.code) + '">Use context</button>' : '',
        isActive && activeContext === model.code ? '<span class="mg-chip">Current</span>' : '',
        '</div>',
        '</article>'
      ].join('');
    }).join('');
  }

  async function loadModels() {
    var container = MG.qs('[data-user-model-list]');
    if (!container) return;
    try {
      var listResponse = await MG.get('/api/user-models/list.php');
      var myResponse = await MG.get('/api/user-models/my.php');
      var listData = listResponse.data || listResponse;
      var myData = myResponse.data || myResponse;
      renderModels({
        models: listData.models || [],
        active_models: myData.models || [],
        active_model: myData.active_model || 'customer'
      });
    } catch (error) {
      container.innerHTML = '<p class="mg-muted">Unable to load identity models.</p>';
    }
  }

  async function requestModel(model, button) {
    MG.setBusy(button, true, 'Requesting…');
    try {
      await MG.post('/api/user-models/request.php', { model: model });
      MG.toast('Model request submitted.', 'success');
      await loadModels();
    } catch (error) {
      MG.toast(error.message || 'Unable to request model.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  async function switchModel(model, button) {
    MG.setBusy(button, true, 'Switching…');
    try {
      await MG.post('/api/user-models/context.php', { model: model });
      MG.toast('Active context updated.', 'success');
      await loadModels();
    } catch (error) {
      MG.toast(error.message || 'Unable to switch context.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadSessions();
    loadProfile();
    loadModels();

    MG.qsa('[data-account-tab]').forEach(function (button) {
      button.addEventListener('click', function () {
        switchTab(button.getAttribute('data-account-tab'));
      });
    });

    var profileForm = MG.qs('[data-profile-form]');
    if (profileForm) {
      profileForm.addEventListener('submit', function (event) {
        event.preventDefault();
        saveProfile(profileForm);
      });
    }

    var linksForm = MG.qs('[data-profile-links-form]');
    if (linksForm) {
      linksForm.addEventListener('submit', function (event) {
        event.preventDefault();
        saveLinks(linksForm);
      });
    }

    document.addEventListener('click', function (event) {
      var requestButton = event.target.closest('[data-request-model]');
      if (requestButton) requestModel(requestButton.getAttribute('data-request-model'), requestButton);
      var switchButton = event.target.closest('[data-switch-model]');
      if (switchButton) switchModel(switchButton.getAttribute('data-switch-model'), switchButton);
    });

    MG.qsa('[data-session-revoke]').forEach(function (button) {
      button.addEventListener('click', function () {
        var mode = button.getAttribute('data-session-revoke') || 'all_except_current';
        var message = mode === 'current'
          ? 'Sign out of this device now?'
          : mode === 'all'
            ? 'Sign out of every device now?'
            : 'Sign out of all other devices?';
        if (!window.confirm(message)) return;
        revoke(mode, button);
      });
    });
  });
})(window, document);
