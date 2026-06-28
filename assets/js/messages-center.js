document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-messages-center]');
  if (!app || !window.Microgifter) return;

  var list = app.querySelector('[data-thread-list]');
  var detail = app.querySelector('[data-thread-detail]');
  var search = app.querySelector('[data-message-search]');
  var sidebar = app.querySelector('[data-app-sidebar]');
  var sidebarToggle = app.querySelector('[data-messages-sidebar-toggle]');
  var sidebarBackdrop = app.querySelector('[data-messages-sidebar-backdrop]');
  var refreshButtons = app.querySelectorAll('[data-message-refresh]');
  var filterButtons = app.querySelectorAll('[data-message-filter]');
  var state = { threads: [], current: null, sending: false, filter: 'all' };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function initials(value) {
    var label = String(value || 'Conversation').replace(/^CRM:\s*/i, '').trim();
    return label.split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase() || 'MG';
  }

  function sourceLabel(item) { return item && (item.source_label || (item.source && item.source.label)) || ''; }
  function sourceSystem(item) { return item && (item.source_system || (item.source && item.source.system)) || ''; }
  function sourceBadge(item) {
    var label = sourceLabel(item);
    if (!label) return '';
    return '<span class="mg-message-source-chip" data-source-system="' + esc(sourceSystem(item)) + '">' + esc(label) + '</span>';
  }

  function contextItems(source) {
    var context = source && source.context ? source.context : {};
    var rows = [];
    if (source && source.label) rows.push(['Source', source.label]);
    if (source && source.system) rows.push(['System', source.system]);
    if (context.merchant_name) rows.push(['Merchant', context.merchant_name]);
    if (context.campaign_title) rows.push(['Campaign', context.campaign_title]);
    if (context.campaign_type) rows.push(['Campaign type', context.campaign_type]);
    if (context.contact_source) rows.push(['Contact source', context.contact_source]);
    if (context.contact_id) rows.push(['CRM contact', context.contact_id]);
    if (context.store_session_id) rows.push(['Store session', context.store_session_id]);
    return rows;
  }

  function contextBar(source) {
    var rows = contextItems(source);
    if (!rows.length) return '';
    return '<div class="mg-thread-context-bar">' + rows.map(function (row) {
      return '<div class="mg-thread-context-item"><span>' + esc(row[0]) + '</span><strong>' + esc(row[1]) + '</strong></div>';
    }).join('') + '</div>';
  }

  function composerStatus(form, message, type) {
    if (!form) return;
    var node = form.querySelector('[data-message-compose-status]');
    if (!node) return;
    node.textContent = message || '';
    node.classList.toggle('is-visible', Boolean(message));
    node.classList.toggle('is-success', type === 'success');
    node.classList.toggle('is-error', type === 'error');
  }

  function setComposerBusy(form, busy) {
    var textarea = form && form.querySelector('textarea[name="body"]');
    var button = form && form.querySelector('[data-message-send]');
    if (textarea) textarea.disabled = busy;
    if (button) { button.disabled = busy; button.textContent = busy ? 'Sending...' : 'Send'; }
    if (form) form.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function setSidebarOpen(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('is-mobile-open', open);
    document.body.classList.toggle('mg-messages-sidebar-open', open);
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (sidebarBackdrop) sidebarBackdrop.hidden = !open;
  }

  function matchesFilter(thread) {
    if (state.filter === 'unread') return Boolean(thread.unread);
    if (state.filter === 'open') return true;
    return true;
  }

  function filteredThreads() {
    var query = (search && search.value || '').toLowerCase();
    return state.threads.filter(function (thread) {
      var haystack = [thread.subject, thread.latest_message, thread.source_label, thread.source_system, thread.source_reference].join(' ').toLowerCase();
      return matchesFilter(thread) && haystack.includes(query);
    });
  }

  function render() {
    var items = filteredThreads();
    if (!list) return;
    list.innerHTML = items.length ? items.map(function (thread) {
      var active = state.current === thread.public_id;
      return '<article class="mg-thread-card ' + (thread.unread ? 'is-unread ' : '') + (active ? 'is-active' : '') + '" data-thread-id="' + esc(thread.public_id) + '" data-thread-initial="' + esc(initials(thread.subject || sourceLabel(thread))) + '"><div><div class="mg-thread-card-title"><strong>' + esc(thread.subject || 'Conversation') + '</strong>' + sourceBadge(thread) + '</div><p>' + esc(thread.latest_message || 'No messages yet') + '</p><small>' + esc(thread.latest_at || thread.updated_at || '') + '</small></div></article>';
    }).join('') : '<div class="mg-empty-state"><strong>No conversations found.</strong><p>Merchant CRM, Store Canvas, gift, recipient, and PPPM conversations will appear here.</p></div>';
  }

  function renderKpis(counts) {
    var kpiNode = app.querySelector('[data-message-kpis]');
    if (!kpiNode) return;
    kpiNode.innerHTML = [['Unread', counts.message_unread], ['Open', state.threads.length], ['Alerts', counts.open_alerts]].map(function (item) {
      return '<div class="mg-communications-kpi"><span>' + esc(item[0]) + '</span><strong>' + Number(item[1] || 0).toLocaleString() + '</strong></div>';
    }).join('');
  }

  async function load(options) {
    options = options || {};
    var response = await Microgifter.get('/api/communications/dashboard.php?limit=100');
    var data = response.data || response;
    var counts = data.counts || {};
    state.threads = data.threads || [];
    renderKpis(counts);
    render();
    if (Microgifter.setMessageCount) Microgifter.setMessageCount(counts.message_unread || 0);
    var requested = new URLSearchParams(location.search).get('thread');
    var nextThread = requested || state.current || (state.threads[0] && state.threads[0].public_id) || '';
    if (nextThread && options.open !== false) await openThread(nextThread);
  }

  async function openThread(id) {
    state.current = id;
    render();
    setSidebarOpen(false);
    if (!detail) return;
    detail.innerHTML = '<div class="mg-empty-state"><strong>Loading conversation...</strong></div>';
    var response = await Microgifter.get('/api/messages/thread.php?id=' + encodeURIComponent(id));
    var thread = (response.data || response).thread || {};
    var source = thread.source || {};
    var messages = (thread.messages || []).map(function (message) {
      var mine = Boolean(message.mine);
      var messageSource = message.source && message.source.label ? '<span class="mg-message-bubble-source">' + esc(message.source.label) + '</span>' : '';
      var avatar = initials(message.sender_name || (mine ? 'Me' : 'User')).slice(0, 1);
      return '<article class="mg-message-row ' + (mine ? 'is-mine' : 'is-theirs') + '"><div class="mg-message-avatar" aria-hidden="true">' + esc(avatar) + '</div><div class="mg-message-bubble ' + (mine ? 'is-mine' : 'is-theirs') + '"><strong>' + esc(message.sender_name || (mine ? 'Me' : 'User')) + '</strong><p>' + esc(message.body) + '</p><small>' + esc(message.created_at || '') + '</small>' + messageSource + '</div></article>';
    }).join('') || '<div class="mg-empty-state"><strong>No messages yet.</strong></div>';
    detail.innerHTML = '<div class="mg-thread-detail-shell"><div class="mg-thread-detail-top">' + contextBar(source) + '<div class="mg-thread-title-bar"><div class="mg-thread-title-block"><div class="mg-thread-title-line"><h2>' + esc(thread.subject || 'Conversation') + '</h2>' + sourceBadge(thread) + '<span class="mg-message-source-chip is-live">Active</span></div><p>' + esc(thread.gift_id || thread.pppm_id || thread.conversation_key || 'Gift communication') + '</p></div><div class="mg-thread-title-actions"><button type="button" aria-label="Mark complete">Done</button><button type="button" aria-label="Tag conversation">#</button><button type="button" aria-label="More actions">More</button></div></div></div><div class="mg-message-stream-wrap"><div class="mg-message-stream">' + messages + '</div></div><form class="mg-message-composer is-detached" data-thread-reply data-thread-id="' + esc(thread.public_id || thread.id || id) + '" aria-busy="false"><div class="mg-message-composer-inner"><div class="mg-message-compose-main"><textarea name="body" maxlength="4000" required placeholder="Type your message..."></textarea><div class="mg-message-compose-meta"><span data-compose-count>0 / 4000</span></div></div><div class="mg-message-compose-tools"><button class="mg-compose-tool" type="button" aria-label="Add attachment">+</button><button class="mg-compose-tool" type="button" aria-label="Add note">Aa</button><button class="mg-btn mg-btn-primary" type="submit" data-message-send>Send</button></div></div><div class="mg-message-compose-status" data-message-compose-status role="status" aria-live="polite"></div></form></div>';
    var stream = detail.querySelector('.mg-message-stream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    var textarea = detail.querySelector('textarea[name="body"]');
    var counter = detail.querySelector('[data-compose-count]');
    if (textarea && counter) textarea.addEventListener('input', function () { counter.textContent = textarea.value.length + ' / 4000'; });
  }

  async function sendReply(form) {
    if (!form || state.sending) return;
    var textarea = form.querySelector('textarea[name="body"]');
    var threadId = form.dataset.threadId || state.current;
    var body = textarea ? textarea.value.trim() : '';
    if (!threadId) return composerStatus(form, 'Select a conversation before sending.', 'error');
    if (!body) { composerStatus(form, 'Type your message before sending.', 'error'); if (textarea) textarea.focus(); return; }
    state.sending = true;
    setComposerBusy(form, true);
    composerStatus(form, 'Sending message...', '');
    try {
      var result = await Microgifter.post('/api/messages/send.php', { thread_id: threadId, body: body, csrf_token: Microgifter.getCsrfToken ? Microgifter.getCsrfToken() : '' });
      var data = result && result.data ? result.data : result;
      var nextThread = data && data.thread_id ? data.thread_id : threadId;
      if (textarea) textarea.value = '';
      composerStatus(form, 'Message sent.', 'success');
      await openThread(nextThread);
      await load({ open: false });
      if (Microgifter.toast) Microgifter.toast('Message sent.', 'success');
    } catch (error) {
      var message = error && error.message ? error.message : 'Message could not be sent.';
      composerStatus(form, message, 'error');
      if (Microgifter.toast) Microgifter.toast(message, 'error');
    } finally {
      state.sending = false;
      setComposerBusy(form, false);
    }
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', function () { setSidebarOpen(!(sidebar && sidebar.classList.contains('is-mobile-open'))); });
  if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', function () { setSidebarOpen(false); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setSidebarOpen(false); });
  if (list) list.addEventListener('click', function (event) { var row = event.target.closest('[data-thread-id]'); if (row) openThread(row.dataset.threadId).catch(function (error) { if (Microgifter.toast) Microgifter.toast(error && error.message ? error.message : 'Unable to open conversation.', 'error'); console.error(error); }); });
  if (detail) {
    detail.addEventListener('click', function (event) { var button = event.target.closest('[data-message-send]'); if (!button) return; event.preventDefault(); sendReply(button.closest('[data-thread-reply]')).catch(console.error); });
    detail.addEventListener('submit', function (event) { var form = event.target.closest('[data-thread-reply]'); if (!form) return; event.preventDefault(); sendReply(form).catch(console.error); });
  }
  if (search) search.addEventListener('input', render);
  refreshButtons.forEach(function (button) { button.addEventListener('click', function () { load().catch(console.error); }); });
  filterButtons.forEach(function (button) { button.addEventListener('click', function () { state.filter = button.dataset.messageFilter || 'all'; filterButtons.forEach(function (item) { item.classList.toggle('is-active', item === button); }); render(); }); });
  load().catch(console.error);
});