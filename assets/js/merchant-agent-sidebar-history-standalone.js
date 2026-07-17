document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var sidebar = document.querySelector('[data-personal-agent-chat-sidebar][data-agent-sidebar-mode="merchant"]');
  var host = sidebar && sidebar.querySelector('[data-merchant-agent-thread-groups]');
  if (!sidebar || !host || !window.Microgifter) return;
  if (document.querySelector('[data-merchant-agent-chat]')) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function payload(response) {
    return response && response.data ? response.data : (response || {});
  }

  function cleanLabel(value) {
    return String(value || 'Current chat').replace(/\s+·\s+(active|saved|archived)$/i, '').trim() || 'Current chat';
  }

  function render(state) {
    var threads = Array.isArray(state.threads) ? state.threads : [];
    var active = state.active_thread && state.active_thread.id ? String(state.active_thread.id) : '';
    if (!threads.length) {
      host.innerHTML = '<div class="mg-personal-chat-empty">No merchant chats yet. Start a new conversation.</div>';
      return;
    }

    host.innerHTML = '<section class="mg-personal-chat-group">' + threads.map(function (thread) {
      var id = String(thread.id || '');
      var title = cleanLabel(thread.title);
      var status = String(thread.status || 'active').toLowerCase();
      var meta = status === 'archived' ? 'Archived' : (status === 'saved' ? 'Saved' : 'Open');
      return '<article class="mg-personal-chat-row' + (id === active ? ' is-active' : '') + '">'
        + '<a class="mg-personal-chat-open" href="/merchant-agent-chat.php?thread=' + encodeURIComponent(id) + '">'
        + '<strong>' + esc(title) + '</strong><span>' + esc(meta) + '</span></a>'
        + '</article>';
    }).join('') + '</section>';
  }

  async function load() {
    try {
      host.innerHTML = '<div class="mg-personal-chat-loading">Loading merchant chats…</div>';
      render(payload(await Microgifter.get('/api/ai/merchant-agent-chat.php')));
    } catch (error) {
      host.innerHTML = '<div class="mg-personal-chat-empty">' + esc((error && error.message) || 'Unable to load merchant chats.') + '</div>';
    }
  }

  sidebar.addEventListener('click', function (event) {
    var newChat = event.target.closest('[data-merchant-agent-new-chat]');
    if (!newChat) return;
    event.preventDefault();
    window.location.assign('/merchant-agent-chat.php?new=1');
  });

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) load();
  });
  window.addEventListener('focus', load);
  load();
});
