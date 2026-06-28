window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  if (!MG.get || !MG.post || document.body.dataset.authenticated !== 'true') return;

  var widget = null;
  var open = false;
  var pollTimer = null;
  var state = { active: false, merchant: null, session: null, thread: null, messages: [], can_reply: false };

  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function initials(name) {
    return String(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'M';
  }
  function formatTime(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : ''));
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(parsed);
  }
  function avatarHtml(merchant) {
    merchant = merchant || {};
    if (merchant.avatar_url) return '<span class="mg-store-chat-launch-avatar"><img src="' + escapeHtml(merchant.avatar_url) + '" alt=""></span>';
    return '<span class="mg-store-chat-launch-avatar">' + escapeHtml(initials(merchant.name)) + '</span>';
  }
  function latestMessage() {
    var messages = Array.isArray(state.messages) ? state.messages : [];
    return messages.length ? messages[messages.length - 1] : null;
  }
  function setStatus(message, type) {
    if (!widget) return;
    var node = widget.querySelector('[data-store-chat-status]');
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-store-chat-status' + (type ? ' is-' + type : '');
  }

  function ensureWidget() {
    if (widget) return widget;
    widget = document.createElement('section');
    widget.className = 'mg-store-chat-widget';
    widget.setAttribute('data-store-chat-widget', '');
    widget.setAttribute('hidden', '');
    widget.innerHTML =
      '<button class="mg-store-chat-launch" type="button" data-store-chat-toggle aria-expanded="false">' +
        '<span class="mg-store-chat-avatar-slot" data-store-chat-avatar></span>' +
        '<span class="mg-store-chat-launch-copy"><strong data-store-chat-title>Merchant chat</strong><span data-store-chat-preview>Inside store</span></span>' +
        '<span class="mg-store-chat-launch-badge" data-store-chat-badge hidden>0</span>' +
      '</button>' +
      '<article class="mg-store-chat-panel" data-store-chat-panel aria-label="Merchant store chat">' +
        '<header class="mg-store-chat-head"><div class="mg-store-chat-head-main"><span data-store-chat-head-avatar></span><div><h2 data-store-chat-head-title>Merchant Store</h2><p data-store-chat-head-subtitle>Active Store Canvas session</p></div></div><button class="mg-store-chat-close" type="button" data-store-chat-close aria-label="Close store chat">×</button></header>' +
        '<div class="mg-store-chat-body" data-store-chat-body></div>' +
        '<form class="mg-store-chat-form" data-store-chat-form><textarea name="message" rows="1" maxlength="1000" placeholder="Reply to merchant..." required></textarea><button class="mg-store-chat-send" type="submit" data-store-chat-send>Send</button></form>' +
        '<p class="mg-store-chat-status" data-store-chat-status role="status"></p>' +
        '<footer class="mg-store-chat-actions"><a href="/messages.php" data-store-chat-messages>Messages</a><button class="mg-store-chat-exit" type="button" data-store-chat-exit>Leave Store</button></footer>' +
      '</article>';
    document.body.appendChild(widget);
    widget.addEventListener('click', onClick);
    widget.addEventListener('submit', onSubmit);
    widget.querySelector('textarea').addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        widget.querySelector('[data-store-chat-form]').requestSubmit();
      }
    });
    return widget;
  }

  function renderMessages() {
    var body = widget.querySelector('[data-store-chat-body]');
    if (!body) return;
    var messages = Array.isArray(state.messages) ? state.messages : [];
    if (!messages.length) {
      body.innerHTML = '<p class="mg-store-chat-empty">You are inside this merchant store. When the merchant sends a chat, it will appear here.</p>';
      return;
    }
    body.innerHTML = messages.map(function (message) {
      return '<article class="mg-store-chat-message' + (message.mine ? ' is-mine' : '') + '">' +
        '<strong>' + escapeHtml(message.mine ? 'You' : (message.sender_name || 'Merchant')) + '</strong>' +
        '<p>' + escapeHtml(message.body || '') + '</p>' +
        '<time>' + escapeHtml(formatTime(message.created_at)) + '</time>' +
      '</article>';
    }).join('');
    body.scrollTop = body.scrollHeight;
  }

  function render() {
    ensureWidget();
    if (!state.active) {
      widget.hidden = true;
      widget.classList.remove('is-open');
      open = false;
      return;
    }
    widget.hidden = false;
    widget.classList.toggle('is-open', open);
    var merchant = state.merchant || {};
    var latest = latestMessage();
    var unread = state.thread ? Number(state.thread.unread || 0) : 0;
    widget.querySelector('[data-store-chat-avatar]').innerHTML = avatarHtml(merchant);
    widget.querySelector('[data-store-chat-head-avatar]').innerHTML = avatarHtml(merchant);
    widget.querySelector('[data-store-chat-title]').textContent = merchant.name || 'Merchant Store';
    widget.querySelector('[data-store-chat-head-title]').textContent = merchant.name || 'Merchant Store';
    widget.querySelector('[data-store-chat-head-subtitle]').textContent = state.thread ? 'Merchant chat is active' : 'Inside merchant store';
    widget.querySelector('[data-store-chat-preview]').textContent = latest ? (latest.mine ? 'You: ' : '') + latest.body : 'You are shopping inside this store';
    var badge = widget.querySelector('[data-store-chat-badge]');
    badge.textContent = String(unread > 99 ? '99+' : unread);
    badge.hidden = unread < 1;
    var link = widget.querySelector('[data-store-chat-messages]');
    link.href = state.thread && state.thread.id ? '/messages.php?thread=' + encodeURIComponent(state.thread.id) : '/messages.php';
    var textarea = widget.querySelector('textarea[name="message"]');
    var send = widget.querySelector('[data-store-chat-send]');
    textarea.disabled = !state.can_reply;
    send.disabled = !state.can_reply;
    textarea.placeholder = state.can_reply ? 'Reply to merchant...' : 'Waiting for merchant chat...';
    renderMessages();
  }

  async function loadStatus(silent) {
    try {
      var data = payload(await MG.get('/api/store/chat-widget.php')) || {};
      state = data;
      if (!silent) setStatus('', '');
      render();
    } catch (error) {
      if (!silent) setStatus(error.message || 'Unable to load store chat.', 'error');
    }
  }

  async function sendReply(form) {
    var textarea = form.elements.message;
    var body = String(textarea.value || '').trim();
    if (!body) return;
    var button = widget.querySelector('[data-store-chat-send]');
    button.disabled = true;
    setStatus('Sending...', '');
    try {
      await MG.post('/api/store/chat-reply.php', { message: body });
      textarea.value = '';
      setStatus('Reply sent to merchant.', 'success');
      await loadStatus(true);
    } catch (error) {
      setStatus(error.message || 'Unable to send reply.', 'error');
    } finally {
      button.disabled = !state.can_reply;
    }
  }

  async function leaveStore(button) {
    if (!window.confirm('Leave this merchant store?')) return;
    button.disabled = true;
    setStatus('Leaving store...', '');
    try {
      await MG.post('/api/store/exit.php', {});
      state = { active: false, merchant: null, session: null, thread: null, messages: [], can_reply: false };
      render();
    } catch (error) {
      setStatus(error.message || 'Unable to leave store.', 'error');
    } finally {
      button.disabled = false;
    }
  }

  function onClick(event) {
    var toggle = event.target.closest('[data-store-chat-toggle]');
    if (toggle) {
      open = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      render();
      if (open) loadStatus(true);
      return;
    }
    if (event.target.closest('[data-store-chat-close]')) {
      open = false;
      render();
      return;
    }
    var exit = event.target.closest('[data-store-chat-exit]');
    if (exit) leaveStore(exit);
  }

  function onSubmit(event) {
    var form = event.target.closest('[data-store-chat-form]');
    if (!form) return;
    event.preventDefault();
    sendReply(form);
  }

  ensureWidget();
  loadStatus(true);
  pollTimer = window.setInterval(function () { loadStatus(true); }, 8000);
  window.addEventListener('beforeunload', function () { if (pollTimer) window.clearInterval(pollTimer); });
})(window, document);
