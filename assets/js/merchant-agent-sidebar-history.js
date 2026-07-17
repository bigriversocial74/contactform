document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-agent-chat]');
  var sidebar = document.querySelector('[data-personal-agent-chat-sidebar][data-agent-sidebar-mode="merchant"]');
  var host = sidebar && sidebar.querySelector('[data-merchant-agent-thread-groups]');
  var select = root && root.querySelector('[data-agent-thread-select]');
  var internalNew = root && root.querySelector('[data-agent-new-thread]');
  if (!root || !sidebar || !host || !select || !window.Microgifter) return;

  var state = { threads: [], activeId: '' };
  var loading = false;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function payload(response) { return response && response.data ? response.data : (response || {}); }
  function timestamp(thread) { return thread.updated_at || thread.saved_at || thread.created_at || ''; }

  function parseDate(value) {
    if (!value) return new Date();
    var raw = String(value);
    var normalized = /[zZ]$|[+-]\d{2}:?\d{2}$/.test(raw) ? raw : raw.replace(' ', 'T') + 'Z';
    var date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? new Date() : date;
  }

  function dayKey(date) {
    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
  }

  function dateLabel(date) {
    if (dayKey(date) === dayKey(new Date())) return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  function humanStatus(value) {
    value = String(value || 'active').toLowerCase();
    if (value === 'saved') return 'Saved';
    if (value === 'archived') return 'Archived';
    return 'Open';
  }

  function render() {
    var threads = Array.isArray(state.threads) ? state.threads : [];
    if (!threads.length) {
      host.innerHTML = '<div class="mg-personal-chat-empty">No merchant chats yet. Start a new conversation.</div>';
      return;
    }

    host.innerHTML = threads.map(function (thread) {
      var date = parseDate(timestamp(thread));
      var id = String(thread.id || '');
      var title = String(thread.title || 'Current chat');
      var meta = dateLabel(date) + ' · ' + humanStatus(thread.status);
      return '<article class="mg-personal-chat-row' + (id === state.activeId ? ' is-active' : '') + '" data-merchant-agent-thread-row="' + esc(id) + '">' +
        '<button class="mg-personal-chat-open" type="button" data-merchant-agent-open-thread="' + esc(id) + '"><strong>' + esc(title) + '</strong><span>' + esc(meta) + '</span></button>' +
        '<button class="mg-personal-chat-delete" type="button" data-merchant-agent-delete-thread="' + esc(id) + '" aria-label="Delete ' + esc(title) + '" title="Delete chat">×</button>' +
        '</article>';
    }).join('');
  }

  function applyState(data) {
    data = data && data.state ? data.state : data;
    data = data || {};
    state.threads = Array.isArray(data.threads) ? data.threads.slice() : [];
    var active = data.active_thread || {};
    state.activeId = String(active.id || select.value || '');
    if (active.id && !state.threads.some(function (thread) { return thread.id === active.id; })) state.threads.unshift(active);
    render();
  }

  async function loadThreads() {
    if (loading) return;
    loading = true;
    try {
      applyState(payload(await Microgifter.get('/api/ai/merchant-agent-chat.php')));
    } catch (error) {
      host.innerHTML = '<div class="mg-personal-chat-empty">' + esc(error && error.message || 'Unable to load merchant chats.') + '</div>';
    } finally {
      loading = false;
    }
  }

  function openThread(id) {
    if (!id) return;
    if (!Array.prototype.some.call(select.options, function (option) { return option.value === id; })) {
      var thread = state.threads.find(function (item) { return item.id === id; });
      var option = document.createElement('option');
      option.value = id;
      option.textContent = thread && thread.title ? thread.title : 'Merchant chat';
      select.appendChild(option);
    }
    select.value = id;
    state.activeId = id;
    render();
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  async function deleteThread(id) {
    var thread = state.threads.find(function (item) { return item.id === id; });
    var title = thread && thread.title ? thread.title : 'this chat';
    if (!window.confirm('Delete "' + title + '" and all of its Merchant Agent messages?')) return;
    await Microgifter.post('/api/ai/merchant-agent-chat.php', { action: 'delete_thread', thread_id: id });
    window.location.reload();
  }

  sidebar.addEventListener('click', function (event) {
    var newChat = event.target.closest('[data-merchant-agent-new-chat]');
    if (newChat) {
      event.preventDefault();
      if (internalNew) internalNew.click();
      return;
    }

    var remove = event.target.closest('[data-merchant-agent-delete-thread]');
    if (remove) {
      event.preventDefault();
      event.stopPropagation();
      remove.disabled = true;
      deleteThread(remove.getAttribute('data-merchant-agent-delete-thread')).catch(function (error) {
        remove.disabled = false;
        window.alert(error && error.message || 'Unable to delete this Merchant Agent chat.');
      });
      return;
    }

    var open = event.target.closest('[data-merchant-agent-open-thread]');
    if (open) {
      event.preventDefault();
      openThread(open.getAttribute('data-merchant-agent-open-thread') || '');
    }
  });

  select.addEventListener('change', function () {
    state.activeId = String(select.value || '');
    render();
    window.setTimeout(loadThreads, 180);
  });

  if ('MutationObserver' in window) {
    new MutationObserver(function () {
      if (!state.activeId && select.value) state.activeId = String(select.value);
      window.setTimeout(loadThreads, 80);
    }).observe(select, { childList: true, subtree: true, attributes: true });
  }

  loadThreads();
});