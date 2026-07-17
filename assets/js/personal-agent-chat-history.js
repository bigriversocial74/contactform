document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var app = window.MicrogifterPersonalAgent || null;
  var agentRoot = app && app.root ? app.root : null;
  var groupsHost = document.querySelector('[data-personal-agent-thread-groups]');
  if (!groupsHost || !window.Microgifter) return;

  var root = agentRoot || groupsHost.closest('[data-personal-agent-chat-sidebar]') || document;
  var state = agentRoot ? app.state : { threads: [], threadId: '' };
  var ui = agentRoot ? app.ui : {};
  var esc = agentRoot ? app.esc : function (value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  };
  var dataOf = agentRoot ? app.dataOf : function (response) {
    return response && response.data ? response.data : (response || {});
  };
  var setStatus = agentRoot ? app.setStatus : function () {};
  var renderingHistory = false;
  var refreshTimer = 0;

  function timestamp(thread) {
    return thread.last_message_at || thread.updated_at || thread.created_at || '';
  }

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
    var now = new Date();
    if (dayKey(date) === dayKey(now)) return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  function renderThreads(threads) {
    state.threads = Array.isArray(threads) ? threads : [];
    if (!state.threads.length) {
      groupsHost.innerHTML = '<div class="mg-personal-chat-empty">No chats yet. Start a new conversation.</div>';
      return;
    }

    groupsHost.innerHTML = state.threads.map(function (thread) {
      var date = parseDate(timestamp(thread));
      var active = agentRoot && thread.id === state.threadId;
      var meta = dateLabel(date) + ' · ' + Number(thread.message_count || 0) + (Number(thread.message_count || 0) === 1 ? ' message' : ' messages');
      return '<article class="mg-personal-chat-row' + (active ? ' is-active' : '') + '" data-personal-agent-thread-row="' + esc(thread.id) + '">'
        + '<a class="mg-personal-chat-open" href="/agent.php?thread=' + encodeURIComponent(thread.id) + '" data-personal-agent-open-thread="' + esc(thread.id) + '">'
        + '<strong>' + esc(thread.title || 'New chat') + '</strong><span>' + esc(meta) + '</span></a>'
        + '<button class="mg-personal-chat-delete" type="button" data-personal-agent-delete-thread="' + esc(thread.id) + '" aria-label="Delete ' + esc(thread.title || 'chat') + '" title="Delete chat">×</button>'
        + '</article>';
    }).join('');
  }

  function clearFeed() {
    if (ui.feed) ui.feed.innerHTML = '';
  }

  function appendHistoricalMessage(message) {
    if (!ui.feed || !message) return;
    var wrapper = document.createElement('div');
    wrapper.className = 'mg-personal-agent-message ' + (message.role === 'user' ? 'is-user' : 'is-assistant');
    wrapper.innerHTML = '<div>' + esc(message.body || '') + '</div>';
    ui.feed.appendChild(wrapper);
    if (message.cards && message.cards.length) {
      var grid = document.createElement('div');
      grid.className = 'mg-personal-agent-card-grid';
      grid.innerHTML = message.cards.map(function (card, index) {
        return '<article class="mg-personal-agent-chat-card"><strong>' + esc(card.title) + '</strong><p>' + esc(card.body) + '</p>'
          + (card.reason ? '<small><b>Why:</b> ' + esc(card.reason) + '</small>' : '')
          + (card.timing ? '<small><b>Timing:</b> ' + esc(card.timing) + '</small>' : '')
          + (card.warning ? '<small><b>Review:</b> ' + esc(card.warning) + '</small>' : '')
          + (card.action && card.action !== 'none' ? '<button type="button" data-agent-card-index="' + index + '">' + esc(card.action_label || 'Review') + '</button>' : '')
          + '</article>';
      }).join('');
      grid._agentCards = message.cards;
      ui.feed.appendChild(grid);
    }
  }

  function syncUrl(threadId) {
    if (!agentRoot || !window.history || !window.history.replaceState) return;
    var url = new URL(window.location.href);
    if (threadId) url.searchParams.set('thread', threadId);
    else url.searchParams.delete('thread');
    url.searchParams.delete('view');
    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
  }

  async function loadThread(threadId, announce) {
    if (!threadId) return;
    if (!agentRoot) {
      window.location.href = '/agent.php?thread=' + encodeURIComponent(threadId);
      return;
    }
    var response = await Microgifter.get('/api/user-agent/threads.php?thread_id=' + encodeURIComponent(threadId));
    var data = dataOf(response);
    var thread = data.thread || {};
    state.threadId = thread.id || threadId;
    renderingHistory = true;
    clearFeed();
    (data.messages || []).forEach(appendHistoricalMessage);
    renderingHistory = false;
    syncUrl(state.threadId);
    renderThreads(state.threads || []);

    if (thread.context_type && thread.context_type !== 'none' && thread.context_id && typeof app.selectContext === 'function') {
      app.selectContext(thread.context_type, thread.context_id).catch(function () {
        if (typeof app.showContext === 'function') app.showContext({ type: 'none', id: '', name: '', details: {} });
      });
    } else if (typeof app.showContext === 'function') {
      app.showContext({ type: 'none', id: '', name: '', details: {} });
    }

    if (ui.feed && ui.feed.lastElementChild) ui.feed.lastElementChild.scrollIntoView({ block: 'end' });
    if (announce) setStatus('Chat loaded.', 'success');
  }

  async function fetchThreads(selectInitial) {
    var response = await Microgifter.get('/api/user-agent/threads.php');
    var data = dataOf(response);
    var threads = data.threads || [];
    renderThreads(threads);
    if (!agentRoot || !selectInitial) return threads;

    var requested = new URLSearchParams(window.location.search).get('thread') || '';
    var selected = threads.find(function (thread) { return thread.id === requested; }) || threads[0];
    if (selected) {
      await loadThread(selected.id, false);
      return threads;
    }
    await createThread(false);
    return threads;
  }

  async function createThread(announce) {
    var response = await Microgifter.post('/api/user-agent/threads.php', { action: 'create' });
    var data = dataOf(response);
    var thread = data.thread || {};
    if (!thread.id) throw new Error('Unable to create a new chat.');

    if (!agentRoot) {
      window.location.href = '/agent.php?thread=' + encodeURIComponent(thread.id);
      return;
    }

    state.threadId = thread.id;
    clearFeed();
    if (typeof app.showContext === 'function') app.showContext({ type: 'none', id: '', name: '', details: {} });
    syncUrl(thread.id);
    await fetchThreads(false);
    renderThreads(state.threads || []);
    if (agentRoot.getAttribute('data-active-view') !== 'home') {
      window.location.href = '/agent.php?thread=' + encodeURIComponent(thread.id);
      return;
    }
    var input = ui.composer && ui.composer.querySelector('textarea,input');
    if (input) input.focus();
    if (announce) setStatus('New chat ready.', 'success');
  }

  async function deleteThread(threadId) {
    var thread = (state.threads || []).find(function (item) { return item.id === threadId; });
    var title = thread && thread.title ? thread.title : 'this chat';
    if (!window.confirm('Delete "' + title + '" and all of its messages?')) return;
    await Microgifter.post('/api/user-agent/threads.php', { action: 'delete', thread_id: threadId });
    var wasActive = agentRoot && state.threadId === threadId;
    if (wasActive) {
      state.threadId = '';
      clearFeed();
      syncUrl('');
    }
    var threads = await fetchThreads(false);
    if (wasActive) {
      if (threads.length) await loadThread(threads[0].id, false);
      else await createThread(false);
    }
    if (agentRoot) setStatus('Chat deleted.', 'success');
  }

  function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(function () {
      fetchThreads(false).catch(function () {});
    }, 250);
  }

  root.addEventListener('click', function (event) {
    var newChat = event.target.closest('[data-personal-agent-new-chat]');
    if (newChat) {
      event.preventDefault();
      createThread(true).catch(function (error) {
        setStatus(error.message || 'Unable to create a new chat.', 'error');
      });
      return;
    }

    var open = event.target.closest('[data-personal-agent-open-thread]');
    if (open) {
      if (!agentRoot || agentRoot.getAttribute('data-active-view') !== 'home') return;
      event.preventDefault();
      loadThread(open.getAttribute('data-personal-agent-open-thread'), true)
        .catch(function (error) { setStatus(error.message || 'Unable to load this chat.', 'error'); });
      return;
    }

    var remove = event.target.closest('[data-personal-agent-delete-thread]');
    if (remove) {
      event.preventDefault();
      event.stopPropagation();
      deleteThread(remove.getAttribute('data-personal-agent-delete-thread'))
        .catch(function (error) { setStatus(error.message || 'Unable to delete this chat.', 'error'); });
    }
  });

  if (agentRoot && ui.feed && window.MutationObserver) {
    new MutationObserver(function (mutations) {
      if (renderingHistory) return;
      var changed = mutations.some(function (mutation) { return mutation.addedNodes && mutation.addedNodes.length; });
      if (changed) scheduleRefresh();
    }).observe(ui.feed, { childList: true });
  }

  var shouldSelectInitialThread = !!agentRoot && agentRoot.getAttribute('data-active-view') === 'home';
  fetchThreads(shouldSelectInitialThread).catch(function (error) {
    groupsHost.innerHTML = '<div class="mg-personal-chat-empty">' + esc(error.message || 'Unable to load chats.') + '</div>';
    setStatus(error.message || 'Unable to load chats.', 'error');
  });
});