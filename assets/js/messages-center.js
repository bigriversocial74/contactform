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
  var state = { threads: [], current: null, sending: false, filter: 'all', failedMessage: null, currentThread: null, query: '', polling: false };

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]; }); }
  function csrf() { return Microgifter.getCsrfToken ? Microgifter.getCsrfToken() : ''; }
  function initials(value) { var label = String(value || 'Conversation').replace(/^CRM:\s*/i, '').trim(); return label.split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part.charAt(0); }).join('').toUpperCase() || 'MG'; }
  function draftKey(threadId) { return 'mg:messages:draft:' + threadId; }
  function hiddenKey(threadId) { return 'mg:messages:hidden:' + threadId; }
  function getDraft(threadId) { try { return localStorage.getItem(draftKey(threadId)) || ''; } catch (error) { return ''; } }
  function setDraft(threadId, body) { try { if (body) localStorage.setItem(draftKey(threadId), body); else localStorage.removeItem(draftKey(threadId)); } catch (error) {} }
  function isHidden(threadId) { try { return localStorage.getItem(hiddenKey(threadId)) === '1'; } catch (error) { return false; } }
  function setHidden(threadId, hidden) { try { hidden ? localStorage.setItem(hiddenKey(threadId), '1') : localStorage.removeItem(hiddenKey(threadId)); } catch (error) {} }
  function sourceLabel(item) { return item && (item.source_label || (item.source && item.source.label)) || ''; }
  function sourceSystem(item) { return item && (item.source_system || (item.source && item.source.system)) || ''; }
  function sourceBadge(item) { var label = sourceLabel(item); if (!label) return ''; return '<span class="mg-message-source-chip" data-source-system="' + esc(sourceSystem(item)) + '">' + esc(label) + '</span>'; }
  function formatDateLabel(value) { if (!value) return ''; var parsed = new Date(String(value).replace(' ', 'T')); if (Number.isNaN(parsed.getTime())) return ''; var today = new Date(); var yesterday = new Date(); yesterday.setDate(today.getDate() - 1); if (parsed.toDateString() === today.toDateString()) return 'Today'; if (parsed.toDateString() === yesterday.toDateString()) return 'Yesterday'; return parsed.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: parsed.getFullYear() === today.getFullYear() ? undefined : 'numeric' }); }
  function formatTime(value) { if (!value) return ''; var parsed = new Date(String(value).replace(' ', 'T')); if (Number.isNaN(parsed.getTime())) return String(value); return parsed.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }); }
  function messageStatus(message) { if (message.status) return message.status; if (message.delivery_status) return message.delivery_status; if (message.failed) return 'Failed'; if (message.mine) return message.read_at ? 'Read' : (message.delivered_at ? 'Delivered' : 'Sent'); return 'Received'; }
  function statusClass(status) { return 'is-' + String(status || 'sent').toLowerCase().replace(/[^a-z0-9]+/g, '-'); }

  function composerStatus(form, message, type) { var node = form && form.querySelector('[data-message-compose-status]'); if (!node) return; node.textContent = message || ''; node.classList.toggle('is-visible', Boolean(message)); node.classList.toggle('is-success', type === 'success'); node.classList.toggle('is-error', type === 'error'); }
  function composerFailed(form, message) { var node = form && form.querySelector('[data-message-compose-status]'); if (!node) return; node.innerHTML = '<span>' + esc(message || 'Message could not be sent.') + '</span><button type="button" data-message-retry>Retry</button>'; node.classList.add('is-visible', 'is-error'); node.classList.remove('is-success'); }
  function autoGrow(textarea) { if (!textarea) return; textarea.style.height = 'auto'; textarea.style.height = Math.min(textarea.scrollHeight, 118) + 'px'; }
  function syncComposer(form) { if (!form) return; var textarea = form.querySelector('textarea[name="body"]'); var button = form.querySelector('[data-message-send]'); var counter = form.querySelector('[data-compose-count]'); var body = String(textarea && textarea.value || ''); if (button) button.disabled = state.sending || !body.trim(); if (counter) counter.textContent = body.length + ' / 4000'; autoGrow(textarea); if (form.dataset.threadId) setDraft(form.dataset.threadId, body); }
  function setComposerBusy(form, busy) { var textarea = form && form.querySelector('textarea[name="body"]'); var button = form && form.querySelector('[data-message-send]'); if (textarea) textarea.disabled = busy; if (button) { button.disabled = busy || !String(textarea && textarea.value || '').trim(); button.textContent = busy ? 'Sending...' : 'Send'; } if (form) form.setAttribute('aria-busy', busy ? 'true' : 'false'); }
  function setSidebarOpen(open) { if (!sidebar) return; sidebar.classList.toggle('is-mobile-open', open); document.body.classList.toggle('mg-messages-sidebar-open', open); if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false'); if (sidebarBackdrop) sidebarBackdrop.hidden = !open; }
  function nearBottom(stream) { return !stream || stream.scrollHeight - stream.scrollTop - stream.clientHeight < 90; }
  function updateJumpButton(stream, button) { if (!stream || !button) return; button.hidden = nearBottom(stream); }

  function threadStatus(thread) { return String(thread.crm_status || (thread.crm_ops && thread.crm_ops.status) || 'open').toLowerCase(); }
  function threadLabel(thread) { return String(thread.crm_label || (thread.crm_ops && thread.crm_ops.label) || '').toLowerCase(); }
  function matchesFilter(thread) {
    var id = thread.public_id || thread.id || '';
    if (isHidden(id)) return false;
    if (state.filter === 'unread') return Boolean(thread.unread);
    if (state.filter === 'resolved') return threadStatus(thread) === 'resolved';
    if (state.filter === 'open') return threadStatus(thread) !== 'resolved';
    if (state.filter === 'assigned') return Boolean(thread.assigned_to_me || thread.assigned_user_id);
    if (state.filter === 'high-value') return thread.high_value || threadLabel(thread) === 'high value';
    if (state.filter === 'drafts') return Boolean(getDraft(id).trim());
    return true;
  }

  function filteredThreads() {
    var query = (search && search.value || '').toLowerCase();
    return state.threads.filter(function (thread) {
      var id = thread.public_id || thread.id || '';
      var draft = getDraft(id);
      var haystack = [thread.subject, thread.latest_message, thread.source_label, thread.source_system, thread.source_reference, thread.crm_status, thread.crm_label, draft].join(' ').toLowerCase();
      return matchesFilter(thread) && haystack.includes(query);
    });
  }

  function render() {
    var items = filteredThreads();
    if (!list) return;
    list.innerHTML = items.length ? items.map(function (thread) {
      var id = thread.public_id || thread.id || '';
      var active = state.current === id;
      var unread = Number(thread.unread || 0);
      var draft = getDraft(id).trim();
      var status = threadStatus(thread);
      var label = thread.crm_label || (status === 'resolved' ? 'Resolved' : 'Open');
      var preview = draft ? '<p class="mg-thread-draft-preview"><b>Draft:</b> ' + esc(draft) + '</p>' : '<p>' + esc(thread.latest_message || 'No messages yet') + '</p>';
      return '<article class="mg-thread-card ' + (thread.unread ? 'is-unread ' : '') + (active ? 'is-active ' : '') + (draft ? 'has-draft ' : '') + '" data-thread-id="' + esc(id) + '" data-thread-initial="' + esc(initials(thread.subject || sourceLabel(thread))) + '" data-thread-status="' + esc(status) + '">' +
        '<div><div class="mg-thread-card-title"><strong>' + esc(thread.subject || 'Conversation') + '</strong>' + sourceBadge(thread) + (unread ? '<span class="mg-thread-unread-count">' + unread + '</span>' : '') + '</div>' + preview + '<small><span>' + esc(label) + '</span><span>' + esc(thread.latest_at || thread.updated_at || '') + '</span></small></div></article>';
    }).join('') : '<div class="mg-empty-state"><strong>No conversations found.</strong><p>Try another filter or search term.</p></div>';
  }

  function renderKpis(counts) {
    var kpiNode = app.querySelector('[data-message-kpis]');
    if (!kpiNode) return;
    kpiNode.innerHTML = [['Unread', counts.message_unread], ['Open', state.threads.filter(function (thread) { return threadStatus(thread) !== 'resolved'; }).length], ['Alerts', counts.open_alerts]].map(function (item) {
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
    if (Microgifter.setNotificationCount && typeof counts.notification_unread !== 'undefined') Microgifter.setNotificationCount(counts.notification_unread || 0);
    var requested = new URLSearchParams(location.search).get('thread');
    var nextThread = requested || state.current || (state.threads[0] && state.threads[0].public_id) || '';
    if (nextThread && options.open !== false) await openThread(nextThread, { quiet: options.quiet });
  }

  function highlightedBody(body) {
    var output = esc(body);
    var query = String(state.query || '').trim();
    if (!query) return output;
    var parts = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return output.replace(new RegExp('(' + parts + ')', 'ig'), '<mark>$1</mark>');
  }

  function renderMessages(messagesData) {
    var html = [];
    var previousDate = '';
    (messagesData || []).forEach(function (message, index) {
      var mine = Boolean(message.mine);
      var dateLabel = formatDateLabel(message.created_at || message.created_at_iso || '');
      var senderName = message.sender_name || (mine ? 'Me' : 'User');
      var next = messagesData[index + 1] || null;
      var prev = messagesData[index - 1] || null;
      var prevSame = prev && Boolean(prev.mine) === mine && (prev.sender_name || (prev.mine ? 'Me' : 'User')) === senderName && formatDateLabel(prev.created_at || prev.created_at_iso || '') === dateLabel;
      var nextSame = next && Boolean(next.mine) === mine && (next.sender_name || (next.mine ? 'Me' : 'User')) === senderName && formatDateLabel(next.created_at || next.created_at_iso || '') === dateLabel;
      var status = messageStatus(message);
      var messageSource = message.source && message.source.label && !nextSame ? '<span class="mg-message-bubble-source">' + esc(message.source.label) + '</span>' : '';
      var matches = state.query && String(message.body || '').toLowerCase().includes(String(state.query).toLowerCase());
      if (dateLabel && dateLabel !== previousDate) html.push('<div class="mg-message-date-divider"><span>' + esc(dateLabel) + '</span></div>');
      html.push('<article class="mg-message-row ' + (mine ? 'is-mine' : 'is-theirs') + (prevSame ? ' is-grouped-prev' : '') + (nextSame ? ' is-grouped-next' : '') + (matches ? ' is-search-match' : '') + '">' + (!mine ? '<div class="mg-message-avatar" aria-hidden="true">' + (prevSame ? '' : esc(initials(senderName).slice(0, 1))) + '</div>' : '') + '<div class="mg-message-bubble ' + (mine ? 'is-mine' : 'is-theirs') + '">' + (!prevSame ? '<strong>' + esc(senderName) + '</strong>' : '') + '<p>' + highlightedBody(message.body) + '</p>' + (!nextSame ? '<div class="mg-message-meta"><small>' + esc(formatTime(message.created_at) || message.created_at || '') + '</small><span class="mg-message-status ' + esc(statusClass(status)) + '">' + esc(status) + '</span></div>' : '') + messageSource + '</div></article>');
      previousDate = dateLabel;
    });
    if (state.failedMessage && state.failedMessage.threadId === state.current) {
      html.push('<article class="mg-message-row is-mine is-failed-local"><div class="mg-message-bubble is-mine"><p>' + esc(state.failedMessage.body || '') + '</p><div class="mg-message-meta"><small>Not sent</small><span class="mg-message-status is-failed">Failed</span><button type="button" data-message-retry>Retry</button><button type="button" data-copy-failed>Copy</button></div></div></article>');
    }
    return html.join('') || '<div class="mg-empty-state mg-empty-chat"><strong>No messages yet.</strong><p>Send the first message in this thread.</p></div>';
  }

  function threadSubtitle(thread) {
    var status = threadStatus(thread) === 'resolved' ? 'Resolved' : 'Open';
    var source = sourceLabel(thread) || 'Messages';
    return source + ' · ' + status + (thread.latest_at ? ' · Last active ' + thread.latest_at : '');
  }

  async function openThread(id, options) {
    options = options || {};
    state.current = id;
    if (!options.quiet) state.failedMessage = null;
    render();
    setSidebarOpen(false);
    if (!detail) return;
    if (!options.quiet) detail.innerHTML = '<div class="mg-message-skeleton"><span></span><span></span><span></span></div>';
    var response = await Microgifter.get('/api/messages/thread.php?id=' + encodeURIComponent(id));
    var thread = (response.data || response).thread || {};
    state.currentThread = thread;
    var streamBefore = detail.querySelector('.mg-message-stream');
    var shouldStick = nearBottom(streamBefore);
    var messages = renderMessages(thread.messages || []);
    var threadTitle = thread.subject || 'Conversation';
    var crmOps = thread.crm_ops || {};

    detail.innerHTML = '<div class="mg-thread-detail-shell is-chat-only">' +
      '<div class="mg-thread-detail-top"><div class="mg-thread-title-bar"><div class="mg-thread-title-block"><div class="mg-thread-title-line"><h2>' + esc(threadTitle) + '</h2>' + sourceBadge(thread) + '</div><p class="mg-thread-clean-subtitle">' + esc(threadSubtitle(thread)) + '</p></div>' +
      '<div class="mg-thread-title-actions"><button type="button" data-thread-search-toggle aria-label="Search conversation" title="Search conversation">⌕</button><button type="button" data-thread-menu-toggle aria-label="Thread actions" title="Thread actions">•••</button><div class="mg-thread-action-menu" data-thread-menu hidden><button type="button" data-thread-action="resolve">' + (crmOps.status === 'resolved' ? 'Reopen' : 'Mark resolved') + '</button><button type="button" data-thread-action="assign">Assign to me</button><button type="button" data-thread-action="high-value">Label high value</button><button type="button" data-thread-action="archive">Archive</button><button type="button" data-thread-action="hide">Delete from view</button><button type="button" data-thread-action="profile">View CRM profile</button></div></div></div>' +
      '<div class="mg-thread-search-row" data-thread-search-row hidden><input type="search" data-thread-search placeholder="Search this conversation..."><button type="button" data-thread-search-clear>Clear</button></div>' +
      '<div class="mg-message-stream-wrap"><button type="button" class="mg-new-message-jump" data-jump-latest hidden>Jump to latest ↓</button><div class="mg-message-stream">' + messages + '</div></div>' +
      '<form class="mg-message-composer is-detached" data-thread-reply data-thread-id="' + esc(thread.public_id || thread.id || id) + '" aria-busy="false">' +
        '<div class="mg-attachment-tray" data-attachment-tray hidden><button type="button">Attach image</button><button type="button">Attach PDF</button><button type="button">Attach reward</button><button type="button">Attach claim link</button></div>' +
        '<div class="mg-message-composer-inner"><div class="mg-message-compose-main"><textarea name="body" maxlength="4000" required rows="1" placeholder="Enter message... Shift+Enter for new line"></textarea><div class="mg-message-compose-meta"><span></span><span data-compose-count>0 / 4000</span></div></div>' +
        '<div class="mg-message-compose-tools"><button class="mg-compose-tool" type="button" data-attach-toggle aria-label="Add attachment">⌕</button><button class="mg-compose-tool" type="button" aria-label="Add emoji">☺</button><button class="mg-compose-tool" type="button" aria-label="Insert code">{ }</button><button class="mg-btn mg-btn-primary" type="submit" data-message-send disabled>Send</button></div></div>' +
        '<div class="mg-message-compose-status" data-message-compose-status role="status" aria-live="polite"></div></form></div>';

    var stream = detail.querySelector('.mg-message-stream');
    var jump = detail.querySelector('[data-jump-latest]');
    if (stream) { stream.scrollTop = shouldStick ? stream.scrollHeight : stream.scrollTop; stream.addEventListener('scroll', function () { updateJumpButton(stream, jump); }); if (shouldStick) stream.scrollTop = stream.scrollHeight; }
    if (jump) jump.addEventListener('click', function () { if (stream) stream.scrollTo({ top: stream.scrollHeight, behavior: 'smooth' }); });
    var textarea = detail.querySelector('textarea[name="body"]');
    var form = detail.querySelector('[data-thread-reply]');
    if (textarea && form) { var savedDraft = getDraft(form.dataset.threadId || ''); if (savedDraft && !textarea.value) textarea.value = savedDraft; textarea.addEventListener('input', function () { syncComposer(form); render(); }); textarea.addEventListener('keydown', function (event) { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendReply(form).catch(console.error); } }); syncComposer(form); }
  }

  async function threadCrmAction(action) {
    if (!state.current) return;
    var payload = { thread_id: state.current, action: 'update_state', csrf_token: csrf() };
    if (action === 'resolve') payload.status = state.currentThread && state.currentThread.crm_ops && state.currentThread.crm_ops.status === 'resolved' ? 'open' : 'resolved';
    if (action === 'assign') payload.assign_to_self = true;
    if (action === 'high-value') payload.label = 'High value';
    await Microgifter.post('/api/messages/crm-ops.php', payload);
    await load({ open: false, quiet: true });
    await openThread(state.current, { quiet: true });
  }

  async function threadSettingAction(action) {
    if (!state.current) return;
    if (action === 'hide') { setHidden(state.current, true); state.current = null; await load(); return; }
    await Microgifter.post('/api/communications/thread-settings.php', { thread_id: state.current, action: action, csrf_token: csrf() });
    if (action === 'archive') { state.current = null; detail.innerHTML = '<div class="mg-empty-state mg-messages-empty-state"><strong>Conversation archived</strong><p>Select another thread to continue.</p></div>'; }
    await load({ open: false, quiet: true });
  }

  async function sendReply(form) {
    if (!form || state.sending) return;
    var textarea = form.querySelector('textarea[name="body"]');
    var threadId = form.dataset.threadId || state.current;
    var body = textarea ? textarea.value.trim() : '';
    if (!threadId) return composerStatus(form, 'Select a conversation before sending.', 'error');
    if (!body) { composerStatus(form, 'Type a message before sending.', 'error'); if (textarea) textarea.focus(); return; }
    state.sending = true; setComposerBusy(form, true); composerStatus(form, 'Sending message...', '');
    try {
      var result = await Microgifter.post('/api/messages/send.php', { thread_id: threadId, body: body, csrf_token: csrf() });
      var data = result && result.data ? result.data : result;
      var nextThread = data && data.thread_id ? data.thread_id : threadId;
      if (textarea) textarea.value = '';
      setDraft(threadId, ''); state.failedMessage = null; composerStatus(form, 'Message sent.', 'success');
      await openThread(nextThread); await load({ open: false });
      document.dispatchEvent(new CustomEvent('mg:messages:refresh'));
      if (Microgifter.toast) Microgifter.toast('Message sent.', 'success');
    } catch (error) {
      var message = error && error.message ? error.message : 'Message could not be sent.';
      state.failedMessage = { threadId: threadId, body: body }; setDraft(threadId, body); composerFailed(form, message); openThread(threadId, { quiet: true }).catch(function () {}); if (Microgifter.toast) Microgifter.toast(message, 'error');
    } finally { state.sending = false; setComposerBusy(form, false); syncComposer(form); }
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', function () { setSidebarOpen(!(sidebar && sidebar.classList.contains('is-mobile-open'))); });
  if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', function () { setSidebarOpen(false); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setSidebarOpen(false); });
  if (list) list.addEventListener('click', function (event) { var row = event.target.closest('[data-thread-id]'); if (row) openThread(row.dataset.threadId).catch(function (error) { if (Microgifter.toast) Microgifter.toast(error && error.message ? error.message : 'Unable to open conversation.', 'error'); console.error(error); }); });

  if (detail) {
    detail.addEventListener('click', function (event) {
      var retry = event.target.closest('[data-message-retry]');
      if (retry) { var form = detail.querySelector('[data-thread-reply]'); var textarea = form && form.querySelector('textarea[name="body"]'); if (textarea && state.failedMessage && state.failedMessage.body) { textarea.value = state.failedMessage.body; syncComposer(form); } sendReply(form).catch(console.error); return; }
      var failedCopy = event.target.closest('[data-copy-failed]');
      if (failedCopy && navigator.clipboard && state.failedMessage) { navigator.clipboard.writeText(state.failedMessage.body || '').catch(function () {}); return; }
      var menuToggle = event.target.closest('[data-thread-menu-toggle]');
      if (menuToggle) { var menu = detail.querySelector('[data-thread-menu]'); if (menu) menu.hidden = !menu.hidden; return; }
      var searchToggle = event.target.closest('[data-thread-search-toggle]');
      if (searchToggle) { var row = detail.querySelector('[data-thread-search-row]'); if (row) { row.hidden = !row.hidden; if (!row.hidden) row.querySelector('input').focus(); } return; }
      var clearSearch = event.target.closest('[data-thread-search-clear]');
      if (clearSearch) { state.query = ''; openThread(state.current, { quiet: true }).catch(console.error); return; }
      var attach = event.target.closest('[data-attach-toggle]');
      if (attach) { var tray = detail.querySelector('[data-attachment-tray]'); if (tray) tray.hidden = !tray.hidden; return; }
      var actionButton = event.target.closest('[data-thread-action]');
      if (actionButton) { var action = actionButton.dataset.threadAction; if (['resolve','assign','high-value'].includes(action)) threadCrmAction(action).catch(console.error); else if (action === 'profile') detail.classList.toggle('show-profile'); else threadSettingAction(action).catch(console.error); return; }
    });
    detail.addEventListener('input', function (event) { if (event.target.matches('[data-thread-search]')) { state.query = event.target.value; openThread(state.current, { quiet: true }).catch(console.error); } });
    detail.addEventListener('submit', function (event) { var form = event.target.closest('[data-thread-reply]'); if (!form) return; event.preventDefault(); sendReply(form).catch(console.error); });
  }

  if (search) search.addEventListener('input', render);
  refreshButtons.forEach(function (button) { button.addEventListener('click', function () { load().catch(console.error); }); });
  filterButtons.forEach(function (button) { button.addEventListener('click', function () { state.filter = button.dataset.messageFilter || 'all'; filterButtons.forEach(function (item) { item.classList.toggle('is-active', item === button); }); render(); }); });

  function poll() { if (document.hidden || state.sending) return; load({ open: false, quiet: true }).then(function () { if (state.current) return openThread(state.current, { quiet: true }); }).catch(function () {}); }
  setInterval(poll, 25000);
  document.addEventListener('mg:signals:poll', poll);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  load().catch(console.error);
});