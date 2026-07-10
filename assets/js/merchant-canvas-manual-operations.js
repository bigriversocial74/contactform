window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.get !== 'function' || typeof MG.post !== 'function') return;

  var drawer = root.querySelector('[data-canvas-drawer]');
  var state = {
    customers: [],
    selectedSessionId: '',
    selectedRequest: 0,
    selectedController: null,
    canvasRequest: 0,
    canvasController: null,
    canvasInFlight: false,
    pollTimer: null,
    pollDelay: 7000,
    failures: 0,
    rewardOptions: { schema_ready: false, campaigns: [], templates: [], can_send_reward: false },
    rewardOptionsLoaded: false,
    returnFocus: null,
    backdrop: null,
    lastCrm: null
  };

  function portalDrawer() {
    if (!drawer || !document.body) return null;
    if (drawer.parentElement !== document.body) document.body.appendChild(drawer);
    drawer.setAttribute('data-canvas-drawer-portal', 'body');
    return drawer;
  }

  function qs(selector, scope) {
    if (scope) return scope.querySelector(selector);
    var node = root.querySelector(selector);
    if (node) return node;
    if (drawer && drawer.isConnected) return drawer.querySelector(selector);
    return document.querySelector(selector);
  }

  function qsa(selector, scope) {
    return Array.from((scope || root).querySelectorAll(selector));
  }

  function payload(response) {
    return response && response.data ? response.data : response;
  }

  function clear(node) {
    if (node) node.replaceChildren();
  }

  function setText(selector, value, scope) {
    var node = qs(selector, scope);
    if (node) node.textContent = value == null ? '' : String(value);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function busy(button, value, text) {
    if (!button) return;
    if (typeof MG.setBusy === 'function') return MG.setBusy(button, value, text);
    if (value) button.dataset.originalLabel = button.textContent;
    button.disabled = value;
    button.textContent = value ? (text || 'Working...') : (button.dataset.originalLabel || button.textContent);
  }

  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
      var random = Math.random() * 16 | 0;
      var value = character === 'x' ? random : (random & 0x3 | 0x8);
      return value.toString(16);
    });
  }

  function stableActionKey(form, type, fingerprint) {
    if (!form) return type + '-' + uuid();
    if (form.dataset.actionFingerprint !== fingerprint || !form.dataset.actionKey) {
      form.dataset.actionFingerprint = fingerprint;
      form.dataset.actionKey = type + '-' + uuid();
    }
    return form.dataset.actionKey;
  }

  function clearActionKey(form) {
    if (!form) return;
    delete form.dataset.actionFingerprint;
    delete form.dataset.actionKey;
  }

  function initials(name) {
    return String(name || 'C').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) {
      return part[0];
    }).join('').toUpperCase() || 'C';
  }

  function formatDuration(seconds) {
    seconds = Math.max(0, Number(seconds || 0));
    if (seconds < 60) return String(seconds) + ' sec';
    var minutes = Math.floor(seconds / 60);
    if (minutes < 60) return String(minutes) + ' min';
    var hours = Math.floor(minutes / 60);
    return String(hours) + ' hr ' + String(minutes % 60) + ' min';
  }

  function formatDate(value) {
    if (!value) return '';
    var raw = String(value);
    var parsed = new Date(raw.indexOf('T') === -1 ? raw.replace(' ', 'T') + 'Z' : raw);
    if (Number.isNaN(parsed.getTime())) return raw;
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString();
  }

  function moneyLabel(cents, currency) {
    cents = Number(cents || 0);
    currency = currency || 'USD';
    if (!cents) return '';
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(cents / 100);
    } catch (error) {
      return '$' + (cents / 100).toFixed(2);
    }
  }

  function rewardValueLabel(template) {
    template = template || {};
    if (template.value_type === 'percent' && template.value_percent != null) return String(template.value_percent).replace(/\.0+$/, '') + '% off';
    if (template.value_amount_cents) return moneyLabel(template.value_amount_cents, template.currency || 'USD');
    return template.reward_type ? String(template.reward_type).replace(/_/g, ' ') : 'Reward';
  }

  function avatarHtml(customer) {
    var src = customer && customer.avatar_url ? String(customer.avatar_url) : '';
    if (src) return '<span class="mg-canvas-avatar"><img src="' + escapeHtml(src) + '" alt=""></span>';
    return '<span class="mg-canvas-avatar">' + escapeHtml(initials(customer && customer.name)) + '</span>';
  }

  function setLiveStatus(message, type) {
    var pill = qs('[data-canvas-live-pill]');
    if (!pill) return;
    pill.classList.remove('mg-canvas-live-pill-hidden');
    pill.textContent = message;
    pill.classList.toggle('is-error', type === 'error');
    pill.classList.toggle('is-live', type === 'live');
    pill.classList.toggle('is-warn', type === 'warn');
  }

  function setCanvasState(message, type) {
    var banner = qs('[data-canvas-state]');
    if (!banner) return;
    banner.classList.remove('mg-canvas-state-hidden');
    banner.textContent = message;
    banner.classList.toggle('is-error', type === 'error');
    banner.classList.toggle('is-live', type === 'live');
    banner.classList.toggle('is-warn', type === 'warn');
  }

  function customerCard(item) {
    var customer = item.customer || {};
    var last = item.last_event && item.last_event.label ? item.last_event.label : 'Entered store';
    var source = item.source_post && item.source_post.headline ? item.source_post.headline : 'Merchant feed post';
    var classes = 'mg-canvas-avatar-card' + (item.status === 'idle' ? ' is-idle' : '') + (item.is_test ? ' is-test' : '') + (item.session_id === state.selectedSessionId ? ' is-active' : '');
    var connection = item.status === 'idle' ? 'Idle · last active ' + formatDuration(item.seconds_since_active) + ' ago' : 'Online · inside ' + formatDuration(item.seconds_inside);
    return '<button class="' + classes + '" type="button" data-session-id="' + escapeHtml(item.session_id) + '" aria-pressed="' + (item.session_id === state.selectedSessionId ? 'true' : 'false') + '">' +
      '<span class="mg-canvas-avatar-status" aria-hidden="true"></span>' +
      avatarHtml(customer) +
      '<span class="mg-canvas-avatar-meta"><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>' + escapeHtml(connection) + '</span><small title="' + escapeHtml(source) + '">' + escapeHtml(last) + '</small></span>' +
      (item.is_test ? '<em>Test</em>' : '') +
      '</button>';
  }

  function renderSummary(summary) {
    summary = summary || {};
    setText('[data-canvas-active-count]', formatNumber(summary.active_customers || state.customers.length));
    setText('[data-canvas-today-entries]', formatNumber(summary.today_entries));
    setText('[data-canvas-today-events]', formatNumber(summary.today_events));
    setText('[data-canvas-history-rows]', formatNumber(summary.history_rows));
  }

  function selectedCustomerExists() {
    return state.customers.some(function (item) {
      return item.session_id === state.selectedSessionId;
    });
  }

  function disableManualActions(message) {
    var form = qs('[data-message-form]');
    if (form) {
      var textarea = form.elements.message;
      var button = qs('[data-message-submit]', form);
      if (textarea) textarea.disabled = true;
      if (button) button.disabled = true;
      var status = qs('[data-message-status]', form);
      if (status && message) {
        status.textContent = message;
        status.className = 'mg-canvas-form-status is-error';
      }
    }
    qsa('[data-reward-submit]', drawer || document).forEach(function (button) {
      button.disabled = true;
    });
  }

  function renderDisconnectedCustomer() {
    if (!drawer || !drawer.classList.contains('is-open')) return;
    setText('[data-drawer-name]', 'Customer left the store');
    var body = qs('[data-drawer-body]');
    if (body) body.innerHTML = '<section class="mg-canvas-disconnected"><strong>This Store Canvas session is no longer active.</strong><p>Refresh or select another live customer before sending a message or reward.</p></section>';
    disableManualActions('Customer session is no longer active.');
  }

  function renderCanvas(data) {
    state.customers = Array.isArray(data.customers) ? data.customers : [];
    var summary = data.summary || {};
    state.pollDelay = Math.max(5000, Math.min(30000, Number(data.poll_after_ms || 7000)));
    renderSummary(summary);
    setText('[data-canvas-agent-status]', summary.agent_status || 'Watching');

    var manualReady = summary.manual_operations_ready !== false;
    if (!manualReady) {
      setLiveStatus('Manual operations setup required', 'warn');
      setCanvasState('Import database/merchant_canvas_manual_operations_stabilization_v1.sql before sending messages or rewards.', 'warn');
    } else {
      setLiveStatus(state.customers.length ? 'Live customers inside' : 'Database connected', state.customers.length ? 'live' : '');
      setCanvasState(state.customers.length ? 'Canvas live. Manual actions are protected by durable CRM safeguards and idempotency receipts.' : 'Database connected, waiting for customers.', state.customers.length ? 'live' : '');
    }

    var layer = qs('[data-canvas-customers]');
    if (layer) {
      clear(layer);
      layer.insertAdjacentHTML('beforeend', state.customers.map(customerCard).join(''));
    }
    var empty = qs('[data-canvas-empty]');
    if (empty) empty.classList.toggle('is-hidden', state.customers.length > 0);

    if (state.selectedSessionId && !selectedCustomerExists()) renderDisconnectedCustomer();
  }

  function clearPollTimer() {
    if (state.pollTimer) window.clearTimeout(state.pollTimer);
    state.pollTimer = null;
  }

  function schedulePoll(delay) {
    clearPollTimer();
    if (document.hidden) return;
    state.pollTimer = window.setTimeout(function () {
      loadCanvas({ reason: 'poll' });
    }, Math.max(1000, Number(delay || state.pollDelay)));
  }

  async function loadCanvas(options) {
    options = options || {};
    if (document.hidden && !options.force) return;
    if (state.canvasInFlight && !options.force) return;

    if (options.force && state.canvasController) state.canvasController.abort();
    var controller = new AbortController();
    var requestId = ++state.canvasRequest;
    state.canvasController = controller;
    state.canvasInFlight = true;
    clearPollTimer();

    try {
      var data = payload(await MG.get('/api/merchant-canvas/active-users.php', { signal: controller.signal }));
      if (requestId !== state.canvasRequest) return;
      state.failures = 0;
      renderCanvas(data || {});
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (requestId !== state.canvasRequest) return;
      state.failures += 1;
      setLiveStatus(error.message || 'Unable to load canvas', 'error');
      setCanvasState(error.message || 'Unable to load canvas. Run diagnostics to confirm the active database and table status.', 'error');
    } finally {
      if (requestId === state.canvasRequest) {
        state.canvasInFlight = false;
        state.canvasController = null;
        var backoff = state.failures ? Math.min(60000, state.pollDelay * Math.pow(2, Math.min(state.failures, 3))) : state.pollDelay;
        schedulePoll(backoff);
      }
    }
  }

  function healthRow(label, value, status) {
    return '<article class="mg-canvas-health-row' + (status ? ' is-' + escapeHtml(status) : '') + '"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value == null ? '—' : value) + '</strong></article>';
  }

  function renderHealth(data) {
    var health = qs('[data-canvas-health]');
    var status = qs('[data-canvas-health-status]');
    if (!health) return;
    var schema = data.schema || {};
    var stats = data.stats || {};
    var tables = schema.tables || {};
    var html = '<section class="mg-canvas-health-section"><h3>Merchant activity</h3>';
    html += healthRow('Active customers', stats.active_customers, 'ready');
    html += healthRow('Today entries', stats.today_entries, 'ready');
    html += healthRow('Today events', stats.today_events, 'ready');
    html += healthRow('Store history rows', stats.history_rows, 'ready');
    html += '</section><section class="mg-canvas-health-section mg-canvas-health-section-wide"><h3>Tables</h3>';
    Object.keys(tables).forEach(function (table) {
      var item = tables[table] || {};
      html += healthRow(table, item.exists ? 'found' : 'missing', item.exists ? 'ready' : 'error');
    });
    html += '</section>';
    health.innerHTML = html;
    if (status) {
      status.textContent = schema.ready ? 'Ready' : 'Missing tables';
      status.classList.toggle('is-error', !schema.ready);
    }
  }

  async function loadHealth(openPanel) {
    var diagnostics = qs('[data-canvas-diagnostics]');
    var status = qs('[data-canvas-health-status]');
    if (openPanel && diagnostics) diagnostics.open = true;
    if (status) status.textContent = 'Checking...';
    try {
      renderHealth(payload(await MG.get('/api/merchant-canvas/health.php')) || {});
    } catch (error) {
      if (status) {
        status.textContent = 'Error';
        status.classList.add('is-error');
      }
      var health = qs('[data-canvas-health]');
      if (health) health.innerHTML = '<p class="mg-canvas-health-error">' + escapeHtml(error.message || 'Unable to run diagnostics.') + '</p>';
    }
  }

  async function loadRewardOptions(force) {
    if (state.rewardOptionsLoaded && !force) return state.rewardOptions;
    try {
      state.rewardOptions = payload(await MG.get('/api/merchant-canvas/reward-options.php')) || state.rewardOptions;
      state.rewardOptions.campaigns = Array.isArray(state.rewardOptions.campaigns) ? state.rewardOptions.campaigns : [];
      state.rewardOptions.templates = Array.isArray(state.rewardOptions.templates) ? state.rewardOptions.templates : [];
    } catch (error) {
      state.rewardOptions = { schema_ready: false, campaigns: [], templates: [], can_send_reward: false, error: error.message || 'Reward options unavailable.' };
    }
    state.rewardOptionsLoaded = true;
    return state.rewardOptions;
  }

  function selectOptions(items, selectedId, labelFn) {
    return items.map(function (item) {
      return '<option value="' + escapeHtml(item.id) + '"' + (item.id === selectedId ? ' selected' : '') + '>' + escapeHtml(labelFn(item)) + '</option>';
    }).join('');
  }

  function campaignAvailability(campaign) {
    if (!campaign) return '';
    var parts = [];
    if (campaign.quantity_limit == null) parts.push('Unlimited campaign quantity');
    else parts.push(formatNumber(Math.max(0, Number(campaign.quantity_limit) - Number(campaign.issued_count || 0))) + ' remaining');
    if (campaign.ends_at) parts.push('Ends ' + formatDate(campaign.ends_at));
    return parts.join(' · ');
  }

  function renderRewardPanel(customer, actions) {
    customer = customer || {};
    actions = actions || {};
    var isTest = customer.profile_type === 'test_customer' || customer.account_status === 'Test avatar';
    if (isTest || !actions.send_reward) {
      return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note">Reward sending requires a live customer account.</p></section>';
    }
    if (!state.rewardOptionsLoaded) {
      return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note">Loading reward options...</p></section>';
    }
    var campaigns = state.rewardOptions.campaigns.filter(function (campaign) {
      return Boolean(campaign.reward_template_id && campaign.available !== false);
    });
    if (!state.rewardOptions.schema_ready || !state.rewardOptions.can_send_reward || !campaigns.length) {
      return '<section class="mg-canvas-reward-panel" data-reward-panel hidden><p class="mg-canvas-reward-note is-error">Create an active campaign with an attached available reward template before sending Store Canvas rewards.</p></section>';
    }
    var campaign = campaigns[0];
    return '<section class="mg-canvas-reward-panel" data-reward-panel hidden>' +
      '<form data-reward-form>' +
        '<label>Campaign<select name="campaign_id" required>' + selectOptions(campaigns, campaign.id, function (item) { return item.title + ' · ' + item.campaign_type; }) + '</select></label>' +
        '<input type="hidden" name="reward_template_id" value="' + escapeHtml(campaign.reward_template_id || '') + '">' +
        '<p class="mg-canvas-reward-note" data-reward-eligibility><strong>' + escapeHtml(campaign.reward_template_title || 'Attached reward') + '</strong><span>' + escapeHtml(campaignAvailability(campaign)) + '</span></p>' +
        '<label>Expires<select name="expiration_days"><option value="">Use template rule</option><option value="7">7 days</option><option value="14">14 days</option><option value="30">30 days</option><option value="60">60 days</option></select></label>' +
        '<label>Note<textarea name="note" rows="3" maxlength="1000" placeholder="Optional customer note..."></textarea></label>' +
        '<button class="mg-btn mg-btn-primary" type="submit" data-reward-submit>Send Reward</button>' +
        '<p class="mg-canvas-form-status" data-reward-status role="status"></p>' +
      '</form>' +
    '</section>';
  }

  function crmTagInput(tag, label, selected) {
    return '<label class="mg-canvas-crm-tag"><input type="checkbox" name="tags[]" value="' + escapeHtml(tag) + '"' + (selected ? ' checked' : '') + '><span>' + escapeHtml(label) + '</span></label>';
  }

  function renderCrm(data) {
    state.lastCrm = data;
    var customer = data.customer || {};
    var stats = data.stats || {};
    var session = data.session || {};
    var crm = data.crm || {};
    var actions = data.actions || {};
    var events = Array.isArray(data.events) ? data.events : [];
    var tags = Array.isArray(crm.tags) ? crm.tags : [];
    var canOpenReward = Boolean(actions.send_reward && state.rewardOptionsLoaded && state.rewardOptions.can_send_reward);
    var canMessage = Boolean(actions.send_direct_message);

    setText('[data-drawer-name]', customer.name || 'Customer CRM');
    var body = qs('[data-drawer-body]');
    if (body) {
      body.innerHTML =
        '<section class="mg-canvas-customer-summary">' + avatarHtml(customer) + '<div><strong>' + escapeHtml(customer.name || 'Customer') + '</strong><span>' + escapeHtml(customer.profile_type || 'customer') + ' · ' + escapeHtml(customer.account_status || 'In system') + '</span><small>Current status: ' + escapeHtml(session.status || 'offline') + '</small></div></section>' +
        '<section class="mg-canvas-crm-grid">' +
          '<article class="mg-canvas-crm-stat"><span>Visits</span><strong>' + formatNumber(stats.visit_count) + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Messages</span><strong>' + formatNumber(stats.messages_sent) + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Rewards</span><strong>' + formatNumber(stats.rewards_received) + '</strong></article>' +
          '<article class="mg-canvas-crm-stat"><span>Claims</span><strong>' + formatNumber(stats.rewards_claimed) + '</strong></article>' +
        '</section>' +
        '<section class="mg-canvas-action-grid"><button type="button" data-drawer-focus-message' + (canMessage ? '' : ' disabled') + '>Send Message</button><button type="button" data-drawer-toggle-reward' + (canOpenReward ? '' : ' disabled') + '>Send Reward</button></section>' +
        '<form class="mg-canvas-crm-editor" data-crm-form>' +
          '<span class="mg-canvas-eyebrow">Merchant CRM safeguards</span>' +
          '<label>Notes<textarea name="notes" rows="4" maxlength="5000" placeholder="Private merchant notes...">' + escapeHtml(crm.notes || '') + '</textarea></label>' +
          '<div class="mg-canvas-crm-tags" aria-label="Customer tags">' +
            crmTagInput('vip', 'VIP', tags.indexOf('vip') !== -1) +
            crmTagInput('high_intent', 'High Intent', tags.indexOf('high_intent') !== -1) +
            crmTagInput('needs_follow_up', 'Needs Follow-Up', tags.indexOf('needs_follow_up') !== -1) +
          '</div>' +
          '<label class="mg-canvas-dnm"><input type="checkbox" name="do_not_message" value="1"' + (crm.do_not_message ? ' checked' : '') + '><span><strong>Do Not Message</strong><small>Blocks manual Store Canvas direct messages on the server.</small></span></label>' +
          '<button class="mg-btn mg-btn-secondary" type="submit" data-crm-save' + (actions.save_crm ? '' : ' disabled') + '>Save CRM</button>' +
          '<p class="mg-canvas-form-status" data-crm-status role="status"></p>' +
        '</form>' +
        renderRewardPanel(customer, actions) +
        '<section><span class="mg-canvas-eyebrow">Store source</span><p>' + escapeHtml(session.source_post && session.source_post.headline ? session.source_post.headline : 'Feed post / Store Canvas') + '</p></section>' +
        '<section><span class="mg-canvas-eyebrow">Session events</span><div class="mg-canvas-event-list">' + (events.length ? events.map(function (event) {
          return '<article><strong>' + escapeHtml(event.label || event.type || 'Store event') + '</strong><span>' + escapeHtml(formatDate(event.created_at)) + '</span></article>';
        }).join('') : '<article><strong>No events yet</strong><span>Customer has just entered the store.</span></article>') + '</div></section>';
    }

    var messageForm = qs('[data-message-form]');
    var message = messageForm ? messageForm.elements.message : null;
    var submit = messageForm ? qs('[data-message-submit]', messageForm) : null;
    if (message) message.disabled = !canMessage;
    if (submit) submit.disabled = !canMessage;
    var messageStatus = messageForm ? qs('[data-message-status]', messageForm) : null;
    if (messageStatus) {
      messageStatus.textContent = canMessage ? '' : (crm.do_not_message ? 'Direct messaging is blocked by Do Not Message.' : 'This customer session is not currently messageable.');
      messageStatus.className = 'mg-canvas-form-status' + (canMessage ? '' : ' is-error');
    }
  }

  function ensureBackdrop() {
    if (state.backdrop && state.backdrop.isConnected) return state.backdrop;
    var backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mg-canvas-drawer-backdrop';
    backdrop.hidden = true;
    backdrop.setAttribute('data-canvas-drawer-backdrop', '');
    backdrop.setAttribute('aria-label', 'Close customer CRM drawer');
    document.body.appendChild(backdrop);
    state.backdrop = backdrop;
    return backdrop;
  }

  function openDrawer() {
    var activeDrawer = portalDrawer();
    if (!activeDrawer) return;
    state.returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    activeDrawer.classList.add('is-open');
    activeDrawer.setAttribute('aria-hidden', 'false');
    var backdrop = ensureBackdrop();
    backdrop.hidden = false;
    document.body.classList.add('mg-canvas-drawer-open');
    window.requestAnimationFrame(function () {
      var close = qs('[data-drawer-close]', activeDrawer);
      if (close) close.focus();
      else activeDrawer.focus();
    });
  }

  function closeDrawer() {
    var activeDrawer = portalDrawer();
    if (!activeDrawer) return;
    activeDrawer.classList.remove('is-open');
    activeDrawer.setAttribute('aria-hidden', 'true');
    if (state.backdrop) state.backdrop.hidden = true;
    document.body.classList.remove('mg-canvas-drawer-open');
    if (state.selectedController) state.selectedController.abort();
    if (state.returnFocus && state.returnFocus.isConnected) state.returnFocus.focus();
  }

  function trapDrawerFocus(event) {
    if (!drawer || !drawer.classList.contains('is-open') || event.key !== 'Tab') return;
    var focusable = qsa('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', drawer).filter(function (node) {
      return !node.hidden && node.offsetParent !== null;
    });
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  async function loadCrm(sessionId) {
    var requestedSessionId = String(sessionId || '');
    if (!requestedSessionId) return;
    state.selectedSessionId = requestedSessionId;
    qsa('[data-session-id]').forEach(function (button) {
      var selected = button.dataset.sessionId === requestedSessionId;
      button.classList.toggle('is-active', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
    openDrawer();
    setText('[data-drawer-name]', 'Loading customer...');
    var drawerBody = qs('[data-drawer-body]');
    if (drawerBody) drawerBody.innerHTML = '<p class="mg-canvas-loading">Loading CRM context...</p>';

    if (state.selectedController) state.selectedController.abort();
    var controller = new AbortController();
    var requestId = ++state.selectedRequest;
    state.selectedController = controller;

    try {
      await loadRewardOptions(false);
      var data = payload(await MG.get('/api/merchant-canvas/customer-crm.php?session_id=' + encodeURIComponent(requestedSessionId), { signal: controller.signal }));
      if (requestId !== state.selectedRequest || requestedSessionId !== state.selectedSessionId) return;
      renderCrm(data || {});
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (requestId !== state.selectedRequest || requestedSessionId !== state.selectedSessionId) return;
      if (drawerBody) drawerBody.innerHTML = '<p class="mg-canvas-health-error">' + escapeHtml(error.message || 'Unable to load customer CRM.') + '</p>';
      disableManualActions(error.message || 'Unable to load customer CRM.');
    } finally {
      if (requestId === state.selectedRequest) state.selectedController = null;
    }
  }

  async function saveCrm(form) {
    if (!state.selectedSessionId || !form) return;
    var button = qs('[data-crm-save]', form);
    var status = qs('[data-crm-status]', form);
    var tags = qsa('input[name="tags[]"]:checked', form).map(function (input) { return input.value; });
    busy(button, true, 'Saving...');
    if (status) {
      status.textContent = '';
      status.className = 'mg-canvas-form-status';
    }
    try {
      await MG.post('/api/merchant-canvas/customer-crm-update.php', {
        session_id: state.selectedSessionId,
        notes: form.elements.notes ? form.elements.notes.value : '',
        tags: tags,
        do_not_message: Boolean(form.elements.do_not_message && form.elements.do_not_message.checked)
      });
      if (status) {
        status.textContent = 'CRM safeguards saved.';
        status.className = 'mg-canvas-form-status is-success';
      }
      await loadCrm(state.selectedSessionId);
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Unable to save CRM safeguards.';
        status.className = 'mg-canvas-form-status is-error';
      }
    } finally {
      busy(button, false);
    }
  }

  async function sendMessage(form) {
    if (!state.selectedSessionId || !form) return;
    var input = form.elements.message;
    var body = String(input.value || '').trim();
    if (!body) return;
    var button = qs('[data-message-submit]', form);
    var status = qs('[data-message-status]', form);
    var fingerprint = state.selectedSessionId + '|' + body;
    var idempotencyKey = stableActionKey(form, 'canvas-message', fingerprint);
    busy(button, true, 'Sending...');
    if (status) {
      status.className = 'mg-canvas-form-status';
      status.textContent = '';
    }
    try {
      var result = payload(await MG.post('/api/merchant-canvas/send-message.php', {
        session_id: state.selectedSessionId,
        message: body,
        idempotency_key: idempotencyKey
      })) || {};
      input.value = '';
      clearActionKey(form);
      if (status) {
        status.textContent = result.message && result.message.duplicate ? 'Message was already sent. No duplicate was created.' : 'Message sent to customer IN/OUT Box.';
        status.className = 'mg-canvas-form-status is-success';
      }
      await loadCanvas({ force: true, reason: 'message-sent' });
      if (state.selectedSessionId) await loadCrm(state.selectedSessionId);
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Unable to send message. Retry uses the same protected request key.';
        status.className = 'mg-canvas-form-status is-error';
      }
    } finally {
      busy(button, false);
    }
  }

  function updateRewardCampaign(form) {
    if (!form) return;
    var campaignId = form.elements.campaign_id ? form.elements.campaign_id.value : '';
    var campaign = state.rewardOptions.campaigns.find(function (item) { return item.id === campaignId; }) || null;
    if (form.elements.reward_template_id) form.elements.reward_template_id.value = campaign && campaign.reward_template_id ? campaign.reward_template_id : '';
    var eligibility = qs('[data-reward-eligibility]', form);
    if (eligibility) {
      eligibility.innerHTML = campaign ? '<strong>' + escapeHtml(campaign.reward_template_title || 'Attached reward') + '</strong><span>' + escapeHtml(campaignAvailability(campaign)) + '</span>' : 'Select an active campaign.';
    }
    var submit = qs('[data-reward-submit]', form);
    if (submit) submit.disabled = !campaign || !campaign.reward_template_id || campaign.available === false;
    clearActionKey(form);
  }

  async function sendReward(form) {
    if (!state.selectedSessionId || !form) return;
    var button = qs('[data-reward-submit]', form);
    var status = qs('[data-reward-status]', form);
    var request = {
      session_id: state.selectedSessionId,
      campaign_id: form.elements.campaign_id ? form.elements.campaign_id.value : '',
      reward_template_id: form.elements.reward_template_id ? form.elements.reward_template_id.value : '',
      expiration_days: form.elements.expiration_days ? form.elements.expiration_days.value : '',
      note: form.elements.note ? form.elements.note.value : ''
    };
    var fingerprint = Object.keys(request).map(function (key) { return key + '=' + String(request[key] || ''); }).join('|');
    request.idempotency_key = stableActionKey(form, 'canvas-reward', fingerprint);
    busy(button, true, 'Sending...');
    if (status) {
      status.className = 'mg-canvas-form-status';
      status.textContent = '';
    }
    try {
      var result = payload(await MG.post('/api/merchant-canvas/send-reward.php', request)) || {};
      var reward = result.reward || {};
      clearActionKey(form);
      if (status) {
        status.textContent = reward.duplicate ? 'Reward was already issued. No duplicate was created.' : (reward.title ? reward.title + ' sent.' : 'Reward sent to customer IN/OUT Box.');
        status.className = 'mg-canvas-form-status is-success';
      }
      if (form.elements.note) form.elements.note.value = '';
      state.rewardOptionsLoaded = false;
      await loadRewardOptions(true);
      await loadCanvas({ force: true, reason: 'reward-sent' });
      if (state.selectedSessionId) await loadCrm(state.selectedSessionId);
    } catch (error) {
      if (status) {
        status.textContent = error.message || 'Unable to send reward. Retry uses the same protected request key.';
        status.className = 'mg-canvas-form-status is-error';
      }
    } finally {
      busy(button, false);
    }
  }

  document.addEventListener('click', function (event) {
    var inRoot = root.contains(event.target);
    var inDrawer = drawer && drawer.contains(event.target);
    var backdropClick = event.target.closest('[data-canvas-drawer-backdrop]');
    if (backdropClick) return closeDrawer();
    if (!inRoot && !inDrawer) return;

    var refresh = event.target.closest('[data-canvas-refresh]');
    if (refresh) return void loadCanvas({ force: true, reason: 'manual-refresh' });
    var health = event.target.closest('[data-canvas-health-refresh]');
    if (health) return void loadHealth(true);
    var focusMessage = event.target.closest('[data-drawer-focus-message]');
    if (focusMessage) {
      var messageInput = qs('[data-message-form] textarea');
      if (messageInput) messageInput.focus();
      return;
    }
    var toggleReward = event.target.closest('[data-drawer-toggle-reward]');
    if (toggleReward) {
      var panel = qs('[data-reward-panel]');
      if (panel) panel.hidden = !panel.hidden;
      return;
    }
    var close = event.target.closest('[data-drawer-close]');
    if (close) return closeDrawer();
    var avatar = inRoot ? event.target.closest('[data-session-id]') : null;
    if (avatar) return void loadCrm(avatar.dataset.sessionId);
  });

  document.addEventListener('change', function (event) {
    var campaignSelect = event.target.closest('[data-reward-form] select[name="campaign_id"]');
    if (campaignSelect) updateRewardCampaign(campaignSelect.form);
  });

  document.addEventListener('submit', function (event) {
    var inRoot = root.contains(event.target);
    var inDrawer = drawer && drawer.contains(event.target);
    if (!inRoot && !inDrawer) return;
    var crmForm = event.target.closest('[data-crm-form]');
    if (crmForm) {
      event.preventDefault();
      saveCrm(crmForm);
      return;
    }
    var rewardForm = event.target.closest('[data-reward-form]');
    if (rewardForm) {
      event.preventDefault();
      sendReward(rewardForm);
      return;
    }
    var messageForm = event.target.closest('[data-message-form]');
    if (messageForm) {
      event.preventDefault();
      sendMessage(messageForm);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
      event.preventDefault();
      closeDrawer();
      return;
    }
    trapDrawerFocus(event);
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      clearPollTimer();
      if (state.canvasController) state.canvasController.abort();
      setLiveStatus('Live updates paused while tab is hidden', 'warn');
    } else {
      loadCanvas({ force: true, reason: 'tab-visible' });
    }
  });

  portalDrawer();
  ensureBackdrop();
  loadRewardOptions(false);
  loadHealth(false);
  loadCanvas({ force: true, reason: 'initial' });

  window.addEventListener('beforeunload', function () {
    clearPollTimer();
    if (state.canvasController) state.canvasController.abort();
    if (state.selectedController) state.selectedController.abort();
  });
})(window, document);
