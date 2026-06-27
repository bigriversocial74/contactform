document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = document.querySelector('[data-messages-center]');
  if (!app || !window.Microgifter) return;

  var list = app.querySelector('[data-thread-list]');
  var detail = app.querySelector('[data-thread-detail]');
  var search = app.querySelector('[data-message-search]');
  var refresh = app.querySelector('[data-message-refresh]');
  var state = { threads: [], current: null };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
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

  function render() {
    var query = (search && search.value || '').toLowerCase();
    var items = state.threads.filter(function (thread) {
      return [thread.subject, thread.latest_message, thread.source_label, thread.source_system, thread.source_reference]
        .join(' ')
        .toLowerCase()
        .includes(query);
    });

    list.innerHTML = items.length ? items.map(function (thread) {
      var active = state.current === thread.public_id;
      return '<article class="mg-thread-card ' + (thread.unread ? 'is-unread ' : '') + (active ? 'is-active' : '') + '" data-thread-id="' + esc(thread.public_id) + '">' +
        '<div><div class="mg-thread-card-title"><strong>' + esc(thread.subject || 'Conversation') + '</strong>' + sourceBadge(thread) + '</div>' +
        '<p>' + esc(thread.latest_message || 'No messages yet') + '</p>' +
        '<small>' + esc(thread.latest_at || thread.updated_at || '') + '</small></div></article>';
    }).join('') : '<div class="mg-empty-state"><strong>No conversations found.</strong><p>Gift, Store Canvas, and IN/OUT Box conversations will appear here.</p></div>';
  }

  async function load() {
    var response = await Microgifter.get('/api/communications/dashboard.php?limit=100');
    var data = response.data || response;
    var counts = data.counts || {};
    state.threads = data.threads || [];

    app.querySelector('[data-message-kpis]').innerHTML = [
      ['Unread messages', counts.message_unread],
      ['Open conversations', state.threads.length],
      ['Operational alerts', counts.open_alerts]
    ].map(function (item) {
      return '<div class="mg-communications-kpi"><span>' + esc(item[0]) + '</span><strong>' + Number(item[1] || 0).toLocaleString() + '</strong></div>';
    }).join('');

    render();
    if (Microgifter.setMessageCount) Microgifter.setMessageCount(counts.message_unread || 0);
  }

  async function openThread(id) {
    state.current = id;
    render();
    detail.innerHTML = '<div class="mg-empty-state"><strong>Loading conversation…</strong></div>';

    var response = await Microgifter.get('/api/messages/thread.php?id=' + encodeURIComponent(id));
    var thread = (response.data || response).thread || {};
    var source = thread.source || {};
    var sourceMeta = source.label
      ? '<div class="mg-thread-source-meta"><span>Source</span><strong>' + esc(source.label) + '</strong>' + (source.system ? '<small>' + esc(source.system) + '</small>' : '') + '</div>'
      : '';

    detail.innerHTML = '<div class="mg-thread-detail-head"><div><div class="mg-thread-detail-title"><h2>' + esc(thread.subject || 'Conversation') + '</h2>' + sourceBadge(thread) + '</div>' +
      '<p>' + esc(thread.gift_id || thread.pppm_id || 'Gift communication') + '</p></div>' + sourceMeta + '</div>' +
      '<div class="mg-message-stream">' + ((thread.messages || []).map(function (message) {
        var messageSource = message.source && message.source.label
          ? '<span class="mg-message-bubble-source">' + esc(message.source.label) + '</span>'
          : '';
        return '<article class="mg-message-bubble ' + (message.mine ? 'is-mine' : '') + '"><strong>' + esc(message.sender_name || 'User') + '</strong>' +
          '<p>' + esc(message.body) + '</p><small>' + esc(message.created_at || '') + '</small>' + messageSource + '</article>';
      }).join('') || '<div class="mg-empty-state"><strong>No messages yet.</strong></div>') + '</div>' +
      '<form class="mg-message-composer" data-thread-reply><textarea name="body" maxlength="4000" required placeholder="Write a reply"></textarea><button class="mg-btn mg-btn-primary" type="submit">Send</button></form>';

    var stream = detail.querySelector('.mg-message-stream');
    if (stream) stream.scrollTop = stream.scrollHeight;
  }

  list.addEventListener('click', function (event) {
    var row = event.target.closest('[data-thread-id]');
    if (row) openThread(row.dataset.threadId).catch(console.error);
  });

  detail.addEventListener('submit', async function (event) {
    var form = event.target.closest('[data-thread-reply]');
    if (!form) return;
    event.preventDefault();
    await Microgifter.post('/api/messages/send.php', { thread_id: state.current, body: form.elements.body.value });
    form.reset();
    await openThread(state.current);
    await load();
  });

  if (search) search.addEventListener('input', render);
  if (refresh) refresh.addEventListener('click', function () { load().catch(console.error); });

  var requested = new URLSearchParams(location.search).get('thread');
  load().then(function () {
    if (requested) openThread(requested).catch(console.error);
  }).catch(console.error);
});
