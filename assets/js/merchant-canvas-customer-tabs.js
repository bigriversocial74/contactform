window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;
  var drawer = document.querySelector('[data-canvas-drawer]') || root.querySelector('[data-canvas-drawer]');
  if (!drawer) return;

  var activeTab = 'chat';
  var transforming = false;
  var observer = null;

  function qs(selector, scope) { return (scope || drawer).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || drawer).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function clean(text) { return String(text || '').replace(/\s+/g, ' ').trim(); }

  function drawerName() {
    var node = qs('[data-drawer-name]');
    return clean(node ? node.textContent : '') || 'Customer';
  }

  function selectedSessionId() {
    var active = root.querySelector('.mg-canvas-avatar-card.is-active[data-session-id]');
    if (active) return active.dataset.sessionId || '';
    var card = root.querySelector('.mg-canvas-avatar-card[data-session-id]');
    return card ? (card.dataset.sessionId || '') : '';
  }

  function extractEvents(body) {
    var list = body.querySelector('.mg-canvas-event-list');
    if (!list) return [];
    return qsa('article', list).map(function (item) {
      return {
        label: clean((item.querySelector('strong') || {}).textContent || 'Store event'),
        time: clean((item.querySelector('span') || {}).textContent || '')
      };
    });
  }

  function eventLooksMerchant(label) {
    label = String(label || '').toLowerCase();
    return label.indexOf('merchant') !== -1 || label.indexOf('message') !== -1 || label.indexOf('reward') !== -1 || label.indexOf('auto chat') !== -1;
  }

  function renderChat(events, customerName) {
    var usable = events.filter(function (event) { return event.label && event.label !== 'No events yet'; }).slice(-8);
    if (!usable.length) {
      usable = [
        { label: customerName + ' entered Store Canvas.', time: 'Now' },
        { label: 'Start a new customer conversation from this chat tab.', time: '' }
      ];
    }
    var html = '<div class="mg-customer-chat-day">Today</div><div class="mg-customer-chat-thread">';
    usable.forEach(function (event, index) {
      var merchant = eventLooksMerchant(event.label) || index % 2 === 1;
      html += '<article class="mg-customer-chat-message ' + (merchant ? 'is-merchant' : 'is-customer') + '">' +
        '<span class="mg-customer-chat-avatar">' + (merchant ? 'M' : esc(customerName.slice(0, 1).toUpperCase() || 'C')) + '</span>' +
        '<div><strong>' + (merchant ? 'Merchant' : esc(customerName)) + '</strong><p>' + esc(event.label) + '</p>' + (event.time ? '<small>' + esc(event.time) + '</small>' : '') + '</div>' +
      '</article>';
    });
    html += '</div>';
    return html;
  }

  function makeHistory(events) {
    if (!events.length) return '<article><strong>No history yet</strong><span>Customer activity will appear after visits, messages, trigger events, and rewards.</span></article>';
    return events.map(function (event) {
      return '<article><strong>' + esc(event.label || 'Store event') + '</strong><span>' + esc(event.time || 'Recent') + '</span></article>';
    }).join('');
  }

  function movementFallback(events, customerName) {
    var basic = events.length ? events : [{ label: customerName + ' entered Store Canvas.', time: 'Current session' }];
    return basic.map(function (event, index) {
      var label = event.label || 'Store event';
      var stage = index === 0 ? 'Entered' : (label.toLowerCase().indexOf('message') !== -1 ? 'Chat' : (label.toLowerCase().indexOf('reward') !== -1 ? 'Reward' : 'Action'));
      return { stage: stage, label: label, time: event.time || '' };
    });
  }

  function renderMovement(events, customerName) {
    var intel = window.Microgifter && window.Microgifter.storeCanvasIntelligence;
    var sessionId = selectedSessionId();
    var path = intel && typeof intel.getPath === 'function' ? intel.getPath(sessionId) : movementFallback(events, customerName);
    var score = intel && typeof intel.getScore === 'function' ? intel.getScore(sessionId) : { score: Math.min(100, 20 + path.length * 15), label: 'Watching', why: 'Score will become more specific as customer actions are recorded.' };
    if (!path.length) path = movementFallback(events, customerName);
    return '<section class="mg-customer-path-score"><span>Customer Score</span><strong>' + esc(score.score) + '</strong><em>' + esc(score.label) + '</em><p>' + esc(score.why) + '</p></section>' +
      '<section class="mg-customer-path-list">' + path.map(function (step) {
        return '<article><b>' + esc(step.stage || 'Action') + '</b><div><strong>' + esc(step.label || 'Store Canvas event') + '</strong><span>' + esc(step.time || '') + '</span></div></article>';
      }).join('') + '</section>' +
      '<section class="mg-customer-path-next"><strong>Next Best Action</strong><p>' + (score.score >= 75 ? 'Send a direct offer or reward while the customer is engaged.' : (score.score >= 45 ? 'Ask a question or send a lightweight follow-up message.' : 'Let the customer continue browsing before sending a stronger promotion.')) + '</p></section>';
  }

  function makeCampaignPanel(rewardPanel, sourceSection) {
    var reward = rewardPanel ? rewardPanel.outerHTML : '<section class="mg-canvas-reward-panel"><p class="mg-canvas-reward-note">Reward options load when a real customer account is selected.</p></section>';
    var source = sourceSection ? sourceSection.outerHTML : '<section><span class="mg-canvas-eyebrow">Store source</span><p>Feed post / Store Canvas</p></section>';
    return source + reward + '<section class="mg-customer-campaign-placeholder"><strong>Campaign Attachments</strong><p>Add this customer to a campaign or use Send Reward to deliver a campaign-based reward.</p></section>';
  }

  function activate(tab) {
    activeTab = tab || 'chat';
    drawer.dataset.customerActiveTab = activeTab;
    drawer.classList.toggle('is-customer-tab-chat', activeTab === 'chat');
    drawer.classList.toggle('is-customer-tab-history', activeTab === 'history');
    drawer.classList.toggle('is-customer-tab-overview', activeTab === 'overview');
    drawer.classList.toggle('is-customer-tab-movement', activeTab === 'movement');
    drawer.classList.toggle('is-customer-tab-campaigns', activeTab === 'campaigns');
    qsa('[data-customer-tab]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.customerTab === activeTab);
    });
    qsa('[data-customer-panel]').forEach(function (panel) {
      panel.hidden = panel.dataset.customerPanel !== activeTab;
    });
  }

  function transform() {
    if (transforming) return;
    var body = qs('[data-drawer-body]');
    if (!body || body.dataset.customerTabsReady === '1') return;
    if (!body.querySelector('.mg-canvas-customer-summary') && !body.querySelector('.mg-canvas-crm-grid')) return;

    transforming = true;
    var customerName = drawerName();
    var summary = body.querySelector('.mg-canvas-customer-summary');
    var stats = body.querySelector('.mg-canvas-crm-grid');
    var actions = body.querySelector('.mg-canvas-action-grid');
    var rewardPanel = body.querySelector('[data-reward-panel]');
    var sourceSection = qsa('section', body).find(function (section) { return clean(section.textContent).toLowerCase().indexOf('store source') === 0; });
    var events = extractEvents(body);

    var overviewHtml = '<section class="mg-customer-overview-card">' +
      (summary ? summary.outerHTML : '<section class="mg-canvas-customer-summary"><span class="mg-canvas-avatar">' + esc(customerName.slice(0, 1).toUpperCase() || 'C') + '</span><div><strong>' + esc(customerName) + '</strong><span>customer · in system</span><small>Current status: active</small></div></section>') +
      (stats ? stats.outerHTML : '') +
      (actions ? actions.outerHTML : '') +
    '</section>';

    body.innerHTML =
      '<nav class="mg-customer-crm-tabs" aria-label="Customer CRM tabs">' +
        '<button type="button" data-customer-tab="overview"><span>▦</span>Overview</button>' +
        '<button type="button" data-customer-tab="chat"><span>●</span>Chat</button>' +
        '<button type="button" data-customer-tab="history"><span>↺</span>History</button>' +
        '<button type="button" data-customer-tab="movement"><span>↝</span>Movement</button>' +
        '<button type="button" data-customer-tab="campaigns"><span>✦</span>Campaigns</button>' +
      '</nav>' +
      '<div class="mg-customer-tab-panels">' +
        '<section class="mg-customer-tab-panel" data-customer-panel="overview">' + overviewHtml + '</section>' +
        '<section class="mg-customer-tab-panel mg-customer-chat-panel" data-customer-panel="chat">' + renderChat(events, customerName) + '</section>' +
        '<section class="mg-customer-tab-panel" data-customer-panel="history"><div class="mg-customer-history-list">' + makeHistory(events) + '</div></section>' +
        '<section class="mg-customer-tab-panel mg-customer-movement-panel" data-customer-panel="movement">' + renderMovement(events, customerName) + '</section>' +
        '<section class="mg-customer-tab-panel" data-customer-panel="campaigns">' + makeCampaignPanel(rewardPanel, sourceSection) + '</section>' +
      '</div>';

    body.dataset.customerTabsReady = '1';
    activate(activeTab);
    transforming = false;
  }

  drawer.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-customer-tab]');
    if (!tab) return;
    activate(tab.dataset.customerTab || 'chat');
  });

  document.addEventListener('mg:storeCanvasIntelligenceLoaded', function () {
    var body = qs('[data-drawer-body]');
    if (!body || body.dataset.customerTabsReady !== '1') return;
    var panel = qs('[data-customer-panel="movement"]', body);
    if (!panel) return;
    panel.innerHTML = renderMovement([], drawerName());
  });

  observer = new MutationObserver(function () {
    if (transforming) return;
    window.requestAnimationFrame(transform);
  });
  observer.observe(drawer, { childList: true, subtree: true });

  window.addEventListener('beforeunload', function () { if (observer) observer.disconnect(); });
  transform();
})(window, document);
