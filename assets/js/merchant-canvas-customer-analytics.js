window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.get !== 'function') return;

  var drawer = document.querySelector('[data-canvas-drawer]');
  var body = drawer ? drawer.querySelector('[data-drawer-body]') : null;
  if (!drawer || !body) return;

  var state = {
    sessionId: '',
    requestId: 0,
    controller: null,
    data: null,
    error: '',
    activeTab: 'overview',
    mounting: false,
    observer: null,
    refreshTimer: null
  };

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString();
  }

  function formatDate(value) {
    if (!value) return '—';
    var raw = String(value);
    var parsed = new Date(raw.indexOf('T') === -1 ? raw.replace(' ', 'T') + 'Z' : raw);
    if (Number.isNaN(parsed.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  function formatDuration(seconds) {
    seconds = Math.max(0, Number(seconds || 0));
    if (seconds < 60) return Math.round(seconds) + ' sec';
    var minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + ' min';
    var hours = Math.floor(minutes / 60);
    return hours + ' hr ' + (minutes % 60) + ' min';
  }

  function formatMoney(cents, currency) {
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(Number(cents || 0) / 100);
    } catch (error) {
      return '$' + (Number(cents || 0) / 100).toFixed(2);
    }
  }

  function stat(label, value, detail) {
    return '<article class="mg-canvas-analytics-stat"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong>' + (detail ? '<small>' + escapeHtml(detail) + '</small>' : '') + '</article>';
  }

  function renderSegments(items) {
    items = Array.isArray(items) ? items : [];
    if (!items.length) return '<p class="mg-canvas-analytics-empty">No customer segments are available yet.</p>';
    return '<div class="mg-canvas-segment-list">' + items.map(function (item) {
      return '<span class="mg-canvas-segment" title="' + escapeHtml(item.reason || '') + '">' + escapeHtml(item.label || item.key || 'Segment') + '</span>';
    }).join('') + '</div>';
  }

  function renderAttribution(attribution) {
    attribution = attribution || {};
    var source = attribution.post || attribution.campaign;
    if (!source) {
      return '<section class="mg-canvas-analytics-card"><span class="mg-canvas-eyebrow">Latest attribution</span><strong>Direct Store Canvas visit</strong><p>No campaign or feed-post source is attached to the latest visit.</p></section>';
    }
    var label = attribution.post ? (source.headline || 'Merchant feed post') : (source.title || 'Campaign');
    var type = attribution.post ? 'Feed post' : 'Campaign';
    return '<section class="mg-canvas-analytics-card"><span class="mg-canvas-eyebrow">Latest attribution</span><strong>' + escapeHtml(label) + '</strong><p>' + escapeHtml(type) + ' · entered ' + escapeHtml(formatDate(attribution.entered_at)) + '</p></section>';
  }

  function renderAnalytics(data) {
    var analytics = data.analytics || {};
    return '<section class="mg-canvas-analytics-section">' +
      '<div class="mg-canvas-analytics-heading"><div><span class="mg-canvas-eyebrow">Customer intelligence</span><h3>Real merchant engagement</h3></div><small>Server-generated records only</small></div>' +
      '<div class="mg-canvas-analytics-stat-grid">' +
        stat('Visits', formatNumber(analytics.visit_count), formatNumber(analytics.return_visit_count) + ' return visits') +
        stat('Total store time', formatDuration(analytics.total_session_seconds), 'Avg. ' + formatDuration(analytics.average_session_seconds)) +
        stat('Messages', formatNumber(analytics.messages_sent), 'Merchant direct messages') +
        stat('Products viewed', formatNumber(analytics.products_viewed), 'Recorded Store Canvas views') +
        stat('Rewards issued', formatNumber(analytics.rewards_issued), formatNumber(analytics.rewards_viewed) + ' viewed') +
        stat('Rewards claimed', formatNumber(analytics.rewards_claimed), Number(analytics.reward_claim_rate || 0).toFixed(1) + '% claim rate') +
        stat('Rewards redeemed', formatNumber(analytics.rewards_redeemed), Number(analytics.reward_redemption_rate || 0).toFixed(1) + '% redemption rate') +
        stat('Last active', formatDate(analytics.last_active_at), 'First visit ' + formatDate(analytics.first_visit_at)) +
      '</div>' +
      '<section class="mg-canvas-analytics-card"><span class="mg-canvas-eyebrow">Segments</span>' + renderSegments(data.segments) + '</section>' +
      renderAttribution(data.attribution) +
      '<p class="mg-canvas-analytics-privacy">Analytics are limited to this merchant’s direct customer interactions. Internal database identifiers and unrelated activity are not exposed.</p>' +
    '</section>';
  }

  function eventDetail(event) {
    var metadata = event.metadata || {};
    return metadata.campaign_title || metadata.reward_template_title || metadata.product_title || metadata.post_headline || metadata.source_label || event.source_kind || '';
  }

  function renderJourney(data) {
    var items = Array.isArray(data.journey) ? data.journey : [];
    if (!items.length) return '<section class="mg-canvas-analytics-section"><p class="mg-canvas-analytics-empty">No journey events are available yet.</p></section>';
    return '<section class="mg-canvas-analytics-section"><div class="mg-canvas-analytics-heading"><div><span class="mg-canvas-eyebrow">Customer journey</span><h3>Campaign → visit → action</h3></div><small>' + formatNumber(items.length) + ' events</small></div><div class="mg-canvas-journey-list">' + items.map(function (event) {
      var detail = eventDetail(event);
      return '<article class="mg-canvas-journey-event" data-event-type="' + escapeHtml(event.type || 'customer_activity') + '"><span class="mg-canvas-journey-dot" aria-hidden="true"></span><div><strong>' + escapeHtml(event.label || event.type || 'Customer activity') + '</strong>' + (detail ? '<p>' + escapeHtml(detail) + '</p>' : '') + '<small>' + escapeHtml(formatDate(event.event_at)) + '</small></div></article>';
    }).join('') + '</div></section>';
  }

  function renderVisits(visits) {
    visits = Array.isArray(visits) ? visits : [];
    if (!visits.length) return '<p class="mg-canvas-analytics-empty">No visit history is available.</p>';
    return '<div class="mg-canvas-history-list">' + visits.map(function (visit) {
      var source = visit.source && visit.source.label ? visit.source.label : 'Direct Store Canvas visit';
      var exit = visit.exited_at ? ('Exited ' + formatDate(visit.exited_at)) : 'Currently active';
      return '<article><div><strong>' + escapeHtml(source) + '</strong><span>' + escapeHtml(formatDate(visit.entered_at)) + '</span></div><div><strong>' + escapeHtml(formatDuration(visit.duration_seconds)) + '</strong><span>' + escapeHtml(exit) + '</span></div></article>';
    }).join('') + '</div>';
  }

  function renderMessages(messages) {
    messages = Array.isArray(messages) ? messages : [];
    if (!messages.length) return '<p class="mg-canvas-analytics-empty">No Store Canvas messages have been sent.</p>';
    return '<div class="mg-canvas-history-list">' + messages.map(function (message) {
      return '<article><div><strong>' + escapeHtml(message.body_preview || 'Direct message') + '</strong><span>' + escapeHtml(formatDate(message.created_at)) + '</span></div><span class="mg-canvas-history-status">' + escapeHtml(message.status || 'sent') + '</span></article>';
    }).join('') + '</div>';
  }

  function renderRewards(rewards) {
    rewards = Array.isArray(rewards) ? rewards : [];
    if (!rewards.length) return '<p class="mg-canvas-analytics-empty">No merchant rewards have been issued.</p>';
    return '<div class="mg-canvas-history-list">' + rewards.map(function (reward) {
      var campaign = reward.campaign && reward.campaign.title ? reward.campaign.title : 'Store Canvas reward';
      return '<article><div><strong>' + escapeHtml(reward.title || 'Reward') + '</strong><span>' + escapeHtml(campaign + ' · ' + formatDate(reward.issued_at)) + '</span></div><div><strong>' + escapeHtml(formatMoney(reward.value_cents, reward.currency)) + '</strong><span class="mg-canvas-history-status">' + escapeHtml(reward.status || 'issued') + '</span></div></article>';
    }).join('') + '</div>';
  }

  function renderHistory(data) {
    return '<section class="mg-canvas-analytics-section"><div class="mg-canvas-analytics-heading"><div><span class="mg-canvas-eyebrow">Customer history</span><h3>Visits, messages, and rewards</h3></div></div>' +
      '<details class="mg-canvas-history-group" open><summary>Visit history <span>' + formatNumber((data.visits || []).length) + '</span></summary>' + renderVisits(data.visits) + '</details>' +
      '<details class="mg-canvas-history-group"><summary>Message history <span>' + formatNumber((data.messages || []).length) + '</span></summary>' + renderMessages(data.messages) + '</details>' +
      '<details class="mg-canvas-history-group"><summary>Reward history <span>' + formatNumber((data.rewards || []).length) + '</span></summary>' + renderRewards(data.rewards) + '</details>' +
    '</section>';
  }

  function tabsMarkup() {
    return '<div class="mg-canvas-analytics-tablist" role="tablist" aria-label="Customer CRM sections">' +
      '<button type="button" role="tab" data-analytics-tab="overview">Overview</button>' +
      '<button type="button" role="tab" data-analytics-tab="analytics">Analytics</button>' +
      '<button type="button" role="tab" data-analytics-tab="journey">Journey</button>' +
      '<button type="button" role="tab" data-analytics-tab="history">History</button>' +
    '</div>';
  }

  function applyActiveTab(shell, focus) {
    var tabs = Array.from(shell.querySelectorAll('[data-analytics-tab]'));
    var panels = Array.from(shell.querySelectorAll('[data-analytics-panel]'));
    tabs.forEach(function (tab) {
      var active = tab.dataset.analyticsTab === state.activeTab;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
      if (active && focus) tab.focus();
    });
    panels.forEach(function (panel) {
      panel.hidden = panel.dataset.analyticsPanel !== state.activeTab;
    });
  }

  function observeBody() {
    if (!state.observer) {
      state.observer = new MutationObserver(function () {
        if (!state.mounting) mount();
      });
    }
    state.observer.observe(body, { childList: true, subtree: false });
  }

  function mount() {
    if (state.mounting || !state.sessionId) return;
    if (body.querySelector('[data-canvas-analytics-shell]')) return;
    if (!body.querySelector('.mg-canvas-customer-summary') && !body.querySelector('[data-crm-form]')) return;

    state.mounting = true;
    if (state.observer) state.observer.disconnect();
    try {
      var fragment = document.createDocumentFragment();
      while (body.firstChild) fragment.appendChild(body.firstChild);

      var shell = document.createElement('section');
      shell.className = 'mg-canvas-analytics-shell';
      shell.setAttribute('data-canvas-analytics-shell', '');
      shell.innerHTML = tabsMarkup() +
        '<section role="tabpanel" data-analytics-panel="overview"></section>' +
        '<section role="tabpanel" data-analytics-panel="analytics"></section>' +
        '<section role="tabpanel" data-analytics-panel="journey"></section>' +
        '<section role="tabpanel" data-analytics-panel="history"></section>';

      shell.querySelector('[data-analytics-panel="overview"]').appendChild(fragment);
      if (state.error) {
        var errorHtml = '<section class="mg-canvas-analytics-section"><p class="mg-canvas-analytics-error">' + escapeHtml(state.error) + '</p></section>';
        shell.querySelector('[data-analytics-panel="analytics"]').innerHTML = errorHtml;
        shell.querySelector('[data-analytics-panel="journey"]').innerHTML = errorHtml;
        shell.querySelector('[data-analytics-panel="history"]').innerHTML = errorHtml;
      } else if (state.data) {
        shell.querySelector('[data-analytics-panel="analytics"]').innerHTML = renderAnalytics(state.data);
        shell.querySelector('[data-analytics-panel="journey"]').innerHTML = renderJourney(state.data);
        shell.querySelector('[data-analytics-panel="history"]').innerHTML = renderHistory(state.data);
      } else {
        var loading = '<section class="mg-canvas-analytics-section"><p class="mg-canvas-analytics-empty">Loading customer analytics...</p></section>';
        shell.querySelector('[data-analytics-panel="analytics"]').innerHTML = loading;
        shell.querySelector('[data-analytics-panel="journey"]').innerHTML = loading;
        shell.querySelector('[data-analytics-panel="history"]').innerHTML = loading;
      }
      body.appendChild(shell);
      applyActiveTab(shell, false);
    } finally {
      state.mounting = false;
      observeBody();
    }
  }

  async function load(sessionId) {
    sessionId = String(sessionId || '');
    if (!sessionId) return;
    state.sessionId = sessionId;
    state.data = null;
    state.error = '';
    if (state.controller) state.controller.abort();
    var controller = new AbortController();
    var requestId = ++state.requestId;
    state.controller = controller;
    mount();

    try {
      var data = payload(await MG.get('/api/merchant-canvas/customer-analytics.php?session_id=' + encodeURIComponent(sessionId), { signal: controller.signal }));
      if (requestId !== state.requestId || sessionId !== state.sessionId) return;
      state.data = data || {};
      state.error = '';
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (requestId !== state.requestId || sessionId !== state.sessionId) return;
      state.data = null;
      state.error = error.message || 'Customer analytics are unavailable. Import the analytics migration and run diagnostics.';
    } finally {
      if (requestId === state.requestId) state.controller = null;
      var existing = body.querySelector('[data-canvas-analytics-shell]');
      if (existing) {
        state.mounting = true;
        if (state.observer) state.observer.disconnect();
        var overview = existing.querySelector('[data-analytics-panel="overview"]');
        var fragment = document.createDocumentFragment();
        while (overview && overview.firstChild) fragment.appendChild(overview.firstChild);
        body.replaceChildren(fragment);
        state.mounting = false;
        observeBody();
      }
      mount();
    }
  }

  function scheduleRefresh() {
    if (!state.sessionId) return;
    if (state.refreshTimer) window.clearTimeout(state.refreshTimer);
    state.refreshTimer = window.setTimeout(function () {
      load(state.sessionId);
    }, 900);
  }

  document.addEventListener('click', function (event) {
    var avatar = event.target.closest('[data-session-id]');
    if (avatar && root.contains(avatar)) {
      state.activeTab = 'overview';
      load(avatar.dataset.sessionId);
      return;
    }
    var tab = event.target.closest('[data-analytics-tab]');
    if (tab && drawer.contains(tab)) {
      state.activeTab = tab.dataset.analyticsTab || 'overview';
      applyActiveTab(tab.closest('[data-canvas-analytics-shell]'), false);
    }
  });

  document.addEventListener('keydown', function (event) {
    var tab = event.target.closest('[data-analytics-tab]');
    if (!tab || !drawer.contains(tab)) return;
    var tabs = Array.from(tab.parentElement.querySelectorAll('[data-analytics-tab]'));
    var index = tabs.indexOf(tab);
    if (event.key === 'ArrowRight') index = (index + 1) % tabs.length;
    else if (event.key === 'ArrowLeft') index = (index - 1 + tabs.length) % tabs.length;
    else if (event.key === 'Home') index = 0;
    else if (event.key === 'End') index = tabs.length - 1;
    else return;
    event.preventDefault();
    state.activeTab = tabs[index].dataset.analyticsTab || 'overview';
    applyActiveTab(tab.closest('[data-canvas-analytics-shell]'), true);
  });

  document.addEventListener('submit', function (event) {
    if (!drawer.contains(event.target)) return;
    if (event.target.matches('[data-crm-form], [data-reward-form], [data-message-form]')) scheduleRefresh();
  });

  observeBody();
  window.addEventListener('beforeunload', function () {
    if (state.controller) state.controller.abort();
    if (state.refreshTimer) window.clearTimeout(state.refreshTimer);
    if (state.observer) state.observer.disconnect();
  });
})(window, document);
