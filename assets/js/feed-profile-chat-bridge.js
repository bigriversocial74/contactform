window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-social-feed]');
  var dock = document.querySelector('[data-feed-chat-dock]');
  var rail = document.querySelector('[data-online-chat-rail]');
  if (!root || !dock) return;

  var authenticated = document.body.dataset.authenticated === 'true';
  var activeProfile = null;

  function payload(response) { return response && response.data ? response.data : response; }
  function safeText(value) { return String(value == null ? '' : value); }
  function clear(node) { if (node) node.replaceChildren(); }
  function initials(name) { return safeText(name || 'M').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'M'; }

  function openSignIn(profileId) {
    var target = '/feed.php';
    if (profileId) target += '?chat=' + encodeURIComponent(profileId);
    window.location.href = '/signin.php?return=' + encodeURIComponent(target);
  }

  function openWithDeepLink(profileId, threadId) {
    var url = new URL(window.location.href);
    url.searchParams.set('chat', profileId);
    if (threadId) url.searchParams.set('thread', threadId);
    window.location.href = url.pathname + url.search + url.hash;
  }

  function avatar(profile) {
    profile = profile || {};
    var name = safeText(profile.name || profile.display_name || 'Microgifter member');
    if (profile.avatar_url) {
      var image = document.createElement('img');
      image.src = profile.avatar_url;
      image.alt = '';
      image.loading = 'lazy';
      image.addEventListener('error', function () {
        var repl = document.createElement('span');
        repl.textContent = initials(name);
        image.replaceWith(repl);
      }, { once: true });
      return image;
    }
    var span = document.createElement('span');
    span.textContent = initials(name);
    return span;
  }

  function messageNode(message) {
    var row = document.createElement('div');
    row.className = 'mg-feed-chat-message' + (message && message.mine ? ' is-mine' : '');
    row.dataset.messageId = safeText(message && message.id);
    var bubble = document.createElement('div');
    bubble.className = 'mg-feed-chat-bubble';
    bubble.textContent = safeText(message && message.body);
    row.appendChild(bubble);
    return row;
  }

  function renderMessages(win, messages) {
    var box = win.querySelector('[data-chat-messages]');
    if (!box) return;
    clear(box);
    messages = Array.isArray(messages) ? messages : [];
    if (!messages.length) {
      var empty = document.createElement('div');
      empty.className = 'mg-feed-chat-empty';
      empty.textContent = 'Start a quick chat. Messages notify the other user.';
      box.appendChild(empty);
    } else {
      messages.forEach(function (message) { box.appendChild(messageNode(message)); });
    }
    box.scrollTop = box.scrollHeight;
  }

  function renderError(message) {
    clear(dock);
    var win = document.createElement('section');
    win.className = 'mg-feed-chat-window';
    win.setAttribute('role', 'dialog');
    win.setAttribute('aria-label', 'Feed chat error');
    var head = document.createElement('header');
    head.className = 'mg-feed-chat-head';
    var user = document.createElement('div');
    user.className = 'mg-feed-chat-user';
    var meta = document.createElement('div');
    var strong = document.createElement('strong');
    strong.textContent = 'Chat unavailable';
    var small = document.createElement('small');
    small.textContent = 'Try again';
    meta.append(strong, small);
    user.appendChild(meta);
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'mg-feed-chat-close';
    close.dataset.feedBridgeClose = '1';
    close.dataset.chatClose = '1';
    close.setAttribute('aria-label', 'Close chat');
    close.textContent = '×';
    head.append(user, close);
    var err = document.createElement('div');
    err.className = 'mg-feed-chat-error';
    err.textContent = message || 'Unable to open chat.';
    win.append(head, err);
    dock.appendChild(win);
  }

  function renderChat(profile, data) {
    activeProfile = profile || {};
    clear(dock);
    var win = document.createElement('section');
    win.className = 'mg-feed-chat-window';
    win.dataset.chatProfileId = safeText(activeProfile.id);
    win.setAttribute('role', 'dialog');
    win.setAttribute('aria-label', 'Chat with ' + safeText(activeProfile.name || 'Microgifter member'));

    var head = document.createElement('header');
    head.className = 'mg-feed-chat-head';
    var user = document.createElement('div');
    user.className = 'mg-feed-chat-user';
    user.appendChild(avatar(activeProfile));
    var meta = document.createElement('div');
    var strong = document.createElement('strong');
    strong.textContent = safeText(activeProfile.name || 'Microgifter member');
    var small = document.createElement('small');
    small.textContent = activeProfile.online ? 'Active now' : 'Recently active';
    meta.append(strong, small);
    user.appendChild(meta);
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'mg-feed-chat-close';
    close.dataset.feedBridgeClose = '1';
    close.dataset.chatClose = '1';
    close.setAttribute('aria-label', 'Close chat');
    close.textContent = '×';
    head.append(user, close);

    var body = document.createElement('div');
    body.className = 'mg-feed-chat-body';
    body.dataset.chatMessages = '1';

    var form = document.createElement('form');
    form.className = 'mg-feed-chat-form';
    form.dataset.feedBridgeChatForm = '1';
    var input = document.createElement('textarea');
    input.name = 'body';
    input.rows = 1;
    input.maxLength = 2000;
    input.required = true;
    input.placeholder = 'Write a message…';
    var submit = document.createElement('button');
    submit.type = 'submit';
    submit.textContent = 'Send';
    form.append(input, submit);

    win.append(head, body, form);
    dock.appendChild(win);
    renderMessages(win, data && data.messages);
    window.setTimeout(function () { input.focus(); }, 40);
  }

  async function openChat(profileId, options) {
    profileId = safeText(profileId).trim();
    if (!profileId) return false;
    if (!authenticated) {
      openSignIn(profileId);
      return true;
    }
    if (!MG.get || !MG.post) {
      openWithDeepLink(profileId, options && options.threadId || '');
      return true;
    }
    try {
      var markRead = !(options && options.markRead === false);
      var url = '/api/social/online-chat.php?profile_id=' + encodeURIComponent(profileId) + (markRead ? '&mark_read=1' : '');
      var data = payload(await MG.get(url));
      renderChat(data.profile || { id: profileId, name: 'Chat' }, data || {});
      return true;
    } catch (error) {
      renderError(error.message || 'Unable to open chat.');
      return false;
    }
  }

  async function sendMessage(form) {
    var win = form.closest('[data-chat-profile-id]');
    var profileId = win && win.dataset.chatProfileId;
    var input = form.elements.body;
    var body = safeText(input && input.value).trim();
    if (!profileId || !body || !MG.post) return;
    var button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    try {
      await MG.post('/api/social/online-chat.php', { profile_id: profileId, body: body });
      input.value = '';
      await openChat(profileId, { markRead: true });
    } catch (error) {
      renderError(error.message || 'Unable to send message.');
    } finally {
      if (button) button.disabled = false;
    }
  }

  function profileIdFromUrl(href) {
    if (!href || href === '#') return '';
    try {
      var url = new URL(href, window.location.origin);
      if (url.origin !== window.location.origin) return '';
      return url.searchParams.get('slug') || url.searchParams.get('profile') || url.searchParams.get('user') || '';
    } catch (error) {
      return '';
    }
  }

  function profileIdFromTrigger(trigger) {
    if (!trigger) return '';
    var explicit = trigger.dataset.feedChatProfile || trigger.dataset.chatProfileId || trigger.dataset.profileId || '';
    if (explicit) return explicit;
    var card = trigger.closest('[data-author-profile-id]');
    if (card && card.dataset.authorProfileId) return card.dataset.authorProfileId;
    var profileLink = trigger.closest('a[href]');
    return profileLink ? profileIdFromUrl(profileLink.getAttribute('href')) : '';
  }

  function isModifiedClick(event) {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
  }

  function shouldIgnore(target) {
    if (target.closest('[data-feed-chat-dock], .mg-feed-chat-window, .mg-feed-chat-close, [data-chat-close], [data-feed-bridge-close]')) return true;
    return Boolean(target.closest('[data-post-action], [data-story-create], [data-story-close], [data-story-next], [data-story-prev], [data-story-analytics], [data-story-highlight], [data-story-promote], [data-story-delete], form, input, textarea, select, button:not(.mg-story-viewer-profile):not(.mg-feed-avatar)'));
  }

  function handleProfileClick(event) {
    if (isModifiedClick(event)) return;
    var target = event.target;
    if (shouldIgnore(target)) return;
    var trigger = target.closest('[data-feed-chat-profile], [data-chat-profile-id], .mg-feed-avatar, .mg-feed-card-header a, .mg-story-viewer-profile');
    if (!trigger || !root.contains(trigger) || dock.contains(trigger)) return;
    var profileId = profileIdFromTrigger(trigger);
    if (!profileId) return;
    event.preventDefault();
    event.stopPropagation();
    openChat(profileId, { markRead: true });
  }

  function handleRailClick(event) {
    if (isModifiedClick(event)) return;
    var btn = event.target.closest('[data-profile-id]');
    if (!btn || !rail || !rail.contains(btn)) return;
    var profileId = btn.dataset.profileId || '';
    if (!profileId) return;
    event.preventDefault();
    event.stopPropagation();
    openChat(profileId, { markRead: true });
  }

  root.addEventListener('click', handleProfileClick, true);
  if (rail) rail.addEventListener('click', handleRailClick, true);

  dock.addEventListener('click', function (event) {
    if (!event.target.closest('[data-feed-bridge-close], [data-chat-close], .mg-feed-chat-close')) return;
    clear(dock);
    activeProfile = null;
  }, true);

  dock.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-feedBridgeChatForm], [data-feed-bridge-chat-form]');
    if (!form) return;
    event.preventDefault();
    sendMessage(form);
  });

  document.addEventListener('mg:feed-open-chat', function (event) {
    var detail = event.detail || {};
    openChat(detail.profileId || detail.profile_id || '', { markRead: true, threadId: detail.threadId || detail.thread_id || '' });
  });

  MG.feedOnlineChatBridge = MG.feedOnlineChatBridge || {};
  MG.feedOnlineChatBridge.open = openChat;
})(window, document);
