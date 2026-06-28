window.Microgifter = window.Microgifter || {};
(function (document) {
  'use strict';
  var lastTrigger = null;
  var pollingStarted = false;
  function escapeHtml(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]; }); }
  function apiGet(url) {
    if (window.Microgifter && typeof window.Microgifter.get === 'function') return window.Microgifter.get(url);
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (response) { if (!response.ok) throw new Error('Unable to load.'); return response.json(); });
  }
  function apiPost(url, payload) {
    if (window.Microgifter && typeof window.Microgifter.post === 'function') return window.Microgifter.post(url, payload);
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload || {}) }).then(function (response) { if (!response.ok) throw new Error('Unable to save.'); return response.json(); });
  }
  function setExpanded(node, open) { var trigger = node && node.querySelector('[data-header-signal-trigger], [data-mg-auth-trigger]'); if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  function setBadgeCount(badge, count) {
    if (!badge) return;
    var value = Math.max(0, parseInt(count || 0, 10) || 0);
    badge.textContent = value > 99 ? '99+' : String(value);
    badge.hidden = value === 0;
    badge.classList.toggle('has-unread', value > 0);
    badge.setAttribute('aria-label', value + ' unread');
    if (value === 0) badge.removeAttribute('style');
  }
  function setNotificationCount(count) { document.querySelectorAll('[data-notification-badge]').forEach(function (badge) { setBadgeCount(badge, count); }); }
  function setMessageCount(count) { document.querySelectorAll('[data-message-badge]').forEach(function (badge) { setBadgeCount(badge, count); }); }
  function ensureSignalPanel(shell) {
    var existing = shell.querySelector('[data-header-signal-panel]');
    if (existing) { existing.classList.add('mg-header-signal-panel'); return existing; }
    var type = shell.dataset.headerSignal || 'notifications';
    var panel = document.createElement('div');
    panel.className = 'mg-header-signal-panel';
    panel.dataset.headerSignalPanel = type;
    panel.innerHTML = '<div class="mg-header-signal-empty"><strong>Loading…</strong></div>';
    shell.appendChild(panel);
    return panel;
  }
  function notificationMarkup(items) {
    if (!items.length) return '<div class="mg-header-signal-empty"><strong>No new notifications</strong><p>Gift, claim, campaign, delivery, and account updates will appear here.</p></div>';
    return '<div class="mg-header-signal-list">' + items.map(function (item) {
      var category = ['claim_locked','claim_expired','delivery_failed','distribution_failed','security','system_alert'].includes(item.type) ? 'operational' : (item.type === 'message' ? 'message' : 'activity');
      var content = '<strong>' + escapeHtml(item.title || 'Notification') + '</strong><p>' + escapeHtml(item.body || '') + '</p><div class="mg-header-signal-meta"><span>' + escapeHtml(category) + '</span><span>' + escapeHtml(item.created_at || '') + '</span></div>';
      var attrs = ' data-notification-id="' + escapeHtml(item.id || item.public_id || '') + '" class="' + (item.read || item.read_at ? '' : 'is-unread') + '"';
      return item.action_url ? '<a href="' + escapeHtml(item.action_url) + '"' + attrs + '>' + content + '</a>' : '<div' + attrs + '>' + content + '</div>';
    }).join('') + '</div>';
  }
  function messageMarkup(items) {
    if (!items.length) return '<div class="mg-header-signal-empty"><strong>No new messages</strong><p>New conversations and gift replies will appear here.</p></div>';
    return '<div class="mg-header-signal-list">' + items.map(function (item) { return '<a class="' + (item.unread ? 'is-unread' : '') + '" href="/messages.php?thread=' + encodeURIComponent(item.id || item.public_id || '') + '"><strong>' + escapeHtml(item.subject || 'Conversation') + '</strong><p>' + escapeHtml(item.latest_message || 'Open conversation') + '</p><div class="mg-header-signal-meta"><span>' + escapeHtml(item.latest_sender || (item.crm_status || 'Conversation')) + '</span><span>' + escapeHtml(item.latest_at || '') + '</span></div></a>'; }).join('') + '</div>';
  }
  async function loadSignal(shell, quiet) {
    var panel = ensureSignalPanel(shell), type = shell.dataset.headerSignal || 'notifications';
    if (!quiet || shell.classList.contains('is-open')) panel.innerHTML = '<div class="mg-header-signal-empty"><strong>Loading…</strong></div>';
    try {
      if (type === 'messages') {
        var messages = await apiGet('/api/messages/threads.php?limit=8');
        var messageData = messages.data || messages || {};
        var threads = Array.isArray(messageData.threads) ? messageData.threads : [];
        setMessageCount(messageData.unread_count || 0);
        if (shell.classList.contains('is-open') || !quiet) panel.innerHTML = '<div class="mg-header-signal-panel-head"><div><span>Messages</span><strong>Gift conversations</strong></div><a href="/messages.php">View all</a></div>' + messageMarkup(threads);
      } else {
        var notifications = await apiGet('/api/notifications/index.php?limit=8');
        var notificationData = notifications.data || notifications || {};
        var items = Array.isArray(notificationData.notifications) ? notificationData.notifications : [];
        setNotificationCount(notificationData.unread_count || 0);
        if (shell.classList.contains('is-open') || !quiet) panel.innerHTML = '<div class="mg-header-signal-panel-head"><div><span>Notifications</span><strong>Activity & alerts</strong></div><div><a href="/notifications.php">View all</a><a href="/notification-preferences.php">Preferences</a><button type="button" data-clear-notifications>Mark all read</button></div></div>' + notificationMarkup(items);
      }
    } catch (error) {
      if (!quiet) {
        if (type === 'messages') setMessageCount(0); else setNotificationCount(0);
        panel.innerHTML = '<div class="mg-header-signal-panel-head"><div><span>' + (type === 'messages' ? 'Messages' : 'Notifications') + '</span><strong>' + (type === 'messages' ? 'Gift conversations' : 'Activity & alerts') + '</strong></div></div><div class="mg-header-signal-empty"><strong>Unable to load</strong><p>' + escapeHtml(error.message || 'Try again shortly.') + '</p></div>';
      }
    }
  }
  function closeSignals(except) { document.querySelectorAll('[data-header-signal].is-open').forEach(function (node) { if (node !== except) { node.classList.remove('is-open'); setExpanded(node, false); } }); }
  function closeAccountMenu(except) { document.querySelectorAll('[data-mg-auth-menu].is-open').forEach(function (node) { if (node !== except) { node.classList.remove('is-open'); setExpanded(node, false); } }); }
  function closeAll(restoreFocus) { closeSignals(); closeAccountMenu(); if (restoreFocus && lastTrigger && document.contains(lastTrigger)) lastTrigger.focus(); lastTrigger = null; }
  function refreshAllSignals(quiet) { document.querySelectorAll('[data-header-signal]').forEach(function (shell) { loadSignal(shell, quiet !== false); }); }
  function startPolling() {
    if (pollingStarted) return;
    pollingStarted = true;
    setInterval(function () {
      if (document.hidden) return;
      refreshAllSignals(true);
      document.dispatchEvent(new CustomEvent('mg:signals:poll'));
    }, 25000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refreshAllSignals(true); });
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mg-header-badge').forEach(function (badge) { setBadgeCount(badge, badge.textContent); });
    document.querySelectorAll('[data-header-signal]').forEach(function (shell) { ensureSignalPanel(shell); loadSignal(shell); });
    document.querySelectorAll('[data-header-signal-trigger]').forEach(function (trigger) { trigger.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); var shell = trigger.closest('[data-header-signal]'); if (!shell) return; var willOpen = !shell.classList.contains('is-open'); closeSignals(shell); closeAccountMenu(); shell.classList.toggle('is-open', willOpen); trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false'); lastTrigger = willOpen ? trigger : null; if (willOpen) loadSignal(shell); }); });
    document.querySelectorAll('[data-mg-auth-trigger]').forEach(function (trigger) { trigger.addEventListener('click', function (event) { event.stopPropagation(); var shell = trigger.closest('[data-mg-auth-menu]'); if (!shell) return; var willOpen = !shell.classList.contains('is-open'); closeAccountMenu(shell); closeSignals(); shell.classList.toggle('is-open', willOpen); trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false'); lastTrigger = willOpen ? trigger : null; }); });
    document.addEventListener('click', async function (event) {
      var clear = event.target.closest('[data-clear-notifications]');
      if (clear) { clear.disabled = true; try { var response = await apiPost('/api/notifications/read.php', { id: 'all' }); var data = response.data || response || {}; setNotificationCount(data.unread_count || 0); var shell = clear.closest('[data-header-signal]'); if (shell) await loadSignal(shell); } catch (error) { clear.disabled = false; } return; }
      var notification = event.target.closest('[data-notification-id]');
      if (notification && notification.dataset.notificationId) { try { await apiPost('/api/notifications/read.php', { id: notification.dataset.notificationId }); } catch (ignore) {} }
      if (!event.target.closest('[data-header-signal], [data-mg-auth-menu]')) closeAll(false);
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeAll(true); });
    startPolling();
  });
  document.addEventListener('mg:notifications:count', function (event) { setNotificationCount(event.detail && event.detail.count); });
  document.addEventListener('mg:notifications:refresh', function () { document.querySelectorAll('[data-header-signal="notifications"]').forEach(function (shell) { loadSignal(shell); }); });
  document.addEventListener('mg:messages:refresh', function () { document.querySelectorAll('[data-header-signal="messages"]').forEach(function (shell) { loadSignal(shell); }); });
  window.Microgifter.setNotificationCount = setNotificationCount; window.Microgifter.setMessageCount = setMessageCount; window.Microgifter.refreshHeaderSignals = refreshAllSignals;
})(document);