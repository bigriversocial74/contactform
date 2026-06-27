document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-messages-center]');
  if (!app || !window.Microgifter) return;

  var list = app.querySelector('[data-thread-list]');
  var detail = app.querySelector('[data-thread-detail]');
  var search = app.querySelector('[data-message-search]');
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

  function sourceLabel(item) {
    return item && (item.source_label || (item.source && item.source.label)) || '';
  }

  function sourceSystem(item) {
    return item && (item.source_system || (item.source && item.source.system)) || '';
  }

  function sourceBadge(item) {
    var label = sourceLabel(item);
    if (!label) return '';
    var system = sourceSystem(item);
    return '<span class="mg-message-source-chip" data-source-system="' + esc(system) + '">' + esc(label) + '</span>';
  }

  function sourceContextPanel(source) {
    var context = source && source.context ? source.context : {};
    if (!context || !Object.keys(context).length) return '';
    var rows = [];
    if (context.merchant_name) rows.push('<small>Merchant</small><strong>' + esc(context.merchant_name) + '</strong>');
    if (context.campaign_title) rows.push('<small>Campaign</small><strong>' + esc(context.campaign_title) + '</strong>');
    if (context.campaign_type) rows.push('<small>Campaign type</small><strong>' + esc(context.campaign_type) + '</strong>');
    if (context.contact_source) rows.push('<small>Original contact source</small><strong>' + esc(context.contact_source) + '</strong>');
    if (context.contact_id) rows.push('<small>CRM contact</small><strong>' + esc(context.contact_id) + '</strong>');
    return '<div class="mg-thread-source-context"><span>Delivery context</span>' + rows.join('') + '</div>';
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
    if (button) {
      button.disabled = busy;
      button.textContent = busy ? 'Sending...' : 'Send';
    }
    if (form) form.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function matchesFilter(thread) {
    if (state.filter === 'unread') return Boolean(thread.unread);
    if (state.filter === 'open') return true;
    return true;
  }

  function filteredThreads() {
    var query = (search && search.value || '').toLowerCase();
    return state.threads.filter(function (thread) {
      var haystack = [thread.subject, thread.latest_message, thread.source_label, thread.source_system, thread.source_reference]
        .join(' ')
        .toLowerCase();
      return matchesFilter(thread) && haystack.includes(query);
    });
  }

  function render() {
    var items = filteredThreads();
    if (!list) return;
    list.innerHTML = items.length ? items.map(function (thread) {
      var active = state.current === thread.public_id;
      return '<article class="mg-thread-card ' + (thread.unread ? 'is-unread ' : '') + (active ? 'is-active' : '') + '" data-thread-id="' + esc(thread.public_id) + '" data-thread-initial="' + esc(initials(thread.subject || sourceLabel(thread))) + '">' +
        '<div><div class="mg-thread-card-title"><strong>' + esc(thread.subject || 'Conversation') + '</strong>' + sourceBadge(thread) + '</div>' +
        '<p>' + esc(thread.latest_message || 'No messages yet') + '</p>' +
        '<small>' + esc(thread.latest_at || thread.updated_at || '') + '</small></div></article>';
    }).join('') : '<div class="mg-empty-state"><strong>No conversations found.</strong><p>Merchant CRM, Store Canvas, gift, recipient, and PPPM conversations will appear here.</p></div>';
  }

  function renderKpis(counts) {
    var kpiNode = app.querySelector('[data-message-kpis]');
    if (!kpiNode) return;
    kpiNode.innerHTML = [
      ['Unread', counts.message_unread],
      ['Open', state.threads.length],
      ['Alerts', counts.open_alerts]
    ].map(function (item) {
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
    if (nextThread && options.open !== false) {
      await openThread(nextThread);
    }
  }

  async function openThread(id) {
    state.current = id;
    render();
    if (!detail) return;
    detail.innerHTML = '<div class="mg-empty-state"><strong>Loading conversation...</strong></div>';

    var response = await Microgifter.get('/api/messages/thread.php?id=' + encodeURIComponent(id));
    var thread = (response.data || response).thread || {};
    var source = thread.source || {};
    var sourceMeta = source.label
      ? '<div class="mg-thread-source-meta"><span>Source</span><strong>' + esc(source.label) + '</strong>' + (source.system ? '<small>' + esc(source.system) + '</small>' : '') + sourceContextPanel(source) + '</div>'
      : '';

    detail.innerHTML = '<div class="mg-thread-detail-head"><div><div class="mg-thread-detail-title"><h2>' + esc(thread.subject || 'Conversation') + '</h2>' + sourceBadge(thread) + '<span class="mg-message-source-chip is-live">Active</span></div>' +
      '<p>' + esc(thread.gift_id || thread.pppm_id || thread.conversation_key || 'Gift communication') + '</p></div>' + sourceMeta + '</div>' +
      '<div class="mg-message-stream">' + ((thread.messages || []).map(function (message) {
        var messageSource = message.source && message.source.label
          ? '<span class="mg-message-bubble-source">' + esc(message.source.label) + '</span>'
          : '';
        return '<article class="mg-message-bubble ' + (message.mine ? 'is-mine' : '') + '"><strong>' + esc(message.sender_name || 'User') + '</strong>' +
          '<p>' + esc(message.body) + '</p><small>' + esc(message.created_at || '') + '</small>' + messageSource + '</article>';
      }).join('') || '<div class="mg-empty-state"><strong>No messages yet.</strong></div>') + '</div>' +
      '<form class="mg-message-composer" data-thread-reply data-thread-id="' + esc(thread.public_id || thread.id || id) + '" aria-busy="false">' +
      '<textarea name="body" maxlength="4000" required placeholder="Type your message..."></textarea>' +
      '<button class="mg-btn mg-btn-primary" type="submit" data-message-send>Send</button>' +
      '<div class="mg-message-compose-status" data-message-compose-status role="status" aria-live="polite"></div>' +
      '</form>';

    var stream = detail.querySelector('.mg-message-stream');
    if (stream) stream.scrollTop = stream.scrollHeight;
  }

  async function sendReply(form) {
    if (!form || state.sending) return;
    var textarea = form.querySelector('textarea[name="body"]');
    var threadId = form.dataset.threadId || state.current;
    var body = textarea ? textarea.value.trim() : '';

    if (!threadId) {
      composerStatus(form, 'Select a conversation before sending.', 'error');
      return;
    }
    if (!body) {
      composerStatus(form, 'Type a message before sending.', 'error');
      if (textarea) textarea.focus();
      return;
    }

    state.sending = true;
    setComposerBusy(form, true);
    composerStatus(form, 'Sending message...', '');

    try {
      var result = await Microgifter.post('/api/messages/send.php', {
        thread_id: threadId,
        body: body,
        csrf_token: Microgifter.getCsrfToken ? Microgifter.getCsrfToken() : ''
      });
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

  if (list) {
    list.addEventListener('click', function (event) {
      var row = event.target.closest('[data-thread-id]');
      if (row) openThread(row.dataset.threadId).catch(function (error) {
        if (Microgifter.toast) Microgifter.toast(error && error.message ? error.message : 'Unable to open conversation.', 'error');
        console.error(error);
      });
    });
  }

  if (detail) {
    detail.addEventListener('click', function (event) {
      var button = event.target.closest('[data-message-send]');
      if (!button) return;
      event.preventDefault();
      sendReply(button.closest('[data-thread-reply]')).catch(console.error);
    });

    detail.addEventListener('submit', function (event) {
      var form = event.target.closest('[data-thread-reply]');
      if (!form) return;
      event.preventDefault();
      sendReply(form).catch(console.error);
    });
  }

  if (search) search.addEventListener('input', render);
  refreshButtons.forEach(function (button) {
    button.addEventListener('click', function () { load().catch(console.error); });
  });
  filterButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      state.filter = button.dataset.messageFilter || 'all';
      filterButtons.forEach(function (item) { item.classList.toggle('is-active', item === button); });
      render();
    });
  });

  load().catch(console.error);
});