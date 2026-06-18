window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var state = {
    leads: [],
    roster: [],
    currentUserId: 0,
    openLeadId: null,
    activeChatUserId: null,
    filter: 'all',
    q: '',
    chatPoll: null,
    presenceTimer: null
  };

  function esc(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function dataOf(response) { return response.data || response || {}; }
  function shortDate(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 16);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' ' + date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }
  function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('');
  }
  function statusOptions(selected) {
    return ['new','assigned','contacted','qualified','nurture','converted','closed_lost','spam'].map(function (status) {
      return '<option value="' + status + '"' + (status === selected ? ' selected' : '') + '>' + esc(status.replace('_', ' ')) + '</option>';
    }).join('');
  }
  function salesOptions(selectedId) {
    var options = '<option value="0">Unassigned</option>';
    state.roster.forEach(function (person) {
      options += '<option value="' + esc(person.user_id) + '"' + (Number(person.user_id) === Number(selectedId || 0) ? ' selected' : '') + '>' + esc(person.name || person.email) + '</option>';
    });
    return options;
  }

  function switchTab(name) {
    MG.qsa('[data-crm-tab]').forEach(function (btn) { btn.classList.toggle('is-active', btn.dataset.crmTab === name); });
    MG.qsa('[data-crm-pane]').forEach(function (pane) { pane.classList.toggle('is-active', pane.dataset.crmPane === name); });
  }

  function statusCounts(stats) {
    var out = { new: 0, assigned: 0, contacted: 0, qualified: 0, converted: 0 };
    ((stats && stats.by_status) || []).forEach(function (row) { out[row.status] = Number(row.count || 0); });
    return out;
  }

  function renderStats(stats) {
    var counts = statusCounts(stats || {});
    var node = MG.qs('[data-crm-stats]');
    if (node) {
      node.innerHTML = ['new', 'assigned', 'contacted', 'qualified', 'converted'].map(function (key) {
        return '<div class="crm-stat-card"><strong>' + esc(counts[key] || 0) + '</strong><span>' + esc(key.replace('_', ' ')) + '</span></div>';
      }).join('');
    }
    var mini = MG.qs('[data-crm-mini-stats]');
    if (mini) {
      mini.innerHTML = '<div><strong>' + esc(stats.page_views_today || 0) + '</strong><span>Today views</span></div><div><strong>' + esc(stats.leads_today || 0) + '</strong><span>Today leads</span></div>';
    }
  }

  function renderLeadList() {
    var node = MG.qs('[data-crm-lead-list]');
    if (!node) return;
    if (!state.leads.length) {
      node.innerHTML = '<p class="mg-muted">No leads found yet.</p>';
      return;
    }

    node.innerHTML = state.leads.map(function (lead) {
      var isOpen = Number(state.openLeadId) === Number(lead.id);
      return [
        '<article class="crm-lead-accordion' + (isOpen ? ' is-open' : '') + '" data-lead-id="' + esc(lead.id) + '">',
          '<div class="crm-lead-summary">',
            '<button class="crm-lead-toggle" type="button" data-toggle-lead="' + esc(lead.id) + '" aria-expanded="' + (isOpen ? 'true' : 'false') + '">',
              '<span class="crm-lead-chevron">›</span>',
              '<span class="crm-lead-primary"><strong>' + esc(lead.name) + '</strong><small>' + esc(lead.email || 'No email') + '</small></span>',
              '<span class="crm-lead-cell"><strong>' + esc(lead.business_name || 'No business') + '</strong><small>' + esc(lead.phone || lead.website_url || 'No phone / website') + '</small></span>',
              '<span class="crm-lead-cell"><strong>' + esc(lead.category || lead.lead_type || 'General') + '</strong><small>' + esc((lead.zip_code ? 'ZIP ' + lead.zip_code : 'No region') + ' · ' + (lead.source_page || 'crm')) + '</small></span>',
            '</button>',
            '<select class="crm-row-select" data-lead-status-select="' + esc(lead.id) + '" aria-label="Lead status">' + statusOptions(lead.status) + '</select>',
            '<select class="crm-row-select" data-lead-owner-select="' + esc(lead.id) + '" aria-label="Assigned sales person">' + salesOptions(lead.assigned_user_id) + '</select>',
          '</div>',
          '<div class="crm-lead-body">',
            '<div class="crm-lead-details-grid">',
              '<div><span>Email</span><strong>' + esc(lead.email || '—') + '</strong></div>',
              '<div><span>Phone</span><strong>' + esc(lead.phone || '—') + '</strong></div>',
              '<div><span>Business</span><strong>' + esc(lead.business_name || '—') + '</strong></div>',
              '<div><span>Website</span><strong>' + esc(lead.website_url || '—') + '</strong></div>',
              '<div><span>Type</span><strong>' + esc(lead.lead_type || 'general') + '</strong></div>',
              '<div><span>Priority</span><strong>' + esc(lead.priority || 'normal') + '</strong></div>',
              '<div><span>Region</span><strong>' + esc(lead.zip_code || lead.region_city || lead.region_state || '—') + '</strong></div>',
              '<div><span>Created</span><strong>' + esc(shortDate(lead.created_at)) + '</strong></div>',
            '</div>',
            '<div class="crm-lead-message"><span>Message</span><p>' + esc(lead.message || 'No message provided.') + '</p></div>',
            '<div class="crm-lead-actions">',
              '<textarea rows="2" placeholder="Optional note" data-lead-note="' + esc(lead.id) + '"></textarea>',
              '<button class="mg-btn mg-btn-primary" type="button" data-save-lead="' + esc(lead.id) + '">Save lead</button>',
              '<button class="mg-btn mg-btn-soft" type="button" data-create-user-from-lead="' + esc(lead.id) + '">Create user from lead</button>',
            '</div>',
          '</div>',
        '</article>'
      ].join('');
    }).join('');
  }

  function renderRosterAdmin() {
    var node = MG.qs('[data-crm-roster]');
    if (!node) return;
    node.innerHTML = state.roster.length ? state.roster.map(function (item) {
      return '<article class="crm-roster-card"><strong>#' + esc(item.user_id) + ' ' + esc(item.name || item.email) + '</strong><span class="mg-muted">' + esc(item.status) + ' · open ' + esc(item.open_lead_count) + '/' + esc(item.max_open_leads) + ' · ' + esc(item.territory || 'No territory') + '</span></article>';
    }).join('') : '<p class="mg-muted">No sales roster users yet.</p>';
  }

  function renderTeamList() {
    var node = MG.qs('[data-crm-team-list]');
    if (!node) return;
    var people = state.roster.filter(function (item) { return Number(item.user_id) !== Number(state.currentUserId); });
    node.innerHTML = people.length ? people.map(function (person) {
      return [
        '<button class="crm-team-person' + (Number(state.activeChatUserId) === Number(person.user_id) ? ' is-active' : '') + '" type="button" data-chat-user="' + esc(person.user_id) + '">',
          '<span class="crm-team-avatar">' + esc(initials(person.name || person.email)) + '<i class="crm-presence-dot is-' + esc(person.presence_status || 'offline') + '"></i></span>',
          '<span class="crm-team-copy"><strong>' + esc(person.name || person.email) + '</strong><small>' + esc(person.presence_status || 'offline') + (person.last_seen_at ? ' · ' + shortDate(person.last_seen_at) : '') + '</small></span>',
          Number(person.unread_count || 0) > 0 ? '<span class="crm-unread">' + esc(person.unread_count) + '</span>' : '',
        '</button>'
      ].join('');
    }).join('') : '<p class="mg-muted">No other sales people are on the roster.</p>';
  }

  async function loadRoster() {
    try {
      var response = await MG.get('/api/admin/sales/roster.php');
      var data = dataOf(response);
      state.roster = data.roster || [];
      state.currentUserId = Number(data.current_user_id || 0);
      renderRosterAdmin();
      renderTeamList();
      renderLeadList();
    } catch (error) {
      var rosterNode = MG.qs('[data-crm-roster]');
      if (rosterNode) rosterNode.innerHTML = '<p class="mg-muted">Unable to load roster.</p>';
    }
  }

  async function loadLeads() {
    var params = new URLSearchParams();
    params.set('status', state.filter || 'all');
    if (state.q) params.set('q', state.q);
    try {
      var response = await MG.get('/api/sales/leads/my.php?' + params.toString());
      var data = dataOf(response);
      state.leads = data.leads || [];
      renderStats(data.stats || {});
      renderLeadList();
    } catch (error) {
      var node = MG.qs('[data-crm-lead-list]');
      if (node) node.innerHTML = '<p class="mg-muted">Unable to load leads. ' + esc(error.message || '') + '</p>';
    }
  }

  function getLead(id) {
    return state.leads.find(function (lead) { return Number(lead.id) === Number(id); }) || null;
  }

  async function saveLead(id, button) {
    var lead = getLead(id);
    if (!lead) return;
    var statusSelect = MG.qs('[data-lead-status-select="' + id + '"]');
    var ownerSelect = MG.qs('[data-lead-owner-select="' + id + '"]');
    var note = MG.qs('[data-lead-note="' + id + '"]');
    MG.setBusy(button, true, 'Saving…');
    try {
      if (statusSelect && statusSelect.value !== lead.status) {
        await MG.post('/api/sales/leads/update-status.php', { lead_id: id, status: statusSelect.value, note: note ? note.value : '' });
      } else if (note && note.value.trim()) {
        await MG.post('/api/sales/leads/update-status.php', { lead_id: id, status: lead.status, note: note.value.trim() });
      }
      if (ownerSelect && Number(ownerSelect.value || 0) > 0 && Number(ownerSelect.value) !== Number(lead.assigned_user_id || 0)) {
        await MG.post('/api/sales/leads/reassign.php', { lead_id: id, sales_user_id: Number(ownerSelect.value), reason: 'Assigned from CRM lead row.' });
      }
      MG.toast('Lead saved.', 'success');
      await Promise.all([loadLeads(), loadRoster()]);
      state.openLeadId = id;
      renderLeadList();
    } catch (error) {
      MG.toast(error.message || 'Unable to save lead.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  function prefillUserFromLead(id) {
    var lead = getLead(id);
    if (!lead) return;
    switchTab('users');
    var form = MG.qs('[data-create-user-form]');
    if (!form) return;
    form.elements.full_name.value = lead.name || '';
    form.elements.email.value = lead.email || '';
    var leadInput = MG.qs('[data-user-lead-id]');
    if (leadInput) leadInput.value = lead.id || '';
  }

  async function openChat(userId) {
    state.activeChatUserId = Number(userId);
    renderTeamList();
    await loadChatThread();
    if (state.chatPoll) window.clearInterval(state.chatPoll);
    state.chatPoll = window.setInterval(loadChatThread, 6000);
  }

  async function loadChatThread() {
    if (!state.activeChatUserId) return;
    var panel = MG.qs('[data-crm-chat-panel]');
    var person = state.roster.find(function (item) { return Number(item.user_id) === Number(state.activeChatUserId); });
    if (!panel || !person) return;
    try {
      var response = await MG.get('/api/sales/chat/thread.php?user_id=' + encodeURIComponent(state.activeChatUserId));
      var messages = dataOf(response).messages || [];
      panel.innerHTML = [
        '<header class="crm-chat-head"><div><strong>' + esc(person.name || person.email) + '</strong><span>' + esc(person.presence_status || 'offline') + '</span></div><button type="button" data-close-chat aria-label="Close chat">×</button></header>',
        '<div class="crm-chat-messages">',
        messages.length ? messages.map(function (message) {
          var mine = Number(message.sender_user_id) === Number(state.currentUserId);
          return '<div class="crm-chat-message' + (mine ? ' is-mine' : '') + '"><p>' + esc(message.message) + '</p><small>' + esc(shortDate(message.created_at)) + (message.sent_while_offline ? ' · offline note' : '') + '</small></div>';
        }).join('') : '<div class="crm-chat-empty"><strong>No messages yet</strong><span>Start a conversation or leave an offline note.</span></div>',
        '</div>',
        '<form class="crm-chat-compose" data-chat-form><textarea name="message" rows="2" placeholder="Message ' + esc(person.name || 'sales person') + '..."></textarea><button class="mg-btn mg-btn-primary" type="submit">Send</button></form>'
      ].join('');
      var messagesNode = MG.qs('.crm-chat-messages', panel);
      if (messagesNode) messagesNode.scrollTop = messagesNode.scrollHeight;
      renderTeamList();
    } catch (error) {
      panel.innerHTML = '<div class="crm-chat-empty"><strong>Unable to load chat</strong><span>' + esc(error.message || '') + '</span></div>';
    }
  }

  async function sendChat(form) {
    if (!state.activeChatUserId) return;
    var button = form.querySelector('[type="submit"]');
    MG.setBusy(button, true, 'Sending…');
    try {
      var message = form.elements.message.value.trim();
      if (!message) return;
      await MG.post('/api/sales/chat/send.php', { recipient_user_id: state.activeChatUserId, message: message });
      form.reset();
      await loadChatThread();
    } catch (error) {
      MG.toast(error.message || 'Unable to send message.', 'error');
    } finally {
      MG.setBusy(button, false);
    }
  }

  async function heartbeat(status) {
    try { await MG.post('/api/sales/presence.php', { status: status || 'online' }); } catch (error) {}
  }

  async function submitManualLead(form) {
    var button = form.querySelector('[type="submit"]');
    MG.setBusy(button, true, 'Creating…');
    try {
      await MG.post('/api/sales/leads/create.php', MG.readForm(form));
      MG.setStatus('[data-manual-lead-status]', 'Lead created.', 'success');
      form.reset();
      await loadLeads();
      switchTab('leads');
    } catch (error) {
      MG.setStatus('[data-manual-lead-status]', error.message || 'Unable to create lead.', 'error');
    } finally { MG.setBusy(button, false); }
  }

  async function submitCreateUser(form) {
    var button = form.querySelector('[type="submit"]');
    MG.setBusy(button, true, 'Creating…');
    try {
      await MG.post('/api/sales/users/create.php', MG.readForm(form));
      MG.setStatus('[data-create-user-status]', 'User record created or already existed.', 'success');
      form.reset();
    } catch (error) {
      MG.setStatus('[data-create-user-status]', error.message || 'Unable to create user.', 'error');
    } finally { MG.setBusy(button, false); }
  }

  async function submitRoster(form) {
    var button = form.querySelector('[type="submit"]');
    MG.setBusy(button, true, 'Saving…');
    try {
      await MG.post('/api/admin/sales/roster.php', MG.readForm(form));
      MG.setStatus('[data-roster-status]', 'Roster saved.', 'success');
      await loadRoster();
    } catch (error) {
      MG.setStatus('[data-roster-status]', error.message || 'Unable to save roster.', 'error');
    } finally { MG.setBusy(button, false); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    Promise.all([loadRoster(), loadLeads()]);
    heartbeat('online');
    state.presenceTimer = window.setInterval(function () { heartbeat(document.hidden ? 'away' : 'online'); loadRoster(); }, 45000);

    MG.qsa('[data-crm-tab]').forEach(function (btn) { btn.addEventListener('click', function () { switchTab(btn.dataset.crmTab); }); });
    var refresh = MG.qs('[data-crm-refresh]');
    if (refresh) refresh.addEventListener('click', function () { Promise.all([loadLeads(), loadRoster()]); });
    var statusFilter = MG.qs('[data-crm-status-filter]');
    if (statusFilter) statusFilter.addEventListener('change', function () { state.filter = statusFilter.value; loadLeads(); });
    var search = MG.qs('[data-crm-search]');
    if (search) search.addEventListener('input', function () { window.clearTimeout(search._t); search._t = window.setTimeout(function () { state.q = search.value.trim(); loadLeads(); }, 350); });

    document.addEventListener('click', function (event) {
      var toggle = event.target.closest('[data-toggle-lead]');
      if (toggle) {
        var id = Number(toggle.dataset.toggleLead);
        state.openLeadId = Number(state.openLeadId) === id ? null : id;
        renderLeadList();
      }
      var save = event.target.closest('[data-save-lead]');
      if (save) saveLead(Number(save.dataset.saveLead), save);
      var createUser = event.target.closest('[data-create-user-from-lead]');
      if (createUser) prefillUserFromLead(Number(createUser.dataset.createUserFromLead));
      var chatUser = event.target.closest('[data-chat-user]');
      if (chatUser) openChat(Number(chatUser.dataset.chatUser));
      if (event.target.closest('[data-close-chat]')) {
        state.activeChatUserId = null;
        if (state.chatPoll) window.clearInterval(state.chatPoll);
        var panel = MG.qs('[data-crm-chat-panel]');
        if (panel) panel.innerHTML = '<div class="crm-chat-empty"><strong>Select a sales person</strong><span>Open a conversation or leave an offline note.</span></div>';
        renderTeamList();
      }
    });

    document.addEventListener('submit', function (event) {
      var chatForm = event.target.closest('[data-chat-form]');
      if (chatForm) { event.preventDefault(); sendChat(chatForm); }
    });

    var manualForm = MG.qs('[data-manual-lead-form]');
    if (manualForm) manualForm.addEventListener('submit', function (event) { event.preventDefault(); submitManualLead(manualForm); });
    var userForm = MG.qs('[data-create-user-form]');
    if (userForm) userForm.addEventListener('submit', function (event) { event.preventDefault(); submitCreateUser(userForm); });
    var rosterForm = MG.qs('[data-roster-form]');
    if (rosterForm) rosterForm.addEventListener('submit', function (event) { event.preventDefault(); submitRoster(rosterForm); });
  });

  window.addEventListener('beforeunload', function () { heartbeat('offline'); });
})(window, document);
