window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.get !== 'function') return;

  var map = root.querySelector('[data-canvas-map]');
  var customerLayer = root.querySelector('[data-canvas-customers]');
  var merchantNode = root.querySelector('[data-canvas-control-center]');
  var zoneLayer = root.querySelector('[data-canvas-server-zones]');
  var drawer = document.querySelector('[data-canvas-drawer]');
  if (!map || !customerLayer || !merchantNode || !zoneLayer || !drawer) return;

  var state = {
    profiles: new Map(),
    selectedSessionId: '',
    selectedPayload: null,
    selectedError: '',
    activeController: null,
    selectedController: null,
    pollTimer: null,
    customerObserver: null,
    drawerObserver: null,
    adjustmentLocks: new WeakSet()
  };

  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function formatPercent(value) { return Math.max(0, Math.min(100, Number(value || 0))).toFixed(1) + '%'; }
  function label(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
  function clamp(value, minimum, maximum) { return Math.max(minimum, Math.min(maximum, value)); }
  function profileFor(sessionId) { return state.profiles.get(String(sessionId || '')) || null; }
  function cardBySession(sessionId) {
    if (!sessionId) return null;
    return Array.from(customerLayer.querySelectorAll('[data-session-id]')).find(function (card) {
      return String(card.dataset.sessionId || '') === String(sessionId);
    }) || null;
  }

  function ensureCue(card) {
    var meta = card.querySelector('.mg-canvas-avatar-meta');
    if (!meta) return null;
    var cue = meta.querySelector('[data-canvas-behavior-cue]');
    if (!cue) {
      cue = document.createElement('span');
      cue.className = 'mg-canvas-behavior-cue';
      cue.setAttribute('data-canvas-behavior-cue', '');
      meta.appendChild(cue);
    }
    return cue;
  }

  function applyProfileToCard(card, profile) {
    if (!card || !profile) return;
    var movement = profile.movement || {};
    var greeting = profile.greeting || {};
    card.dataset.relationshipStage = profile.relationship_stage || 'unknown';
    card.dataset.behaviorPattern = profile.dominant_pattern || 'insufficient_data';
    card.dataset.behaviorMode = movement.mode || 'explore';
    card.dataset.followState = movement.follow_state || 'observe';
    card.dataset.releaseState = movement.release_state || 'hold';

    var confidenceValue = String(clamp(Number(profile.confidence || 0), 0, 100)) + '%';
    if (card.style.getPropertyValue('--mg-behavior-confidence') !== confidenceValue) {
      card.style.setProperty('--mg-behavior-confidence', confidenceValue);
    }
    var cue = ensureCue(card);
    var cueText = greeting.label || 'Welcome';
    if (cue && cue.textContent !== cueText) cue.textContent = cueText;
    var title = (profile.memory_summary || cueText) + ' Confidence ' + formatPercent(profile.confidence || 0) + '.';
    if (card.title !== title) card.title = title;
  }

  function applyAllProfiles() {
    customerLayer.querySelectorAll('[data-session-id]').forEach(function (card) {
      var profile = profileFor(card.dataset.sessionId);
      if (profile) applyProfileToCard(card, profile);
    });
  }

  function positionValue(value, total) {
    var text = String(value || '').trim();
    if (!text) return 0;
    if (text.endsWith('%')) return (parseFloat(text) / 100) * total;
    return parseFloat(text) || 0;
  }

  function targetForProfile(card, profile) {
    var movement = profile && profile.movement ? profile.movement : {};
    var mode = movement.mode || 'explore';
    if (mode === 'explore') return null;

    var mapRect = map.getBoundingClientRect();
    var cardWidth = card.offsetWidth || 180;
    var cardHeight = card.offsetHeight || 74;
    var currentLeft = positionValue(card.style.left, map.clientWidth);
    var currentTop = positionValue(card.style.top, map.clientHeight);
    var targetLeft = currentLeft;
    var targetTop = currentTop;
    var blend = 0.2;

    if (mode === 'merchant_follow') {
      var merchantRect = merchantNode.getBoundingClientRect();
      targetLeft = merchantRect.left - mapRect.left + (merchantRect.width / 2) - (cardWidth / 2);
      targetTop = merchantRect.top - mapRect.top + merchantRect.height + 26;
      blend = 0.28;
    } else if (mode === 'campaign_interest') {
      var zone = zoneLayer.querySelector('.mg-canvas-server-zone:not(.is-paused)') || zoneLayer.querySelector('.mg-canvas-server-zone');
      if (zone) {
        var zoneRect = zone.getBoundingClientRect();
        targetLeft = zoneRect.left - mapRect.left + (zoneRect.width / 2) - (cardWidth / 2);
        targetTop = zoneRect.top - mapRect.top + (zoneRect.height / 2) - (cardHeight / 2);
        blend = 0.24;
      }
    } else if (mode === 'release') {
      var cardCenter = currentLeft + (cardWidth / 2);
      targetLeft = cardCenter < (map.clientWidth / 2) ? 18 : map.clientWidth - cardWidth - 18;
      targetTop = map.clientHeight - cardHeight - 86;
      blend = 0.23;
    }

    return {
      left: clamp(currentLeft + ((targetLeft - currentLeft) * blend), 18, Math.max(18, map.clientWidth - cardWidth - 18)),
      top: clamp(currentTop + ((targetTop - currentTop) * blend), 92, Math.max(92, map.clientHeight - cardHeight - 78))
    };
  }

  function adviseMovement(card) {
    if (!card || state.adjustmentLocks.has(card)) return;
    var target = targetForProfile(card, profileFor(card.dataset.sessionId));
    if (!target) return;
    state.adjustmentLocks.add(card);
    card.dataset.visualMovement = 'presentation-only';
    card.dataset.behaviorGuidance = 'server-profile';
    card.style.left = Math.round(target.left) + 'px';
    card.style.top = Math.round(target.top) + 'px';
    window.requestAnimationFrame(function () { state.adjustmentLocks.delete(card); });
  }

  function probabilityCard(key, title, value, detail) {
    var percent = clamp(Number(value || 0), 0, 100);
    return '<article class="mg-canvas-behavior-probability" data-probability="' + escapeHtml(key) + '">' +
      '<div><span>' + escapeHtml(title) + '</span><strong>' + escapeHtml(formatPercent(percent)) + '</strong></div>' +
      '<div class="mg-canvas-behavior-meter" aria-hidden="true"><i style="width:' + percent.toFixed(1) + '%"></i></div>' +
      '<small>' + escapeHtml(detail) + '</small></article>';
  }

  function renderEvidence(items) {
    items = Array.isArray(items) ? items : [];
    if (!items.length) return '<p class="mg-canvas-behavior-empty">More interaction history is needed before evidence factors can be shown.</p>';
    return '<div class="mg-canvas-behavior-evidence-list">' + items.map(function (item) {
      var impact = Number(item.impact || 0);
      return '<article data-direction="' + escapeHtml(item.direction || 'neutral') + '"><div><strong>' + escapeHtml(item.label || item.key || 'Evidence') + '</strong><p>' + escapeHtml(item.reason || '') + '</p></div><span>' + (impact > 0 ? '+' : '') + escapeHtml(impact.toFixed(1)) + '</span></article>';
    }).join('') + '</div>';
  }

  function renderBehaviorPanel() {
    var panel = drawer.querySelector('[data-analytics-panel="behavior"]');
    if (!panel) return;
    if (state.selectedError) {
      panel.innerHTML = '<section class="mg-canvas-behavior-section"><p class="mg-canvas-behavior-error">' + escapeHtml(state.selectedError) + '</p></section>';
      return;
    }
    if (!state.selectedPayload || !state.selectedPayload.profile) {
      panel.innerHTML = '<section class="mg-canvas-behavior-section"><p class="mg-canvas-behavior-empty">Loading behavior memory and projections…</p></section>';
      return;
    }

    var profile = state.selectedPayload.profile || {};
    var movement = profile.movement || {};
    var greeting = profile.greeting || {};
    var probabilities = profile.probabilities || {};
    var future = state.selectedPayload.future_capabilities || {};

    panel.innerHTML = '<section class="mg-canvas-behavior-section">' +
      '<div class="mg-canvas-behavior-heading"><div><span class="mg-canvas-eyebrow">Merchant-customer memory</span><h3>' + escapeHtml(label(profile.relationship_stage || 'new')) + ' relationship</h3></div><span class="mg-canvas-behavior-confidence">' + escapeHtml(formatPercent(profile.confidence || 0)) + ' confidence</span></div>' +
      '<article class="mg-canvas-behavior-memory"><strong>' + escapeHtml(label(profile.dominant_pattern || 'early_signal')) + '</strong><p>' + escapeHtml(profile.memory_summary || 'Behavior memory is still developing.') + '</p><small>' + escapeHtml(String(profile.sample_size || 0)) + ' canonical evidence records · updated ' + escapeHtml(profile.last_calculated_at || 'now') + '</small></article>' +
      '<div class="mg-canvas-behavior-probability-grid">' +
        probabilityCard('return', 'Return in 7 days', probabilities.return_7d, 'Estimated near-term return likelihood') +
        probabilityCard('campaign', 'Campaign engagement', probabilities.campaign_engagement, 'Estimated participation interest') +
        probabilityCard('claim', 'Reward claim', probabilities.reward_claim, 'Estimated claim likelihood') +
        probabilityCard('redeem', 'Reward redeem', probabilities.reward_redeem, 'Estimated redemption likelihood') +
        probabilityCard('inactivity', 'Inactivity risk', probabilities.inactivity_risk, 'Estimated relationship cooling risk') +
      '</div>' +
      '<section class="mg-canvas-behavior-card"><h4>Greeting, following, and release</h4><div class="mg-canvas-behavior-action-grid">' +
        '<article><span>Greeting</span><strong>' + escapeHtml(greeting.label || 'Welcome') + '</strong><small>Mode: ' + escapeHtml(label(greeting.mode || 'first_visit')) + '</small></article>' +
        '<article><span>Movement</span><strong>' + escapeHtml(label(movement.mode || 'explore')) + '</strong><small>' + escapeHtml(movement.explanation || 'Exploratory presentation movement.') + '</small></article>' +
        '<article><span>Follow state</span><strong>' + escapeHtml(label(movement.follow_state || 'observe')) + '</strong><small>Visual guidance only; no automatic message or campaign action.</small></article>' +
        '<article><span>Release state</span><strong>' + escapeHtml(label(movement.release_state || 'hold')) + '</strong><small>Supports gentle timing and comeback recommendations.</small></article>' +
      '</div></section>' +
      '<section class="mg-canvas-behavior-card"><h4>Why these projections</h4>' + renderEvidence(profile.evidence) + '</section>' +
      '<section class="mg-canvas-behavior-card"><h4>Connected systems</h4><div class="mg-canvas-behavior-links"><a href="/merchant-crm.php">Merchant CRM & Contacts</a><a href="/merchant-campaigns.php">Campaigns</a><a href="/merchant-memory.php">Merchant Memory</a><a href="/merchant-reward-templates.php">Reward Inventory</a></div><p>Campaign recommendations remain merchant-approved. Campaign completion remains the only authority that can issue a reward to Wallet, Inbox, and PPPM.</p></section>' +
      '<section class="mg-canvas-behavior-card is-future"><h4>Future peer commerce</h4><p>Customer-to-customer chat, peer matching, and item sending are intentionally gated. Current behavior memory prepares the authority and safety context but does not activate these features.</p><div class="mg-canvas-behavior-future"><span>Peer chat: ' + (future.customer_to_customer_chat ? 'enabled' : 'planned') + '</span><span>Item sending: ' + (future.customer_item_sending ? 'enabled' : 'planned') + '</span><span>Policy: server gated</span></div></section>' +
      '<p class="mg-canvas-behavior-policy">Predictions use this merchant’s direct behavioral records only. Protected traits are excluded. Scores are estimates, not facts, and cannot trigger browser-side customer actions.</p></section>';
  }

  function behaviorPanelMissing() {
    var shell = drawer.querySelector('[data-canvas-analytics-shell]');
    if (!shell) return false;
    return !shell.querySelector('[data-analytics-tab="behavior"]') || !shell.querySelector('[data-analytics-panel="behavior"]');
  }

  function ensureBehaviorPanel() {
    var shell = drawer.querySelector('[data-canvas-analytics-shell]');
    if (!shell) return false;
    var tablist = shell.querySelector('.mg-canvas-analytics-tablist');
    if (!tablist) return false;
    if (!tablist.querySelector('[data-analytics-tab="behavior"]')) {
      var tab = document.createElement('button');
      tab.type = 'button';
      tab.setAttribute('role', 'tab');
      tab.setAttribute('data-analytics-tab', 'behavior');
      tab.textContent = 'Behavior';
      tablist.appendChild(tab);
    }
    if (!shell.querySelector('[data-analytics-panel="behavior"]')) {
      var panel = document.createElement('section');
      panel.setAttribute('role', 'tabpanel');
      panel.setAttribute('data-analytics-panel', 'behavior');
      panel.hidden = true;
      shell.appendChild(panel);
    }
    renderBehaviorPanel();
    return true;
  }

  async function loadSelected(sessionId) {
    sessionId = String(sessionId || '');
    if (!sessionId) return;
    state.selectedSessionId = sessionId;
    state.selectedPayload = null;
    state.selectedError = '';
    if (state.selectedController) state.selectedController.abort();
    var controller = new AbortController();
    state.selectedController = controller;
    ensureBehaviorPanel();
    renderBehaviorPanel();
    try {
      var response = payload(await MG.get('/api/merchant-canvas/customer-behavior.php?session_id=' + encodeURIComponent(sessionId), { signal: controller.signal })) || {};
      if (sessionId !== state.selectedSessionId) return;
      state.selectedPayload = response;
      if (response.profile) {
        state.profiles.set(sessionId, response.profile);
        var card = cardBySession(sessionId);
        if (card) applyProfileToCard(card, response.profile);
      }
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (sessionId !== state.selectedSessionId) return;
      state.selectedError = error.message || 'Behavior memory is unavailable. Import database/merchant_canvas_behavior_memory_predictive_v1.sql.';
    } finally {
      if (state.selectedController === controller) state.selectedController = null;
      ensureBehaviorPanel();
      renderBehaviorPanel();
    }
  }

  function scheduleActiveRefresh(delay) {
    if (state.pollTimer) window.clearTimeout(state.pollTimer);
    if (document.hidden) return;
    state.pollTimer = window.setTimeout(loadActiveProfiles, Math.max(15000, Number(delay || 30000)));
  }

  async function loadActiveProfiles() {
    if (state.activeController) state.activeController.abort();
    var controller = new AbortController();
    state.activeController = controller;
    try {
      var response = payload(await MG.get('/api/merchant-canvas/active-behavior.php', { signal: controller.signal })) || {};
      var profiles = response.profiles && typeof response.profiles === 'object' ? response.profiles : {};
      Object.keys(profiles).forEach(function (sessionId) { state.profiles.set(String(sessionId), profiles[sessionId]); });
      applyAllProfiles();
      document.dispatchEvent(new CustomEvent('mg:canvasBehaviorProfiles', { detail: { profiles: profiles, generated_at: response.generated_at || null } }));
    } catch (error) {
      if (!(error && error.name === 'AbortError')) {
        document.dispatchEvent(new CustomEvent('mg:canvasBehaviorProfilesError', { detail: { message: error.message || 'Behavior profiles unavailable.' } }));
      }
    } finally {
      if (state.activeController === controller) state.activeController = null;
      scheduleActiveRefresh(30000);
    }
  }

  document.addEventListener('click', function (event) {
    var card = event.target.closest('[data-session-id]');
    if (card && root.contains(card)) loadSelected(card.dataset.sessionId);
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      if (state.pollTimer) window.clearTimeout(state.pollTimer);
      return;
    }
    loadActiveProfiles();
  });

  state.customerObserver = new MutationObserver(function (records) {
    var cardsChanged = false;
    records.forEach(function (record) {
      if (record.type === 'childList') cardsChanged = true;
      if (record.type === 'attributes' && record.target && record.target.matches && record.target.matches('[data-session-id]')) {
        adviseMovement(record.target);
      }
    });
    if (cardsChanged) applyAllProfiles();
  });
  state.customerObserver.observe(customerLayer, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });

  state.drawerObserver = new MutationObserver(function () {
    if (behaviorPanelMissing()) ensureBehaviorPanel();
  });
  state.drawerObserver.observe(drawer, { childList: true, subtree: true });

  loadActiveProfiles();
  ensureBehaviorPanel();

  window.addEventListener('pagehide', function () {
    if (state.pollTimer) window.clearTimeout(state.pollTimer);
    if (state.activeController) state.activeController.abort();
    if (state.selectedController) state.selectedController.abort();
    if (state.customerObserver) state.customerObserver.disconnect();
    if (state.drawerObserver) state.drawerObserver.disconnect();
  }, { once: true });
})(window, document);
