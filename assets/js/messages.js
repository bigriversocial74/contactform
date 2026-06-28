document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-messages-center]');
  if (!app || !window.Microgifter) return;

  var list = app.querySelector('[data-thread-list]');
  var detail = app.querySelector('[data-thread-detail]');
  var search = app.querySelector('[data-message-search]');
  var refresh = app.querySelector('[data-message-refresh]');
  var filters = Array.from(app.querySelectorAll('[data-message-filter]'));
  var sidebar = app.querySelector('[data-app-sidebar]');
  var sidebarToggle = app.querySelector('[data-messages-sidebar-toggle]');
  var sidebarBackdrop = app.querySelector('[data-messages-sidebar-backdrop]');
  var state = { threads: [], current: '', filter: 'all', sending: false };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  function csrf() {
    return window.Microgifter.getCsrfToken ? window.Microgifter.getCsrfToken() : '';
  }

  function initials(value) {
    return String(value || 'MG')
      .replace(/^CRM:\s*/i, '')
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map(function (part) { return part.charAt(0); })
      .join('')
      .toUpperCase() || 'MG';
  }

  function sourceLabel(item) {
    return item && (item.source_label || (item.source && item.source.label)) || 'Messages';
  }

  function sourceChip(item) {
    return '<span class="mg-message-source-chip">' + esc(sourceLabel(item)) + '</span>';
  }

  function time(value) {
    if (!value) return '';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }

  function day(value) {
    if (!value) return 'Today';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return 'Today';
    var today = new Date();
    var yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    if (date.toDateString() === today.toDateString()) return 'Today';
    if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  function draftKey(id) {
    return 'mg:messages:draft:' + id;
  }

  function getDraft(id) {
    try { return localStorage.getItem(draftKey(id)) || ''; } catch (e) { return ''; }
  }

  function setDraft(id, text) {
    try { text ? localStorage.setItem(draftKey(id), text) : localStorage.removeItem(draftKey(id)); } catch (e) {}
  }

  function setSidebarOpen(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('is-mobile-open', open);
    document.body.classList.toggle('mg-messages-sidebar-open', open);
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (sidebarBackdrop) sidebarBackdrop.hidden = !open;
  }

  function statusText(thread) {
    var status = String(thread.crm_status || 'open').toLowerCase();
    return status === 'resolved' ? 'Resolved' : 'Open';
  }

  function filteredThreads() {
    var query = String(search && search.value || '').toLowerCase();
    return state.threads.filter(function (thread) {
      if (state.filter === 'unread' && !thread.unread) return false;
      if (state.filter === 'open' && String(thread.crm_status || 'open').toLowerCase() === 'resolved') return false;
      var text = [thread.subject, thread.latest_message, thread.source_label, getDraft(thread.public_id || '')].join(' ').toLowerCase();
      return !query || text.indexOf(query) !== -1;
    });
  }

  function renderKpis(counts) {
    var node = app.querySelector('[data-message-kpis]');
    if (!node) return;
    node.innerHTML = [['Unread', counts.message_unread], ['Open', state.threads.length], ['Alerts', counts.open_alerts]].map(function (item) {
      return '<div class="mg-message-kpi"><span>' + esc(item[0]) + '</span><strong>' + Number(item[1] || 0).toLocaleString() + '</strong></div>';
    }).join('');
  }

  function renderThreads() {
    if (!list) return;
    var rows = filteredThreads();
    list.innerHTML = rows.length ? rows.map(function (thread) {
      var id = thread.public_id || thread.id || '';
      var draft = getDraft(id).trim();
      var preview = draft ? '<p class="mg-thread-draft-preview"><b>Draft:</b> ' + esc(draft) + '</p>' : '<p>' + esc(thread.latest_message || 'No messages yet') + '</p>';
      return '<article class="mg-thread-card ' + (state.current === id ? 'is-active ' : '') + (thread.unread ? 'is-unread ' : '') + (draft ? 'has-draft' : '') + '" data-thread-id="' + esc(id) + '" data-thread-initial="' + esc(initials(thread.subject || sourceLabel(thread))) + '"><div><div class="mg-thread-card-title"><strong>' + esc(thread.subject || 'Conversation') + '</strong>' + sourceChip(thread) + (thread.unread ? '<span class="mg-thread-unread-count">' + Number(thread.unread || 1) + '</span>' : '') + '</div>' + preview + '<small><span>' + esc(statusText(thread)) + '</span><span>' + esc(thread.latest_at || thread.updated_at || '') + '</span></small></div></article>';
    }).join('') : '<div class="mg-empty-state"><strong>No conversations found.</strong><p>Try another filter.</p></div>';
  }

  function messagesHtml(messages) {
    var lastDay = '';
    return (messages || []).map(function (message) {
      var mine = !!message.mine;
      var dateLabel = day(message.created_at || message.created_at_iso);
      var divider = dateLabel !== lastDay ? '<div class="mg-message-date-divider"><span>' + esc(dateLabel) + '</span></div>' : '';
      var sender = esc(message.sender_name || (mine ? 'Me' : 'User'));
      var avatar = '<div class="mg-message-avatar">' + esc(initials(message.sender_name || (mine ? 'Me' : 'User')).slice(0, 2)) + '</div>';
      var source = message.source && message.source.label ? message.source.label : sourceLabel(message);
      var meta = '<div class="mg-message-meta"><small>' + esc(time(message.created_at)) + '</small><span class="mg-message-bubble-source">' + esc(source) + '</span>' + (mine ? '<span class="mg-message-status">Sent</span>' : '') + '</div>';
      lastDay = dateLabel;
      return divider + '<article class="mg-message-row ' + (mine ? 'is-mine' : 'is-theirs') + '">' + (!mine ? avatar : '') + '<div class="mg-message-bubble ' + (mine ? 'is-mine' : 'is-theirs') + '"><strong>' + sender + '</strong><p>' + esc(message.body || '') + '</p>' + meta + '</div>' + (mine ? avatar : '') + '</article>';
    }).join('') || '<div class="mg-empty-state mg-empty-chat"><strong>No messages yet.</strong><p>Send the first message in this conversation.</p></div>';
  }

  function syncComposer(form) {
    var textarea = form && form.querySelector('textarea[name="body"]');
    var button = form && form.querySelector('[data-message-send]');
    var count = form && form.querySelector('[data-compose-count]');
    var text = textarea ? textarea.value : '';
    if (button) button.disabled = state.sending || !text.trim();
    if (count) count.textContent = text.length + ' / 4000';
    if (textarea) {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 118) + 'px';
    }
    if (form && form.dataset.threadId) setDraft(form.dataset.threadId, text);
  }

  async function openThread(id) {
    state.current = id;
    renderThreads();
    setSidebarOpen(false);
    detail.innerHTML = '<div class="mg-message-skeleton"><span></span><span></span><span></span></div>';
    var response = await window.Microgifter.get('/api/messages/thread.php?id=' + encodeURIComponent(id));
    var thread = (response.data || response).thread || {};
    var threadId = thread.public_id || thread.id || id;
    var threadStatus = thread.crm_ops && thread.crm_ops.status || 'Open';

    detail.innerHTML = '<div class="mg-thread-detail-shell is-chat-only"><header class="mg-thread-detail-top"><div class="mg-thread-title-bar"><div class="mg-thread-title-block"><div class="mg-thread-title-line"><h2>' + esc(thread.subject || 'Conversation') + '</h2>' + sourceChip(thread) + '</div><p class="mg-thread-clean-subtitle">' + esc(sourceLabel(thread)) + ' · ' + esc(threadStatus) + '</p></div></div></header><main class="mg-message-stream-wrap"><div class="mg-message-stream">' + messagesHtml(thread.messages || []) + '</div></main><form class="mg-message-composer is-detached" data-thread-reply data-thread-id="' + esc(threadId) + '"><div class="mg-message-composer-inner"><div class="mg-message-compose-main"><textarea name="body" maxlength="4000" rows="1" placeholder="Enter message... Shift+Enter for new line"></textarea><div class="mg-message-compose-meta"><span></span><span data-compose-count>0 / 4000</span></div></div><div class="mg-message-compose-tools"><button class="mg-btn mg-btn-primary" type="submit" data-message-send disabled>Send</button></div></div><div class="mg-message-compose-status" data-message-compose-status></div></form></div>';

    var stream = detail.querySelector('.mg-message-stream');
    if (stream) stream.scrollTop = stream.scrollHeight;
    var form = detail.querySelector('[data-thread-reply]');
    var textarea = form.querySelector('textarea[name="body"]');
    var saved = getDraft(threadId);
    if (saved) textarea.value = saved;
    textarea.addEventListener('input', function () { syncComposer(form); renderThreads(); });
    textarea.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendReply(form);
      }
    });
    syncComposer(form);
  }

  async function sendReply(form) {
    if (!form || state.sending) return;
    var textarea = form.querySelector('textarea[name="body"]');
    var body = textarea.value.trim();
    var id = form.dataset.threadId || state.current;
    if (!body || !id) return;
    state.sending = true;
    syncComposer(form);
    try {
      await window.Microgifter.post('/api/messages/send.php', { thread_id: id, body: body, csrf_token: csrf() });
      setDraft(id, '');
      textarea.value = '';
      await openThread(id);
      await load(false);
      if (window.Microgifter.toast) window.Microgifter.toast('Message sent.', 'success');
    } catch (error) {
      if (window.Microgifter.toast) window.Microgifter.toast(error.message || 'Message could not be sent.', 'error');
    } finally {
      state.sending = false;
      syncComposer(form);
    }
  }

  async function load(openFirst) {
    var response = await window.Microgifter.get('/api/communications/dashboard.php?limit=100');
    var data = response.data || response;
    state.threads = data.threads || [];
    renderKpis(data.counts || {});
    renderThreads();
    if (openFirst !== false) {
      var requested = new URLSearchParams(location.search).get('thread');
      var id = requested || state.current || (state.threads[0] && state.threads[0].public_id);
      if (id) await openThread(id);
    }
  }

  if (list) list.addEventListener('click', function (event) {
    var row = event.target.closest('[data-thread-id]');
    if (row) openThread(row.dataset.threadId).catch(console.error);
  });
  if (detail) detail.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-thread-reply]');
    if (form) {
      event.preventDefault();
      sendReply(form).catch(console.error);
    }
  });
  if (search) search.addEventListener('input', renderThreads);
  if (refresh) refresh.addEventListener('click', function () { load(false).catch(console.error); });
  filters.forEach(function (button) {
    button.addEventListener('click', function () {
      state.filter = button.dataset.messageFilter || 'all';
      filters.forEach(function (item) { item.classList.toggle('is-active', item === button); });
      renderThreads();
    });
  });
  if (sidebarToggle) sidebarToggle.addEventListener('click', function () { setSidebarOpen(!(sidebar && sidebar.classList.contains('is-mobile-open'))); });
  if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', function () { setSidebarOpen(false); });
  load().catch(console.error);
});
