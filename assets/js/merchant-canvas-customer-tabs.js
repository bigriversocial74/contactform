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
    var name = drawerName().toLowerCase();
    var cards = Array.from(root.querySelectorAll('.mg-canvas-avatar-card[data-session-id]'));
    var matched = cards.find(function (card) { return clean((card.querySelector('strong') || {}).textContent || '').toLowerCase() === name; });
    return matched ? (matched.dataset.sessionId || '') : (cards[0] ? cards[0].dataset.sessionId || '' : '');
  }

  function extractEvents(body) {
    var list = body.querySelector('.mg-canvas-event-list');
    if (!list) return [];
    return qsa('article', list).map(function (item) {
      return {
        label: clean((item.querySelector('strong') || {}).textContent || 'Store event'),
        time: clean((item.querySelector('span') || {}).textContent || ''),
        type: 'drawer_event'
      };
    });
  }

  function intelligenceEvents(sessionId) {
    var intel = window.Microgifter && window.Microgifter.storeCanvasIntelligence;
    var data = intel && typeof intel.getData === 'function' ? intel.getData() : {};
    var events = [];
    if (data && Array.isArray(data.journeys)) {
      var journey = data.journeys.find(function (item) { return String(item.session_id || '') === String(sessionId || ''); });
      if (journey && Array.isArray(journey.events)) events = events.concat(journey.events);
    }
    if (data && Array.isArray(data.activity)) {
      events = events.concat(data.activity.filter(function (event) { return String(event.session_id || '') === String(sessionId || ''); }));
    }
    return events.map(function (event) {
      return {
        label: event.label || event.event_label || event.type || 'Store Canvas event',
        time: event.created_at || event.time || '',
        type: event.type || 'event',
        metadata: event.metadata || {}
      };
    });
  }

  function mergedEvents(drawerEvents) {
    var sessionEvents = intelligenceEvents(selectedSessionId());
    var all = sessionEvents.concat(drawerEvents || []);
    var seen = {};
    return all.filter(function (event) {
      var key = String(event.type || '') + '|' + String(event.label || '') + '|' + String(event.time || '');
      if (seen[key]) return false;
      seen[key] = true;
      return true;
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
      var lower = label.toLowerCase();
      var stage = index === 0 ? 'Entered' : (lower.indexOf('message') !== -1 ? 'Chat' : (lower.indexOf('reward') !== -1 ? 'Reward' : (lower.indexOf('trigger') !== -1 ? 'Trigger Zone' : 'Action')));
      return { stage: stage, label: label, time: event.time || '' };
    });
  }

  function scoreBreakdown(path, events) {
    var breakdown = [{ label: 'Active Store Canvas session', points: 20, state: 'plus' }];
    var labels = path.map(function (step) { return String((step.stage || '') + ' ' + (step.label || '')).toLowerCase(); }).join(' | ');
    var eventLabels = (events || []).map(function (event) { return String((event.type || '') + ' ' + (event.label || '')).toLowerCase(); }).join(' | ');
    var combined = labels + ' | ' + eventLabels;
    if (combined.indexOf('trigger') !== -1 || combined.indexOf('zone') !== -1) breakdown.push({ label: 'Crossed a trigger zone', points: 18, state: 'plus' });
    if (combined.indexOf('message') !== -1 || combined.indexOf('chat') !== -1 || combined.indexOf('merchant') !== -1) breakdown.push({ label: 'Merchant chat/message interaction', points: 20, state: 'plus' });
    if (combined.indexOf('reward') !== -1) breakdown.push({ label: 'Reward was sent or viewed', points: 18, state: 'plus' });
    if (combined.indexOf('claim') !== -1 || combined.indexOf('redeem') !== -1) breakdown.push({ label: 'Claim/redeem action detected', points: 22, state: 'plus' });
    if (combined.indexOf('return') !== -1 || combined.indexOf('repeat') !== -1) breakdown.push({ label: 'Repeat/returning customer signal', points: 12, state: 'plus' });
    if (combined.indexOf('idle') !== -1) breakdown.push({ label: 'Idle during session', points: -8, state: 'minus' });
    if (combined.indexOf('exit') !== -1 || combined.indexOf('left') !== -1) breakdown.push({ label: 'Exited before completing action', points: -6, state: 'minus' });
    return breakdown;
  }

  function actionResults(events) {
    var rows = (events || []).filter(function (event) { return event && event.label; }).map(function (event) {
      var label = String(event.label || 'Store Canvas action');
      var lower = (String(event.type || '') + ' ' + label).toLowerCase();
      var kind = 'Action';
      var result = 'Recorded';
      var state = 'neutral';
      var next = 'Review activity and decide whether to follow up.';
      if (lower.indexOf('message') !== -1 || lower.indexOf('chat') !== -1) {
        kind = 'Message'; result = lower.indexOf('reply') !== -1 ? 'Replied' : 'Sent'; state = lower.indexOf('reply') !== -1 ? 'success' : 'neutral'; next = lower.indexOf('reply') !== -1 ? 'Continue the conversation.' : 'Wait for reply or send lightweight follow-up.';
      } else if (lower.indexOf('reward') !== -1) {
        kind = 'Reward'; result = lower.indexOf('claim') !== -1 || lower.indexOf('redeem') !== -1 ? 'Claimed' : 'Sent'; state = lower.indexOf('claim') !== -1 || lower.indexOf('redeem') !== -1 ? 'success' : 'pending'; next = result === 'Claimed' ? 'Mark as converted or ask for feedback.' : 'Follow up before reward expires.';
      } else if (lower.indexOf('trigger') !== -1 || lower.indexOf('zone') !== -1) {
        kind = 'Trigger'; result = lower.indexOf('block') !== -1 || lower.indexOf('cooldown') !== -1 ? 'Blocked' : 'Fired'; state = result === 'Blocked' ? 'blocked' : 'success'; next = result === 'Blocked' ? 'No duplicate action needed.' : 'Check whether the message/reward converted.';
      } else if (lower.indexOf('campaign') !== -1 || lower.indexOf('newsletter') !== -1) {
        kind = 'Campaign'; result = lower.indexOf('join') !== -1 || lower.indexOf('subscribe') !== -1 ? 'Joined' : 'Attached'; state = result === 'Joined' ? 'success' : 'pending'; next = 'Monitor campaign response.';
      } else if (lower.indexOf('exit') !== -1 || lower.indexOf('left') !== -1) {
        kind = 'Exit'; result = 'No conversion yet'; state = 'warning'; next = 'Consider a follow-up if allowed.';
      }
      return { kind: kind, label: label, result: result, state: state, next: next, time: event.time || event.created_at || '' };
    });
    if (!rows.length) {
      rows.push({ kind: 'Session', label: 'Customer entered Store Canvas', result: 'Watching', state: 'neutral', next: 'Wait for trigger, chat, or reward activity.', time: 'Current session' });
    }
    return rows.slice(0, 12);
  }

  function renderScoreBreakdown(path, events) {
    var rows = scoreBreakdown(path, events);
    return '<section class="mg-customer-score-breakdown"><header><strong>Why this score?</strong><span>Action-weighted customer signals</span></header>' + rows.map(function (row) {
      return '<article class="is-' + esc(row.state) + '"><span>' + esc(row.label) + '</span><strong>' + (row.points > 0 ? '+' : '') + esc(row.points) + '</strong></article>';
    }).join('') + '</section>';
  }

  function renderMovement(events, customerName) {
    var intel = window.Microgifter && window.Microgifter.storeCanvasIntelligence;
    var sessionId = selectedSessionId();
    var path = intel && typeof intel.getPath === 'function' ? intel.getPath(sessionId) : movementFallback(events, customerName);
    if (!path.length) path = movementFallback(events, customerName);
    var allEvents = mergedEvents(events);
    var score = intel && typeof intel.getScore === 'function' ? intel.getScore(sessionId) : { score: Math.min(100, 20 + path.length * 15), label: 'Watching', why: 'Score will become more specific as customer actions are recorded.' };
    return '<section class="mg-customer-path-score"><span>Customer Score</span><strong>' + esc(score.score) + '</strong><em>' + esc(score.label) + '</em><p>' + esc(score.why) + '</p></section>' +
      renderScoreBreakdown(path, allEvents) +
      '<section class="mg-customer-path-list">' + path.map(function (step) {
        return '<article><b>' + esc(step.stage || 'Action') + '</b><div><strong>' + esc(step.label || 'Store Canvas event') + '</strong><span>' + esc(step.time || '') + '</span></div></article>';
      }).join('') + '</section>' +
      '<section class="mg-customer-path-next"><strong>Next Best Action</strong><p>' + (score.score >= 75 ? 'Send a direct offer or reward while the customer is engaged.' : (score.score >= 45 ? 'Ask a question or send a lightweight follow-up message.' : 'Let the customer continue browsing before sending a stronger promotion.')) + '</p></section>';
  }

  function renderResults(events, customerName) {
    var rows = actionResults(mergedEvents(events));
    return '<section class="mg-customer-results-summary"><span>Action Results</span><strong>' + esc(rows.length) + '</strong><p>Shows what happened after messages, rewards, campaigns, and trigger events for ' + esc(customerName) + '.</p></section>' +
      '<section class="mg-customer-results-list">' + rows.map(function (row) {
        return '<article class="is-' + esc(row.state) + '"><header><span>' + esc(row.kind) + '</span><strong>' + esc(row.result) + '</strong></header><p>' + esc(row.label) + '</p><footer><small>' + esc(row.time || 'Recent') + '</small><b>' + esc(row.next) + '</b></footer></article>';
      }).join('') + '</section>';
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
    drawer.classList.toggle('is-customer-tab-results', activeTab === 'results');
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
        '<button type="button" data-customer-tab="results"><span>✓</span>Results</button>' +
        '<button type="button" data-customer-tab="campaigns"><span>✦</span>Campaigns</button>' +
      '</nav>' +
      '<div class="mg-customer-tab-panels">' +
        '<section class="mg-customer-tab-panel" data-customer-panel="overview">' + overviewHtml + '</section>' +
        '<section class="mg-customer-tab-panel mg-customer-chat-panel" data-customer-panel="chat">' + renderChat(events, customerName) + '</section>' +
        '<section class="mg-customer-tab-panel" data-customer-panel="history"><div class="mg-customer-history-list">' + makeHistory(events) + '</div></section>' +
        '<section class="mg-customer-tab-panel mg-customer-movement-panel" data-customer-panel="movement">' + renderMovement(events, customerName) + '</section>' +
        '<section class="mg-customer-tab-panel mg-customer-results-panel" data-customer-panel="results">' + renderResults(events, customerName) + '</section>' +
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
    var movement = qs('[data-customer-panel="movement"]', body);
    var results = qs('[data-customer-panel="results"]', body);
    if (movement) movement.innerHTML = renderMovement([], drawerName());
    if (results) results.innerHTML = renderResults([], drawerName());
  });

  observer = new MutationObserver(function () {
    if (transforming) return;
    window.requestAnimationFrame(transform);
  });
  observer.observe(drawer, { childList: true, subtree: true });

  window.addEventListener('beforeunload', function () { if (observer) observer.disconnect(); });
  transform();
})(window, document);
