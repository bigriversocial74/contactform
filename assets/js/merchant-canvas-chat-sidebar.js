window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;

  var rail = null;
  var observer = null;
  var lastStatsHost = null;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function isOpen(drawer) { return !!(drawer && drawer.classList.contains('is-open')); }
  function activeCount() {
    var stat = qs('[data-canvas-active-count]');
    var value = stat ? parseInt(String(stat.textContent || '0').replace(/[^0-9]/g, ''), 10) : 0;
    if (!Number.isFinite(value)) value = 0;
    return value;
  }

  function installHeaderStats() {
    var stats = root.querySelector('[data-canvas-header-stats]') || root.querySelector('.mg-canvas-header-stats');
    var header = document.querySelector('.mg-unified-header .mg-header-inner') || document.querySelector('.mg-site-header .mg-header-inner');
    if (!stats || !header) return;
    if (stats.parentElement !== header) {
      var actions = header.querySelector('.mg-header-actions');
      header.insertBefore(stats, actions || null);
    }
    stats.classList.add('is-in-app-header');
    stats.setAttribute('data-canvas-header-mounted', 'app-header');
    root.classList.add('has-header-stats');
    lastStatsHost = header;
  }

  function ensureRail() {
    if (rail && rail.isConnected) return rail;
    rail = document.createElement('button');
    rail.type = 'button';
    rail.className = 'mg-canvas-chat-rail';
    rail.setAttribute('data-chat-rail', '');
    rail.setAttribute('aria-label', 'Open in-store chat');
    rail.setAttribute('aria-expanded', 'false');
    rail.innerHTML = '<span class="mg-canvas-chat-rail-icon">✦</span><span class="mg-canvas-chat-rail-copy"><strong>In-Store Chat</strong><span data-chat-rail-label>No active chats</span></span><span class="mg-canvas-chat-rail-count" data-chat-rail-count>0</span>';
    document.body.appendChild(rail);
    rail.addEventListener('click', function () { openChat(true); });
    return rail;
  }

  function openChat(selectFirst) {
    var drawer = document.querySelector('[data-canvas-drawer]');
    if (!drawer) return;
    if (selectFirst) {
      var activeTab = drawer.querySelector('[data-chat-session-id].is-active');
      if (activeTab) { activeTab.click(); return; }
      var firstTab = drawer.querySelector('[data-chat-session-id]');
      if (firstTab && firstTab.dataset.chatSessionId) { firstTab.click(); return; }
      var firstAvatar = root.querySelector('.mg-canvas-avatar-card[data-session-id]');
      if (firstAvatar && firstAvatar.dataset.sessionId) { firstAvatar.click(); return; }
    }
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    syncRail();
  }

  function syncRail() {
    var drawer = document.querySelector('[data-canvas-drawer]');
    var node = ensureRail();
    var count = activeCount();
    var countNode = node.querySelector('[data-chat-rail-count]');
    var label = node.querySelector('[data-chat-rail-label]');
    if (countNode) countNode.textContent = String(count);
    if (label) label.textContent = count === 1 ? '1 active chat' : String(count) + ' active chats';
    node.classList.toggle('is-drawer-open', isOpen(drawer));
    node.setAttribute('aria-expanded', isOpen(drawer) ? 'true' : 'false');
  }

  function decorateChatTabs() {
    qsa('[data-chat-tabs] button[data-chat-session-id]').forEach(function (button, index) {
      if (!button.querySelector('small')) {
        var close = document.createElement('small');
        close.textContent = '×';
        close.setAttribute('aria-hidden', 'true');
        button.appendChild(close);
      }
      if (!button.getAttribute('aria-label')) {
        button.setAttribute('aria-label', 'Open chat ' + (button.textContent || ('Visitor ' + (index + 1))).replace('×', '').trim());
      }
    });
  }

  function decorateTriggerZones() {
    qsa('[data-canvas-persistent-zone]').forEach(function (node) {
      var main = node.querySelector('.mg-canvas-trigger-main');
      if (!main) return;
      var meta = main.querySelector('[data-zone-rules]');
      if (!meta) {
        meta = document.createElement('em');
        meta.setAttribute('data-zone-rules', '');
        main.appendChild(meta);
      }
      var priority = node.dataset.triggerPriority || '3';
      if (node.classList.contains('is-cooldown')) meta.textContent = 'Cooling down · Priority ' + priority;
      else if (node.classList.contains('is-hot')) meta.textContent = 'Triggered · Priority ' + priority;
      else meta.textContent = 'Rules active · Priority ' + priority;
    });
  }

  function keepDrawersOnTop() {
    qsa('.mg-canvas-trigger-settings-drawer,.mg-canvas-merchant-settings-drawer,.mg-canvas-trigger-analytics-drawer,.mg-canvas-crm-drawer').forEach(function (drawer) {
      drawer.style.zIndex = '2147483002';
      drawer.style.top = '0';
      drawer.style.height = '100dvh';
      drawer.style.maxHeight = '100dvh';
    });
  }

  function tick() {
    installHeaderStats();
    ensureRail();
    syncRail();
    decorateChatTabs();
    decorateTriggerZones();
    keepDrawersOnTop();
  }

  tick();
  window.setTimeout(tick, 200);
  window.setTimeout(tick, 900);
  window.setInterval(function () {
    if (!lastStatsHost || !lastStatsHost.isConnected) installHeaderStats();
    syncRail();
    decorateTriggerZones();
  }, 2500);

  observer = new MutationObserver(function () { tick(); });
  observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'aria-hidden'] });

  window.addEventListener('beforeunload', function () { if (observer) observer.disconnect(); });
})(window, document);
