window.Microgifter = window.Microgifter || {};
(function (document) {
  'use strict';
  var lastTrigger = null;
  function escapeHtml(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]; }); }
  function setExpanded(node, open) { var trigger = node && node.querySelector('[data-header-signal-trigger], [data-mg-auth-trigger]'); if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false'); }
  function setBadgeCount(badge, count) { var value = Math.max(0, parseInt(count || 0, 10) || 0); badge.textContent = String(value); badge.hidden = value === 0; badge.style.display = value === 0 ? 'none' : 'grid'; }
  function setNotificationCount(count) { document.querySelectorAll('[data-notification-badge]').forEach(function (badge) { setBadgeCount(badge, count); }); }
  function setMessageCount(count) { document.querySelectorAll('[data-message-badge]').forEach(function (badge) { setBadgeCount(badge, count); }); }
  function ensureSignalPanel(shell) { var existing = shell.querySelector('[data-header-signal-panel]'); if (existing) return existing; var type = shell.dataset.headerSignal || 'notifications'; var panel = document.createElement('div'); panel.className = 'mg-header-signal-panel'; panel.dataset.headerSignalPanel = type; shell.appendChild(panel); return panel; }
  function notificationMarkup(items) {
    if (!items.length) return '<div class="mg-header-signal-empty"><strong>No new notifications</strong><p>Gift, claim, campaign, delivery, and account updates will appear here.</p></div>';
    return '<div class="mg-header-signal-list">' + items.map(function (item) {
      var category = ['claim_locked','claim_expired','delivery_failed','distribution_failed','security','system_alert'].includes(item.type) ? 'operational' : (item.type === 'message' ? 'message' : 'activity');
      var content = '<strong>' + escapeHtml(item.title) + '</strong><p>' + escapeHtml(item.body) + '</p><div class="mg-header-signal-meta"><span>' + escapeHtml(category) + '</span><span>' + escapeHtml(item.created_at || '') + '</span></div>';
      var attrs = ' data-notification-id="' + escapeHtml(item.id || item.public_id) + '" class="' + (item.read || item.read_at ? '' : 'is-unread') + '"';
      return item.action_url ? '<a href="' + escapeHtml(item.action_url) + '"' + attrs + '>' + content + '</a>' : '<div' + attrs + '>' + content + '</div>';
    }).join('') + '</div>';
  }
  function messageMarkup(items) {
    if (!items.length) return '<div class="mg-header-signal-empty"><strong>No new messages</strong><p>New conversations and gift replies will appear here.</p></div>';
    return '<div class="mg-header-signal-list">' + items.map(function (item) { return '<a class="' + (item.unread ? 'is-unread' : '') + '" href="/messages.php?thread=' + encodeURIComponent(item.id || item.public_id) + '"><strong>' + escapeHtml(item.subject) + '</strong><p>' + escapeHtml(item.latest_message || 'Open conversation') + '</p><div class="mg-header-signal-meta"><span>' + escapeHtml(item.latest_sender || 'Conversation') + '</span><span>' + escapeHtml(item.latest_at || '') + '</span></div></a>'; }).join('') + '</div>';
  }
  async function loadSignal(shell) {
    var panel = ensureSignalPanel(shell), type = shell.dataset.headerSignal || 'notifications';
    panel.innerHTML = '<div class="mg-header-signal-empty"><strong>Loading…</strong></div>';
    try {
      if (type === 'messages') {
        var messages = await Microgifter.get('/api/messages/threads.php?limit=8'); var threads = messages.data && Array.isArray(messages.data.threads) ? messages.data.threads : []; setMessageCount(messages.data ? messages.data.unread_count : 0); panel.innerHTML = '<div class="mg-header-signal-panel-head"><div><span>Messages</span><strong>Gift conversations</strong></div><a href="/messages.php">View all</a></div>' + messageMarkup(threads);
      } else {
        var notifications = await Microgifter.get('/api/notifications/index.php?limit=8'); var items = notifications.data && Array.isArray(notifications.data.notifications) ? notifications.data.notifications : []; setNotificationCount(notifications.data ? notifications.data.unread_count : 0); panel.innerHTML = '<div class="mg-header-signal-panel-head"><div><span>Notifications</span><strong>Activity & alerts</strong></div><div><a href="/notifications.php">View all</a><a href="/notification-preferences.php">Preferences</a><button type="button" data-clear-notifications>Mark all read</button></div></div>' + notificationMarkup(items);
      }
    } catch (error) { panel.innerHTML = '<div class="mg-header-signal-empty"><strong>Unable to load</strong><p>' + escapeHtml(error.message || 'Try again shortly.') + '</p></div>'; }
  }
  function closeSignals(except) { document.querySelectorAll('[data-header-signal].is-open').forEach(function (node) { if (node !== except) { node.classList.remove('is-open'); setExpanded(node, false); } }); }
  function closeAccountMenu(except) { document.querySelectorAll('[data-mg-auth-menu].is-open').forEach(function (node) { if (node !== except) { node.classList.remove('is-open'); setExpanded(node, false); } }); }
  function closeAll(restoreFocus) { closeSignals(); closeAccountMenu(); if (restoreFocus && lastTrigger && document.contains(lastTrigger)) lastTrigger.focus(); lastTrigger = null; }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mg-header-badge').forEach(function (badge) { setBadgeCount(badge, badge.textContent); });
    document.querySelectorAll('[data-header-signal]').forEach(function (shell) { ensureSignalPanel(shell); loadSignal(shell); });
    document.querySelectorAll('[data-header-signal-trigger]').forEach(function (trigger) { trigger.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); var shell = trigger.closest('[data-header-signal]'); if (!shell) return; var willOpen = !shell.classList.contains('is-open'); closeSignals(shell); closeAccountMenu(); shell.classList.toggle('is-open', willOpen); trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false'); lastTrigger = willOpen ? trigger : null; if (willOpen) loadSignal(shell); }); });
    document.querySelectorAll('[data-mg-auth-trigger]').forEach(function (trigger) { trigger.addEventListener('click', function (event) { event.stopPropagation(); var shell = trigger.closest('[data-mg-auth-menu]'); if (!shell) return; var willOpen = !shell.classList.contains('is-open'); closeAccountMenu(shell); closeSignals(); shell.classList.toggle('is-open', willOpen); trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false'); lastTrigger = willOpen ? trigger : null; }); });
    document.addEventListener('click', async function (event) {
      var clear = event.target.closest('[data-clear-notifications]');
      if (clear) { clear.disabled = true; try { var response = await Microgifter.post('/api/notifications/read.php', { id: 'all' }); setNotificationCount(response.data ? response.data.unread_count : 0); var shell = clear.closest('[data-header-signal]'); if (shell) await loadSignal(shell); } catch (error) { clear.disabled = false; } return; }
      var notification = event.target.closest('[data-notification-id]');
      if (notification) { try { await Microgifter.post('/api/notifications/read.php', { id: notification.dataset.notificationId }); } catch (ignore) {} }
      if (!event.target.closest('[data-header-signal], [data-mg-auth-menu]')) closeAll(false);
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeAll(true); });
  });
  document.addEventListener('mg:notifications:count', function (event) { setNotificationCount(event.detail && event.detail.count); });
  document.addEventListener('mg:notifications:refresh', function () { document.querySelectorAll('[data-header-signal="notifications"]').forEach(loadSignal); });
  document.addEventListener('mg:messages:refresh', function () { document.querySelectorAll('[data-header-signal="messages"]').forEach(loadSignal); });
  window.Microgifter.setNotificationCount = setNotificationCount; window.Microgifter.setMessageCount = setMessageCount;
})(document);
